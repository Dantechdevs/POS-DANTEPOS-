<?php

namespace App\Services;

use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;

class EtimsService
{
    public function configured(): bool
    {
        foreach (['base_url', 'tin', 'branch_id', 'cmc_key'] as $k) {
            if (empty(config("etims.$k"))) {
                return false;
            }
        }

        return true;
    }

    private function taxClass($rate): string
    {
        if (empty($rate) || (float) $rate == 0.0) {
            return config('etims.zero_class', 'C');
        }
        $key = (string) (float) $rate;
        $map = config('etims.tax_map');
        if (! isset($map[$key])) {
            throw new \Exception("No eTIMS tax class mapped for VAT rate {$rate}%. Add it to config/etims.php tax_map.");
        }

        return $map[$key];
    }

    private function paymentCode($t): string
    {
        if (in_array($t->payment_status, ['due', 'partial'], true)) {
            return '02'; // credit
        }
        $method = DB::table('transaction_payments')->where('transaction_id', $t->id)->orderBy('id')->value('method');

        return ['cash' => '01', 'cheque' => '04', 'card' => '05', 'mpesa' => '06', 'bank_transfer' => '07'][$method] ?? '07';
    }

    public function buildPayload(int $transactionId, int $invoiceNo): array
    {
        $t = DB::table('transactions')->where('id', $transactionId)->first();
        if (! $t) {
            throw new \Exception("Transaction {$transactionId} not found.");
        }
        $contact = DB::table('contacts')->where('id', $t->contact_id)->first();
        $biz = DB::table('business')->where('id', $t->business_id)->first();
        $loc = DB::table('business_locations')->where('id', $t->location_id)->first();

        $lines = DB::table('transaction_sell_lines as l')
            ->join('products as p', 'p.id', '=', 'l.product_id')
            ->leftJoin('tax_rates as tr', 'tr.id', '=', 'l.tax_id')
            ->where('l.transaction_id', $transactionId)
            ->select('p.id as pid', 'p.name', 'p.sku', 'l.quantity', 'l.quantity_returned', 'l.unit_price_inc_tax', 'l.item_tax', 'tr.amount as tax_rate')
            ->get();

        $rates = ['A' => 0, 'B' => 16, 'C' => 0, 'D' => 0, 'E' => 8];
        $b = [];
        foreach (array_keys($rates) as $c) {
            $b[$c] = ['taxbl' => 0, 'tax' => 0];
        }

        $items = [];
        $seq = 0;
        foreach ($lines as $l) {
            $qty = (float) $l->quantity - (float) $l->quantity_returned;
            if ($qty <= 0) {
                continue;
            }
            $cls = $this->taxClass($l->tax_rate);
            $total = round($qty * (float) $l->unit_price_inc_tax, 2);
            $tax = round($qty * (float) $l->item_tax, 2);
            $taxbl = round($total - $tax, 2);
            $b[$cls]['taxbl'] += $taxbl;
            $b[$cls]['tax'] += $tax;

            $items[] = [
                'itemSeq' => ++$seq,
                'itemCd' => $l->sku ?: 'P'.$l->pid,
                'itemClsCd' => config('etims.item_class'),
                'itemNm' => $l->name,
                'pkgUnitCd' => 'NT',
                'pkg' => 1,
                'qtyUnitCd' => 'U',
                'qty' => $qty,
                'prc' => round((float) $l->unit_price_inc_tax, 2),
                'splyAmt' => $total,
                'dcRt' => 0,
                'dcAmt' => 0,
                'taxTyCd' => $cls,
                'taxblAmt' => $taxbl,
                'taxAmt' => $tax,
                'totAmt' => $total,
            ];
        }

        if (empty($items)) {
            throw new \Exception('Sale has no billable lines.');
        }

        $totTaxbl = $totTax = 0;
        $out = [];
        foreach ($b as $c => $v) {
            $out["taxblAmt{$c}"] = round($v['taxbl'], 2);
            $out["taxRt{$c}"] = $rates[$c];
            $out["taxAmt{$c}"] = round($v['tax'], 2);
            $totTaxbl += $v['taxbl'];
            $totTax += $v['tax'];
        }

        $when = Carbon::parse($t->transaction_date);

        return array_merge([
            'tin' => config('etims.tin'),
            'bhfId' => config('etims.branch_id'),
            'invcNo' => $invoiceNo,
            'orgInvcNo' => 0,
            'custTin' => $contact->tax_number ?? null,
            'custNm' => ($contact && ! $contact->is_default) ? $contact->name : null,
            'salesTyCd' => 'N',
            'rcptTyCd' => 'S',
            'pmtTyCd' => $this->paymentCode($t),
            'salesSttsCd' => '02',
            'cfmDt' => $when->format('YmdHis'),
            'salesDt' => $when->format('Ymd'),
            'totItemCnt' => count($items),
        ], $out, [
            'totTaxblAmt' => round($totTaxbl, 2),
            'totTaxAmt' => round($totTax, 2),
            'totAmt' => round($totTaxbl + $totTax, 2),
            'prchrAcptcYn' => 'N',
            'regrId' => 'dantepos',
            'regrNm' => 'DantePOS',
            'modrId' => 'dantepos',
            'modrNm' => 'DantePOS',
            'receipt' => [
                'custTin' => $contact->tax_number ?? null,
                'custMblNo' => $contact->mobile ?? null,
                'rptNo' => $invoiceNo,
                'trdeNm' => $biz->name ?? null,
                'adrs' => $loc->landmark ?? null,
                'topMsg' => $biz->name ?? null,
                'btmMsg' => 'Thank you',
                'prchrAcptcYn' => 'N',
            ],
            'itemList' => $items,
        ]);
    }

    /** @return array{ok:bool,error:?string,response:mixed} */
    public function submit(array $payload): array
    {
        try {
            $client = new Client(['timeout' => 30, 'connect_timeout' => 10, 'http_errors' => false]);
            $res = $client->post(rtrim(config('etims.base_url'), '/').'/saveTrnsSalesOsdc', [
                'headers' => [
                    'tin' => config('etims.tin'),
                    'bhfId' => config('etims.branch_id'),
                    'cmcKey' => config('etims.cmc_key'),
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);
            $body = json_decode((string) $res->getBody(), true);
            $ok = is_array($body) && (($body['resultCd'] ?? null) === '000');

            return ['ok' => $ok, 'error' => $ok ? null : (($body['resultMsg'] ?? null) ?: 'HTTP '.$res->getStatusCode()), 'response' => $body];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'response' => null];
        }
    }
}
