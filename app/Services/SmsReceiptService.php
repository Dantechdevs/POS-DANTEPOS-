<?php

namespace App\Services;

use App\Utils\TransactionUtil;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SmsReceiptService
{
    private const PHONE_REGEX = '/^(?:254|0)?([17]\d{8})$/';

    private const METHOD_LABELS = [
        'mpesa' => 'M-Pesa', 'cash' => 'Cash', 'card' => 'Card',
        'bank_transfer' => 'Bank', 'custom_pay_1' => 'Gift card', 'cheque' => 'Cheque', 'advance' => 'Advance',
    ];

    protected $sms;
    protected $transactionUtil;

    public function __construct(AfricasTalkingService $sms, TransactionUtil $transactionUtil)
    {
        $this->sms = $sms;
        $this->transactionUtil = $transactionUtil;
    }

    /**
     * Decide whether a sale should get an SMS receipt and build the message.
     * Nothing is sent or saved here.
     */
    public function prepare(int $transactionId, bool $force = false): array
    {
        $t = DB::table('transactions')->where('id', $transactionId)->first();

        if (! $t || $t->type !== 'sell') {
            return ['ok' => false, 'reason' => 'Not a sale.'];
        }

        if ($t->status !== 'final' || $t->is_suspend) {
            return ['ok' => false, 'reason' => 'Sale is not final (draft, quotation or suspended).'];
        }

        $business = DB::table('business')->where('id', $t->business_id)->first();
        $tz = ($business->time_zone ?? null) ?: 'Africa/Nairobi';

        if (! $force && Carbon::parse($t->transaction_date, $tz)->lt(now()->subDay())) {
            return ['ok' => false, 'reason' => 'Sale is older than 24 hours (use --force to send anyway).'];
        }

        $contact = $t->contact_id ? DB::table('contacts')->where('id', $t->contact_id)->first() : null;
        $phone = $contact ? $this->normalise((string) $contact->mobile) : null;

        if (! $phone) {
            return ['ok' => false, 'reason' => 'Customer has no valid mobile number (walk-in customers are skipped).'];
        }

        $payments = DB::table('transaction_payments')
            ->where('transaction_id', $t->id)
            ->where('is_return', 0)
            ->get(['method', 'amount']);

        $total = (float) $t->final_total;
        $paid = (float) $payments->sum('amount');
        $balance = max(0, $total - $paid);

        if (! $force && config('services.sms_receipt.mode') === 'credit' && $balance <= 0.009) {
            return ['ok' => false, 'reason' => 'Credit-only mode and this sale is fully paid.'];
        }

        $methods = $payments->pluck('method')
            ->map(fn ($m) => self::METHOD_LABELS[$m] ?? 'Other')
            ->unique()->take(2)->implode('+');

        $name = substr((string) ($business->name ?? 'Receipt'), 0, 20);
        $url = $this->transactionUtil->getInvoiceUrl($t->id, $t->business_id);

        if ($balance <= 0.009) {
            $body = $name.': Receipt '.$t->invoice_no.', KSh '.$this->money($total).' paid'.($methods ? ' by '.$methods : '').'.';
        } else {
            $body = $name.': Receipt '.$t->invoice_no.', total KSh '.$this->money($total)
                .', paid KSh '.$this->money($paid).', balance KSh '.$this->money($balance).'.';
        }

        $body .= ' View: '.$url.' Thank you for shopping with us!';

        return [
            'ok' => true,
            'reason' => 'Ready.',
            'phone' => $phone,
            'message' => preg_replace('/[^\x20-\x7E]/', '', $body),
            'invoice' => $t->invoice_no,
            'transaction_id' => $t->id,
            'business_id' => $t->business_id,
        ];
    }

    /**
     * Send the receipt once. A unique row per sale stops duplicates when a sale is edited.
     */
    public function send(int $transactionId, bool $force = false): array
    {
        $p = $this->prepare($transactionId, $force);

        if (! $p['ok']) {
            return $p;
        }

        $claimed = DB::table('sms_receipts')->insertOrIgnore([
            'transaction_id' => $transactionId,
            'business_id' => $p['business_id'],
            'phone' => $p['phone'],
            'status' => 'sending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($claimed === 0) {
            if (! $force) {
                return ['ok' => false, 'reason' => 'Already handled (use --force to resend).'];
            }

            DB::table('sms_receipts')->where('transaction_id', $transactionId)
                ->update(['phone' => $p['phone'], 'status' => 'sending', 'error' => null, 'updated_at' => now()]);
        }

        try {
            $result = $this->sms->send($p['phone'], $p['message']);
            $ok = ($result['sent'] ?? 0) > 0;

            DB::table('sms_receipts')->where('transaction_id', $transactionId)->update([
                'status' => $ok ? 'sent' : 'failed',
                'error' => $ok ? null : substr(json_encode($result['recipients'] ?? []), 0, 250),
                'updated_at' => now(),
            ]);

            return array_merge($p, [
                'ok' => $ok,
                'reason' => $ok ? 'Sent.' : 'Africa\'s Talking did not accept the message.',
            ]);
        } catch (\Throwable $e) {
            Log::error('SMS receipt failed for sale '.$transactionId.': '.$e->getMessage());

            DB::table('sms_receipts')->where('transaction_id', $transactionId)->update([
                'status' => 'failed',
                'error' => substr($e->getMessage(), 0, 250),
                'updated_at' => now(),
            ]);

            return array_merge($p, ['ok' => false, 'reason' => $e->getMessage()]);
        }
    }

    private function normalise(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        return preg_match(self::PHONE_REGEX, $digits, $m) ? '254'.$m[1] : null;
    }

    private function money(float $v): string
    {
        return number_format($v, $v == floor($v) ? 0 : 2);
    }
}
