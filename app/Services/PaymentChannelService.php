<?php

namespace App\Services;

use App\Models\DanteBankAccount;
use Illuminate\Support\Facades\DB;

/**
 * Reads the Kenyan payment channels and bank accounts a business has set up.
 * Customer-facing pages should use active(), which returns only methods that are
 * switched on AND have their details filled in.
 */
class PaymentChannelService
{
    public const LABELS = [
        'airtel_money' => 'Airtel Money',
        'equitel' => 'Equitel',
        'pesalink' => 'Pesalink',
        'card' => 'Visa / Mastercard',
    ];

    public const FIELDS = [
        'airtel_money' => [
            'business_name' => 'Name shown to customers',
            'account_number' => 'Airtel Money number',
            'account_reference' => 'Account reference (optional)',
        ],
        'equitel' => [
            'business_name' => 'Name shown to customers',
            'account_number' => 'Equitel number',
            'account_reference' => 'Account reference (optional)',
        ],
        'pesalink' => [
            'business_name' => 'Account name',
            'account_number' => 'Account number',
            'account_reference' => 'Bank name',
        ],
        'card' => [],
    ];

    public const BANKS = [
        'KCB Bank Kenya', 'Equity Bank', 'Co-operative Bank of Kenya', 'Absa Bank Kenya',
        'NCBA Bank', 'Standard Chartered Bank Kenya', 'Stanbic Bank Kenya', 'Family Bank',
        'DTB Kenya', 'I&M Bank', 'Sidian Bank', 'National Bank of Kenya', 'Prime Bank',
        'Bank of Africa Kenya', 'Citibank Kenya', 'Access Bank Kenya', 'Kingdom Bank',
        'Gulf African Bank', 'M-Oriental Bank', 'Consolidated Bank of Kenya',
    ];

    public static function channels(int $businessId): array
    {
        $rows = DB::table('dantepos_payment_channels')->where('business_id', $businessId)->get()->keyBy('channel');
        $out = [];

        foreach (self::LABELS as $key => $label) {
            $r = $rows->get($key);
            $row = [
                'key' => $key,
                'label' => $label,
                'is_enabled' => $r ? (bool) $r->is_enabled : false,
                'business_name' => $r->business_name ?? '',
                'account_number' => $r->account_number ?? '',
                'account_reference' => $r->account_reference ?? '',
                'instructions' => $r->instructions ?? '',
            ];
            $row['status'] = self::status($key, $row);
            $out[$key] = $row;
        }

        return $out;
    }

    public static function isConfigured(string $key, array $row): bool
    {
        return $key === 'card'
            ? trim((string) $row['instructions']) !== ''
            : trim((string) $row['account_number']) !== '';
    }

    public static function status(string $key, array $row): string
    {
        if (! self::isConfigured($key, $row)) {
            return 'not_configured';
        }

        return $row['is_enabled'] ? 'active' : 'inactive';
    }

    public static function banks(int $businessId)
    {
        return DanteBankAccount::where('business_id', $businessId)
            ->orderByDesc('is_active')->orderBy('bank_name')->get();
    }

    /** Only what customers should see: enabled and configured. */
    public static function active(int $businessId): array
    {
        return [
            'channels' => array_filter(self::channels($businessId), fn ($c) => $c['status'] === 'active'),
            'banks' => self::banks($businessId)->where('is_active', true)->values(),
        ];
    }
}
