<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Per-business M-Pesa receiving details and the rule for recording
 * M-Pesa payments without an STK confirmation.
 */
class MpesaSettingsService
{
    public static function get(int $businessId): array
    {
        $r = DB::table('mpesa_settings')->where('business_id', $businessId)->first();

        return [
            'account_type' => $r->account_type ?? 'till',
            'shortcode' => $r->shortcode ?? '',
            'till_number' => $r->till_number ?? '',
            'account_hint' => $r->account_hint ?? '',
            'manual_enabled' => $r ? (bool) $r->manual_enabled : false,
            'manual_unverified_cap' => $r ? (float) $r->manual_unverified_cap : 0.0,
        ];
    }

    /** True when a cashier may record this amount without an STK confirmation. */
    public static function manualAllowed(int $businessId, float $amount): bool
    {
        $s = self::get($businessId);

        return $s['manual_enabled']
            && $s['manual_unverified_cap'] > 0
            && $amount <= $s['manual_unverified_cap'];
    }
}
