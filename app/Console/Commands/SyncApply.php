<?php

namespace App\Console\Commands;

use App\Utils\BusinessUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncApply extends Command
{
    protected $signature = 'sync:apply {--limit=25 : Sales per run}';

    protected $description = 'Turn sales received from satellites into real sales on the primary server';

    public function handle()
    {
        if (config('sync.role') !== 'primary') {
            $this->error('sync:apply only runs on the primary server (DANTE_ROLE=primary).');

            return self::FAILURE;
        }

        config(['sync.importing' => true]);

        $rows = DB::table('sync_inbox')
            ->where('status', 'received')->where('entity', 'sale')
            ->orderBy('id')->limit(max(1, min(100, (int) $this->option('limit'))))->get();

        if ($rows->isEmpty()) {
            $this->info('Nothing to apply.');

            return self::SUCCESS;
        }

        $ok = $skipped = $bad = 0;
        foreach ($rows as $row) {
            try {
                $result = $this->applySale($row);
                if ($result === 'skipped') {
                    $skipped++;
                } else {
                    $ok++;
                }
            } catch (\Throwable $e) {
                $bad++;
                $this->mark($row->id, 'error', null, mb_substr($e->getMessage(), 0, 500));
                $this->warn("{$row->uuid}: ".$e->getMessage());
            }
        }

        $this->info("Applied {$ok}, skipped {$skipped} (already on the server), {$bad} failed.");

        return $bad ? self::FAILURE : self::SUCCESS;
    }

    private function applySale($row): string
    {
        $p = json_decode($row->payload, true);
        $meta = $p['meta'] ?? null;
        $input = $p['request'] ?? null;
        if (! $meta || ! $input || empty($input['products'])) {
            throw new \RuntimeException('Payload has no sale data.');
        }

        $bid = (int) $meta['business_id'];
        $uid = (int) $meta['created_by'];
        $inv = (string) $meta['invoice_no'];
        $loc = (int) $meta['location_id'];

        if (! DB::table('business')->where('id', $bid)->exists()) {
            throw new \RuntimeException("Business {$bid} not found.");
        }
        if (! DB::table('users')->where('id', $uid)->exists()) {
            throw new \RuntimeException("User {$uid} not found.");
        }
        if (! DB::table('contacts')->where('id', $meta['contact_id'])->where('business_id', $bid)->exists()) {
            throw new \RuntimeException("Customer {$meta['contact_id']} not found on the server.");
        }
        if (! DB::table('business_locations')->where('id', $loc)->where('business_id', $bid)->exists()) {
            throw new \RuntimeException("Location {$loc} not found.");
        }
        foreach ($input['products'] as $line) {
            if (! DB::table('variations')->where('id', $line['variation_id'])->where('product_id', $line['product_id'])->exists()) {
                throw new \RuntimeException("Product {$line['product_id']} / variation {$line['variation_id']} not found on the server.");
            }
        }

        if (DB::table('transactions')->where('business_id', $bid)->where('type', 'sell')->where('invoice_no', $inv)->exists()) {
            $this->mark($row->id, 'skipped', null, 'A sale with this invoice number already exists.');

            return 'skipped';
        }

        Auth::loginUsingId($uid);

        $tu = app(TransactionUtil::class);
        $pu = app(ProductUtil::class);
        $bu = app(BusinessUtil::class);
        $warnings = [];
        $txId = null;

        DB::transaction(function () use ($input, $meta, $bid, $uid, $inv, $loc, $tu, $pu, $bu, &$warnings, &$txId) {
            $input['type'] = 'sell';
            $input['status'] = 'final';
            $input['invoice_no'] = $inv;
            $input['transaction_date'] = $meta['transaction_date'];
            $input['location_id'] = $loc;
            $input['contact_id'] = $meta['contact_id'];
            $input['final_total'] = $meta['final_total'];

            $invoiceTotal = $pu->calculateInvoiceTotal(
                $input['products'],
                $input['tax_rate_id'] ?? null,
                ['discount_type' => $input['discount_type'] ?? null, 'discount_amount' => $input['discount_amount'] ?? 0],
                false
            );

            $t = $tu->createSellTransaction($bid, $input, $invoiceTotal, $uid, false);
            $tu->createOrUpdateSellLines($t, $input['products'], $loc, false, null, [], false);

            $payments = $input['payment'] ?? [];
            $change = $payments['change_return'] ?? [];
            unset($payments['change_return']);
            $change['amount'] = $input['change_return'] ?? 0;
            $change['is_return'] = 1;
            $payments[] = $change;
            foreach ($payments as $i => $pm) {
                $payments[$i]['paid_on'] = $meta['transaction_date'];
            }

            if (empty($input['is_credit_sale'])) {
                $tu->createOrUpdatePaymentLines($t, $payments, $bid, $uid, false);
            }

            foreach ($input['products'] as $line) {
                $qty = $pu->num_uf($line['quantity']);
                if (! empty($line['base_unit_multiplier'])) {
                    $qty = $qty * $line['base_unit_multiplier'];
                }
                if (! empty($line['enable_stock'])) {
                    $pu->decreaseProductQuantity($line['product_id'], $line['variation_id'], $loc, $qty);
                    $left = DB::table('variation_location_details')
                        ->where('variation_id', $line['variation_id'])->where('location_id', $loc)->value('qty_available');
                    if ($left !== null && $left < 0) {
                        $warnings[] = "product {$line['product_id']} stock is now {$left}";
                    }
                }
                if (($line['product_type'] ?? '') === 'combo' && ! empty($line['combo'])) {
                    $pu->decreaseProductQuantityCombo($line['combo'], $loc);
                }
            }

            $t->payment_status = $tu->updatePaymentStatus($t->id, $t->final_total);
            $t->save();

            $biz = DB::table('business')->where('id', $bid)->first();
            $pos = empty($biz->pos_settings) ? $bu->defaultPosSettings() : json_decode($biz->pos_settings, true);
            $tu->mapPurchaseSell([
                'id' => $bid,
                'accounting_method' => $biz->accounting_method,
                'location_id' => $loc,
                'pos_settings' => $pos,
            ], $t->sell_lines, 'purchase');

            $txId = $t->id;
        });

        $this->mark($row->id, 'applied', $txId, $warnings ? implode('; ', $warnings) : null);
        foreach ($warnings as $w) {
            $this->warn("{$row->uuid}: {$w}");
        }

        return 'applied';
    }

    private function mark(int $id, string $status, $txId, ?string $note): void
    {
        $data = ['status' => $status, 'updated_at' => now()];
        if (Schema::hasColumn('sync_inbox', 'transaction_id')) {
            $data['transaction_id'] = $txId;
        }
        if (Schema::hasColumn('sync_inbox', 'last_error')) {
            $data['last_error'] = $note;
        }
        if ($status === 'applied' && Schema::hasColumn('sync_inbox', 'applied_at')) {
            $data['applied_at'] = now();
        }
        DB::table('sync_inbox')->where('id', $id)->update($data);
    }
}
