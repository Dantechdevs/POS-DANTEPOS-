<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncStatus extends Command
{
    protected $signature = 'sync:status';

    protected $description = 'Show this install\'s sync role, queue sizes and connected devices';

    public function handle()
    {
        $role = config('sync.role');
        $this->line('Role: '.$role);
        if (! in_array($role, ['standalone', 'primary', 'satellite'], true)) {
            $this->error('DANTE_ROLE must be standalone, primary or satellite.');
        }

        if ($role === 'satellite') {
            $this->line('Device id: '.(config('sync.device_id') ?: '(not set)'));
            $this->line('Token: '.(config('sync.device_token') ? 'set' : 'NOT SET'));
            $this->line('Server: '.(config('sync.server_url') ?: '(not set)'));
            foreach (DB::table('sync_outbox')->selectRaw('status, COUNT(*) as n')->groupBy('status')->get() as $r) {
                $this->line("Outbox {$r->status}: {$r->n}");
            }
        }

        if ($role === 'primary') {
            foreach (DB::table('sync_inbox')->selectRaw('status, COUNT(*) as n')->groupBy('status')->get() as $r) {
                $this->line("Inbox {$r->status}: {$r->n}");
            }
            $rows = DB::table('sync_devices')->get(['device_id', 'name', 'active', 'last_seen_at'])->map(fn ($d) => [
                $d->device_id, $d->name, $d->active ? 'yes' : 'NO', $d->last_seen_at ?: 'never',
            ])->all();
            $rows ? $this->table(['Device', 'Name', 'Active', 'Last seen'], $rows) : $this->line('No devices registered.');
        }

        return self::SUCCESS;
    }
}
