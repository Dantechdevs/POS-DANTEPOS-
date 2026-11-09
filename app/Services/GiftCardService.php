<?php

namespace App\Services;

use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GiftCardService
{
    const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O/1/I

    public static function normalize($code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $code));
    }

    public static function format(string $code): string
    {
        return strlen($code) === 12
            ? substr($code, 0, 2).'-'.substr($code, 2, 4).'-'.substr($code, 6, 4).'-'.substr($code, 10, 2)
            : $code;
    }

    public static function generate(): string
    {
        do {
            $c = 'GC';
            for ($i = 0; $i < 10; $i++) {
                $c .= self::ALPHABET[random_int(0, 31)];
            }
        } while (DB::table('gift_cards')->where('code', $c)->exists());

        return $c;
    }

    public static function unusable($card, float $amount): ?string
    {
        if (! $card) {
            return 'Gift card not found.';
        }
        if ($card->status !== 'active') {
            return 'This gift card has been voided.';
        }
        if ($card->expires_at && $card->expires_at < date('Y-m-d')) {
            return 'This gift card expired on '.date('d/m/Y', strtotime($card->expires_at)).'.';
        }
        if ((float) $card->balance + 0.0001 < $amount) {
            return 'Gift card balance is KSh '.number_format($card->balance, 2).', less than the KSh '.number_format($amount, 2).' requested.';
        }

        return null;
    }

    /** Server-side pre-check of Gift Card payment rows on a sale. */
    public static function violation(Request $request, $business_id): ?string
    {
        if ($request->input('status') !== 'final') {
            return null;
        }

        $util = app(Util::class);
        $totals = [];

        foreach ((array) $request->input('payment', []) as $p) {
            if (($p['method'] ?? '') !== 'gift_card') {
                continue;
            }
            $amt = (float) $util->num_uf($p['amount'] ?? 0);
            if ($amt <= 0) {
                continue;
            }
            $code = self::normalize($p['gift_card_code'] ?? '');
            if ($code === '') {
                return 'Enter the gift card code for the Gift Card payment.';
            }
            $totals[$code] = ($totals[$code] ?? 0) + $amt;
        }

        foreach ($totals as $code => $amt) {
            $card = DB::table('gift_cards')->where('business_id', $business_id)->where('code', $code)->first();
            if ($msg = self::unusable($card, $amt)) {
                return $msg;
            }
        }

        return null;
    }

    public static function log($cardId, $businessId, string $type, float $amount, float $balanceAfter, array $extra = []): void
    {
        DB::table('gift_card_transactions')->insert(array_merge([
            'gift_card_id' => $cardId,
            'business_id' => $businessId,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'user_id' => auth()->id(),
            'created_at' => now(),
        ], $extra));
    }

    private static function businessIdOf($tp)
    {
        return $tp->business_id ?: DB::table('transactions')->where('id', $tp->transaction_id)->value('business_id');
    }

    /** Deduct from the card. Call inside the sale's DB transaction; throws on any problem. */
    public static function redeem($tp, float $amount): void
    {
        $code = self::normalize($tp->gift_card_code);
        if ($code === '') {
            throw new \Exception('A gift card code is required for Gift Card payments. Gift cards can only be redeemed at the POS.');
        }

        $bid = self::businessIdOf($tp);
        $card = DB::table('gift_cards')->where('business_id', $bid)->where('code', $code)->lockForUpdate()->first();

        if ($msg = self::unusable($card, $amount)) {
            throw new \Exception($msg);
        }

        $new = round((float) $card->balance - $amount, 4);
        DB::table('gift_cards')->where('id', $card->id)->update(['balance' => $new, 'updated_at' => now()]);
        self::log($card->id, $bid, 'redeem', -$amount, $new, [
            'transaction_id' => $tp->transaction_id,
            'transaction_payment_id' => $tp->id,
        ]);
    }

    /** Put money back on the card (payment edited down or deleted). */
    public static function restore($tp, float $amount, string $note): void
    {
        $code = self::normalize($tp->gift_card_code);
        if ($code === '' || $amount <= 0) {
            return;
        }

        $bid = self::businessIdOf($tp);
        $card = DB::table('gift_cards')->where('business_id', $bid)->where('code', $code)->lockForUpdate()->first();
        if (! $card) {
            return;
        }

        $new = round((float) $card->balance + $amount, 4);
        DB::table('gift_cards')->where('id', $card->id)->update(['balance' => $new, 'updated_at' => now()]);
        self::log($card->id, $bid, 'restore', $amount, $new, [
            'transaction_id' => $tp->transaction_id,
            'transaction_payment_id' => $tp->id,
            'note' => $note,
        ]);
    }
}
