<?php

namespace App\Services;

use App\Exceptions\PurchaseSellMismatch;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GiftCardService
{
    /** No 0/O/1/I so codes are easy to read out and type. */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public const ISSUE_METHODS = [
        'cash' => 'Cash',
        'mpesa' => 'M-Pesa',
        'card' => 'Card',
        'bank_transfer' => 'Bank transfer',
        'free' => 'Free (promo or credit note)',
    ];

    /** The payment method (custom_pay_1 .. custom_pay_7) that means "paid with a gift card". */
    public function method(): ?string
    {
        $m = (string) config('services.gift_card.method');

        return $m !== '' ? $m : null;
    }

    public function normalise(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    /** @return array{0:string,1:string} [display code, lookup key] */
    public function generateCode(): array
    {
        $prefix = $this->normalise((string) config('services.gift_card.prefix')) ?: 'GC';

        do {
            $part = '';
            for ($i = 0; $i < 8; $i++) {
                $part .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $key = $prefix.$part;
        } while (DB::table('gift_cards')->where('code_key', $key)->exists());

        return [$prefix.'-'.substr($part, 0, 4).'-'.substr($part, 4), $key];
    }

    public function status($card): string
    {
        if ($card->status === 'void') {
            return 'void';
        }

        if ((float) $card->balance <= 0.0001) {
            return 'used';
        }

        if ($card->expires_at && Carbon::parse($card->expires_at)->endOfDay()->isPast()) {
            return 'expired';
        }

        return 'active';
    }

    public function find(int $businessId, string $key, bool $lock = false)
    {
        $q = DB::table('gift_cards')->where('business_id', $businessId)->where('code_key', $key);

        if ($lock) {
            $q->lockForUpdate();
        }

        return $q->first();
    }

    public function issue(int $businessId, float $amount, array $meta, ?int $userId)
    {
        [$code, $key] = $this->generateCode();
        $now = now();
        $via = $meta['issued_via'] ?? 'cash';

        $id = DB::table('gift_cards')->insertGetId([
            'business_id' => $businessId,
            'code' => $code,
            'code_key' => $key,
            'initial_amount' => $amount,
            'balance' => $amount,
            'status' => 'active',
            'expires_at' => $meta['expires_at'] ?? null,
            'recipient_name' => $meta['recipient_name'] ?? null,
            'recipient_phone' => $meta['recipient_phone'] ?? null,
            'issued_via' => $via,
            'note' => $meta['note'] ?? null,
            'issued_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('gift_card_transactions')->insert([
            'gift_card_id' => $id,
            'business_id' => $businessId,
            'type' => 'issue',
            'amount' => $amount,
            'balance_after' => $amount,
            'user_id' => $userId,
            'note' => 'Issued, '.strtolower(self::ISSUE_METHODS[$via] ?? $via),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DB::table('gift_cards')->where('id', $id)->first();
    }

    /** Manual change from the Gift Cards screen (positive adds, negative removes). */
    public function adjust(int $businessId, int $cardId, float $delta, string $note, ?int $userId): float
    {
        return DB::transaction(function () use ($businessId, $cardId, $delta, $note, $userId) {
            $card = DB::table('gift_cards')->where('business_id', $businessId)->where('id', $cardId)->lockForUpdate()->first();

            if (! $card) {
                throw new \RuntimeException('Gift card not found.');
            }

            return $this->applyDelta($card, $delta, 'adjust', null, null, $note, $userId);
        });
    }

    public function void(int $businessId, int $cardId, string $reason, ?int $userId): void
    {
        DB::transaction(function () use ($businessId, $cardId, $reason, $userId) {
            $card = DB::table('gift_cards')->where('business_id', $businessId)->where('id', $cardId)->lockForUpdate()->first();

            if (! $card) {
                throw new \RuntimeException('Gift card not found.');
            }

            if ($card->status === 'void') {
                throw new \RuntimeException('This gift card is already cancelled.');
            }

            if ((float) $card->balance > 0.0001) {
                $this->applyDelta($card, -(float) $card->balance, 'void', null, null, $reason, $userId);
            }

            DB::table('gift_cards')->where('id', $cardId)->update(['status' => 'void', 'updated_at' => now()]);
        });
    }

    public function isGiftPayment($payment): bool
    {
        $m = $this->method();

        return $m !== null
            && (string) $payment->method === $m
            && empty($payment->parent_id)
            && empty($payment->is_return);
    }

    /**
     * Make the card ledger match one sale payment.
     * Safe to call repeatedly: it only moves the difference, so edits, deletes and
     * duplicate events never charge or refund twice. Throws (so the sale is rolled back)
     * when the code is wrong, expired, cancelled or the balance is too low.
     */
    public function syncPayment($payment, bool $deleted = false): void
    {
        $paymentId = (int) $payment->id;
        $transactionId = $payment->transaction_id ? (int) $payment->transaction_id : null;
        $userId = auth()->id();

        // Net effect already recorded for this payment, per card (negative = card was charged).
        $current = DB::table('gift_card_transactions')
            ->where('transaction_payment_id', $paymentId)
            ->select('gift_card_id', DB::raw('SUM(amount) as net'))
            ->groupBy('gift_card_id')
            ->pluck('net', 'gift_card_id');

        $wantCardId = null;
        $wantAmount = 0.0;

        if (! $deleted && $this->isGiftPayment($payment)) {
            $businessId = (int) ($payment->business_id ?: DB::table('transactions')->where('id', $payment->transaction_id)->value('business_id'));
            $key = $this->normalise((string) $payment->transaction_no);

            if ($key === '') {
                throw new PurchaseSellMismatch('Type the gift card code in the Transaction No. field.');
            }

            $card = $this->find($businessId, $key, true);

            if (! $card) {
                throw new PurchaseSellMismatch('Gift card not found: '.strtoupper(trim((string) $payment->transaction_no)));
            }

            $wantCardId = (int) $card->id;
            $wantAmount = round((float) $payment->amount, 4);
        }

        // 1. Give money back to any card this payment no longer uses (fully or partly).
        foreach ($current as $cardId => $net) {
            $debited = round(-1 * (float) $net, 4);
            $want = ($wantCardId === (int) $cardId) ? $wantAmount : 0.0;
            $delta = round($want - $debited, 4);

            if ($delta < -0.0001) {
                $old = DB::table('gift_cards')->where('id', $cardId)->lockForUpdate()->first();

                if ($old) {
                    $this->applyDelta($old, -$delta, 'refund', $paymentId, $transactionId, 'Payment changed or removed', $userId);
                }
            }
        }

        // 2. Take money from the card this payment now uses.
        if ($wantCardId !== null) {
            $debited = round(-1 * (float) ($current[$wantCardId] ?? 0), 4);
            $more = round($wantAmount - $debited, 4);

            if ($more > 0.0001) {
                $card = DB::table('gift_cards')->where('id', $wantCardId)->lockForUpdate()->first();

                $this->assertUsable($card);

                if ($more > (float) $card->balance + 0.0001) {
                    throw new PurchaseSellMismatch(
                        'Gift card '.$card->code.' has only KSh '.number_format((float) $card->balance, 2).' left, which is less than KSh '.number_format($more, 2).'.'
                    );
                }

                $invoice = $transactionId ? DB::table('transactions')->where('id', $transactionId)->value('invoice_no') : null;

                $this->applyDelta($card, -$more, 'redeem', $paymentId, $transactionId, $invoice ? 'Redeemed on invoice '.$invoice : 'Redeemed', $userId);
            }
        }
    }

    private function assertUsable($card): void
    {
        if ($card->status !== 'active') {
            throw new PurchaseSellMismatch('Gift card '.$card->code.' has been cancelled.');
        }

        if ($card->expires_at && Carbon::parse($card->expires_at)->endOfDay()->isPast()) {
            throw new PurchaseSellMismatch('Gift card '.$card->code.' expired on '.Carbon::parse($card->expires_at)->format('d/m/Y').'.');
        }
    }

    private function applyDelta($card, float $delta, string $type, ?int $paymentId, ?int $transactionId, string $note, ?int $userId): float
    {
        $new = round((float) $card->balance + $delta, 4);

        if ($new < -0.0001) {
            throw new PurchaseSellMismatch('Gift card '.$card->code.' balance cannot go below zero.');
        }

        DB::table('gift_cards')->where('id', $card->id)->update(['balance' => $new, 'updated_at' => now()]);

        DB::table('gift_card_transactions')->insert([
            'gift_card_id' => $card->id,
            'business_id' => $card->business_id,
            'type' => $type,
            'amount' => $delta,
            'balance_after' => $new,
            'transaction_payment_id' => $paymentId,
            'transaction_id' => $transactionId,
            'user_id' => $userId,
            'note' => substr($note, 0, 255),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $new;
    }
}
