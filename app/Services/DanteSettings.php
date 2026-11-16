<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DanteSettings
{
    /** setting name => config key it overrides */
    const MAP = [
        'price_guard_mode' => 'pricing.guard_mode',
        'credit_require_phone' => 'credit.require_phone',
        'credit_require_limit' => 'credit.require_limit',
        'owner_phones' => 'summary.phones',
        'backup_enabled' => 'backup.enabled',
        'backup_time' => 'backup.time',
        'backup_keep_days' => 'backup.keep_days',
    ];

    const BOOLS = ['credit_require_phone', 'credit_require_limit', 'backup_enabled'];

    /** Used when neither the database nor config has a value. */
    const DEFAULTS = ['backup_enabled' => true, 'backup_time' => '21:00'];

    public static function all(): array
    {
        try {
            return DB::table('dantepos_settings')->pluck('value', 'name')->all();
        } catch (\Throwable $e) {
            return []; // table not created yet
        }
    }

    public static function set(string $name, $value): void
    {
        DB::table('dantepos_settings')->updateOrInsert(
            ['name' => $name],
            ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    /** Current effective values (database over .env defaults). */
    public static function current(): array
    {
        $out = [];
        foreach (self::MAP as $name => $key) {
            $out[$name] = config($key, self::DEFAULTS[$name] ?? null);
        }

        return $out;
    }

    /** Called at startup: database settings win over .env defaults. */
    public static function applyToConfig(): void
    {
        foreach (self::all() as $name => $value) {
            if (! isset(self::MAP[$name])) {
                continue;
            }
            if ($name === 'price_guard_mode' && ! in_array($value, ['off', 'warn', 'block'], true)) {
                continue;
            }
            if ($name === 'backup_time' && ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $value)) {
                continue;
            }
            if ($name === 'backup_keep_days') {
                $value = (int) $value;
                if ($value < 1 || $value > 365) {
                    continue;
                }
            }
            config([self::MAP[$name] => in_array($name, self::BOOLS, true) ? (bool) (int) $value : $value]);
        }
    }
}
