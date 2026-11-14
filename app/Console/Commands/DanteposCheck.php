<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DanteposCheck extends Command
{
    protected $signature = 'dantepos:check';

    protected $description = 'Pre-flight check: database, migrations, permissions, config, broken code references, backups, scheduler';

    private $fails = 0;
    private $warns = 0;

    private function good($m) { $this->line('[ OK ]   '.$m); }
    private function flag($m) { $this->warns++; $this->line('[ WARN ] '.$m); }
    private function bad($m) { $this->fails++; $this->line('[ FAIL ] '.$m); }

    public function handle()
    {
        $this->info('DantePOS pre-flight check');

        // Environment
        if (config('app.debug')) {
            config('app.env') === 'production'
                ? $this->bad('APP_DEBUG is true in production. Set APP_DEBUG=false.')
                : $this->flag('APP_DEBUG is true. Set APP_DEBUG=false before go-live.');
        } else {
            $this->good('APP_DEBUG is off');
        }

        foreach ([storage_path(), base_path('bootstrap/cache')] as $dir) {
            is_writable($dir) ? $this->good('Writable: '.basename($dir)) : $this->bad('Not writable: '.$dir);
        }

        // Database
        try {
            DB::connection()->getPdo();
            $this->good('Database connection');
        } catch (\Throwable $e) {
            $this->bad('Database: '.$e->getMessage());

            return self::FAILURE;
        }

        // Migrations
        $ran = DB::table('migrations')->pluck('migration')->all();
        $files = array_map(fn ($f) => basename($f, '.php'), glob(database_path('migrations/*.php')) ?: []);
        $pending = array_diff($files, $ran);
        empty($pending)
            ? $this->good('No pending migrations')
            : $this->bad(count($pending).' pending migration(s). Run php artisan migrate. First: '.reset($pending));

        // Tables and columns
        foreach (['debt_reminder_logs', 'etims_invoices', 'gift_cards', 'gift_card_transactions', 'stocktakes', 'stocktake_lines', 'mpesa_transactions'] as $t) {
            Schema::hasTable($t) ? $this->good("Table {$t}") : $this->bad("Missing table {$t}");
        }
        Schema::hasColumn('transaction_payments', 'gift_card_code')
            ? $this->good('Column transaction_payments.gift_card_code')
            : $this->bad('Missing column transaction_payments.gift_card_code');

        // Permissions
        $perms = ['credit.override', 'mpesa.reconcile', 'stocktake.manage', 'giftcard.manage', 'price.below_cost'];
        $admin = Role::where('name', 'like', 'Admin#%')->first();
        foreach ($perms as $p) {
            if (! Permission::where('name', $p)->exists()) {
                $this->bad("Permission {$p} does not exist");
            } elseif ($admin && ! $admin->hasPermissionTo($p)) {
                $this->flag("Admin role does not have {$p}");
            } else {
                $this->good("Permission {$p}");
            }
        }

        // Config
        config('africastalking.username') && config('africastalking.api_key')
            ? $this->good('Africa\'s Talking keys set')
            : $this->flag('Africa\'s Talking keys missing. SMS reminders, daily summary and gift card SMS will not send.');

        config('summary.phones') ? $this->good('OWNER_PHONES set') : $this->flag('OWNER_PHONES not set. No daily summary or backup-failure SMS.');

        $mp = config('services.mpesa');
        if (empty($mp['consumer_key']) || empty($mp['consumer_secret']) || empty($mp['shortcode']) || empty($mp['passkey'])) {
            $this->flag('M-Pesa keys incomplete. STK push will fail.');
        } else {
            $this->good('M-Pesa keys set ('.($mp['env'] ?? '?').')');
        }
        $cb = (string) ($mp['callback_url'] ?? '');
        if ($cb === '' || preg_match('#^http://|127\.0\.0\.1|localhost#i', $cb)) {
            $this->flag('M-Pesa callback URL is not a public https address. Safaricom cannot reach it, so payments are only confirmed by polling.');
        } else {
            $this->good('M-Pesa callback URL is public');
        }

        config('pricing.guard_mode') === 'block'
            ? $this->good('Price guard: block')
            : $this->flag(config('pricing.guard_mode') === 'off' ? 'Price guard is OFF. Below-cost sales are not checked or logged.' : 'Price guard is in warn mode. Below-cost sales are logged but allowed.');

        if (config('etims.enabled')) {
            (config('etims.base_url') && config('etims.tin') && config('etims.cmc_key'))
                ? $this->good('eTIMS enabled and configured')
                : $this->bad('eTIMS is enabled but credentials are missing.');
        } else {
            $this->good('eTIMS off (sales are not queued for KRA)');
        }

        // Backups
        $files = glob(storage_path('app/backups/*.sql.gz')) ?: [];
        if (! $files) {
            $this->flag('No backups found. Run php artisan db:backup.');
        } else {
            $age = (time() - max(array_map('filemtime', $files))) / 3600;
            $age < 36 ? $this->good('Latest backup is '.round($age).'h old') : $this->flag('Latest backup is '.round($age).'h old.');
        }

        // Scheduler
        try {
            Artisan::call('schedule:list');
            $sched = Artisan::output();
            foreach (['debts:remind', 'reports:daily-summary', 'db:backup'] as $c) {
                strpos($sched, $c) !== false ? $this->good("Scheduled: {$c}") : $this->flag("Not scheduled: {$c}");
            }
        } catch (\Throwable $e) {
            $this->flag('Could not read the schedule: '.$e->getMessage());
        }

        // Broken references: controllers calling service/util methods that do not exist
        $seen = [];
        foreach (glob(app_path('Http/Controllers/*.php')) ?: [] as $f) {
            if (! preg_match_all('/\\\\App\\\\(Services|Utils)\\\\(\w+)::(\w+)\(/', file_get_contents($f), $m, PREG_SET_ORDER)) {
                continue;
            }
            foreach ($m as $x) {
                $cls = 'App\\'.$x[1].'\\'.$x[2];
                $key = $cls.'::'.$x[3];
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                if (! class_exists($cls)) {
                    $this->bad(basename($f)." calls {$key} but the class does not exist");
                } elseif (! method_exists($cls, $x[3])) {
                    $this->bad(basename($f)." calls {$key} but that method does not exist");
                }
            }
        }
        $this->good('Checked '.count($seen).' service/util calls in controllers');

        $this->line('');
        $this->line("Result: {$this->fails} failure(s), {$this->warns} warning(s).");

        return $this->fails ? self::FAILURE : self::SUCCESS;
    }
}
