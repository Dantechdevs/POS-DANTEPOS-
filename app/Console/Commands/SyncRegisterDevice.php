<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncRegisterDevice extends Command
{
    protected $signature = 'sync:register-device {name : A label, e.g. "Shop PC"}';

    protected $description = 'Register a satellite device on the primary server and print its secret token (shown once)';

    public function handle()
    {
        if (config('sync.role') !== 'primary') {
            $this->error('Set DANTE_ROLE=primary in .env on the server, run php artisan config:clear, then try again.');

            return self::FAILURE;
        }

        $deviceId = 'dev-'.strtolower(Str::random(6));
        $token = Str::random(48);

        DB::table('sync_devices')->insert([
            'device_id' => $deviceId,
            'name' => trim($this->argument('name')),
            'token_hash' => hash('sha256', $token),
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->info('Device registered. Put these in the PC\'s .env. The token is shown only once and is stored only as a hash.');
        $this->line('');
        $this->line('DANTE_ROLE=satellite');
        $this->line('DANTE_DEVICE_ID='.$deviceId);
        $this->line('DANTE_DEVICE_TOKEN='.$token);
        $this->line('DANTE_SYNC_URL=https://YOUR-LIVE-SERVER');
        $this->line('');
        $this->warn('Do not paste the token into chat or commit it to git.');

        return self::SUCCESS;
    }
}
