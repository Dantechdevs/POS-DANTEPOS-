<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-business M-Pesa receiving account (Paybill or Till).
 * When a business has no saved settings the old .env behaviour is kept exactly.
 */
class MpesaSettings
{
    public const TYPE_TILL = 'till';
    public const TYPE_PAYBILL = 'paybill';

    private static array $cache = [];


    public static function forBusiness(?int $businessId): object
    {
        $key = (int) $businessId;

        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $row = null;

        try {
            if ($key > 0 && Schema::hasTable('mpesa_settings')) {
                $row = DB::table('mpesa_settings')->where('business_id', $key)->first();
            }
        } catch (\Throwable $e) {
            $row = null;
        }

        $envShortcode = (string) config('services.mpesa.shortcode');

        return self::$cache[$key] = (object) [
            'configured' => (bool) $row,
            'account_type' => $row->account_type ?? null,
            'shortcode' => (($row->shortcode ?? '') ?: $envShortcode),
            'till_number' => (string) ($row->till_number ?? ''),
            'account_hint' => (string) ($row->account_hint ?? ''),
            'manual_enabled' => $row ? (bool) $row->manual_enabled : true,
            'cap' => $row ? (float) $row->manual_unverified_cap : 20000.0,
        ];
    }


    public static function forget(): void
    {
        self::$cache = [];
    }

    /** Daraja TransactionType for the STK Push. */
    public static function transactionType(object $s): string
    {
        if ($s->account_type === self::TYPE_PAYBILL) {
            return 'CustomerPayBillOnline';
        }

        if ($s->account_type === self::TYPE_TILL) {
            return 'CustomerBuyGoodsOnline';
        }

        return (string) config('services.mpesa.transaction_type', 'CustomerBuyGoodsOnline');
    }

    /**
     * Daraja PartyB. For a Paybill it is the paybill itself; for a Till it is the till number
     * (the BusinessShortCode of a till is its store / head-office number, which can differ).
     */
    public static function partyB(object $s): string
    {
        if ($s->account_type === self::TYPE_TILL && $s->till_number !== '') {
            return $s->till_number;
        }

        return (string) $s->shortcode;
    }


    /** What the cashier tells a customer who is paying by hand. */
    public static function payTo(object $s): string
    {
        $isPaybill = self::transactionType($s) === 'CustomerPayBillOnline';

        if ($isPaybill) {
            return 'Paybill '.$s->shortcode.($s->account_hint !== '' ? ', Account: '.$s->account_hint : '');
        }

        return 'Till '.($s->till_number !== '' ? $s->till_number : $s->shortcode);
    }
}
