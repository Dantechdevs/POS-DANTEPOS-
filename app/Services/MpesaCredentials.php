<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Daraja credentials per business. Secrets are stored encrypted.
 */
class MpesaCredentials
{
    public static function forBusiness(int $businessId): ?array
    {
        $r = DB::table('mpesa_settings')->where('business_id', $businessId)->first();

        if ($r && ! empty($r->consumer_key_enc) && ! empty($r->consumer_secret_enc)
            && ! empty($r->passkey_enc) && ! empty($r->stk_shortcode)) {
            try {
                return [
                    'env' => $r->daraja_env ?: 'sandbox',
                    'consumer_key' => Crypt::decryptString($r->consumer_key_enc),
                    'consumer_secret' => Crypt::decryptString($r->consumer_secret_enc),
                    'passkey' => Crypt::decryptString($r->passkey_enc),
                    'shortcode' => $r->stk_shortcode,
                    'transaction_type' => $r->transaction_type ?: 'CustomerBuyGoodsOnline',
                    'callback_secret' => $r->callback_secret,
                    'callback_url' => $r->callback_secret ? url('/mpesa/callback/'.$r->callback_secret) : null,
                ];
            } catch (\Throwable $e) {
                return null; // unreadable (APP_KEY changed): treat as not set up
            }
        }

        return config('dantepos_mpesa.allow_env_fallback') ? config('services.mpesa') : null;
    }

    public static function hasOwn(int $businessId): array
    {
        $r = DB::table('mpesa_settings')->where('business_id', $businessId)->first();

        return [
            'row' => $r,
            'key' => ! empty($r->consumer_key_enc),
            'secret' => ! empty($r->consumer_secret_enc),
            'passkey' => ! empty($r->passkey_enc),
        ];
    }
}
