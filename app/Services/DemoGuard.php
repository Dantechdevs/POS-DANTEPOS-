<?php

namespace App\Services;

class DemoGuard
{
    /** Business ids that are demo businesses (DANTE_DEMO_BUSINESS_IDS, comma separated). */
    public static function ids(): array
    {
        $raw = (string) config('dante.demo_ids_csv', '');

        return array_values(array_filter(array_map('intval', explode(',', $raw))));
    }
}
