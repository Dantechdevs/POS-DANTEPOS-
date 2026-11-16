<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use Illuminate\Console\Command;

class SyncPing extends Command
{
    protected $signature = 'sync:ping {--url= : Server address (default DANTE_SYNC_URL)} {--token= : Device token (default DANTE_DEVICE_TOKEN)}';

    protected $description = 'Check that this device can reach the primary server and is authorised';

    public function handle()
    {
        $url = rtrim((string) ($this->option('url') ?: config('sync.server_url')), '/');
        $token = (string) ($this->option('token') ?: config('sync.device_token'));

        if ($url === '' || $token === '') {
            $this->error('Server address or token missing. Set DANTE_SYNC_URL and DANTE_DEVICE_TOKEN in .env.');

            return self::FAILURE;
        }

        $parts = parse_url($url);
        $local = in_array($parts['host'] ?? '', ['localhost', '127.0.0.1'], true);
        if (($parts['scheme'] ?? '') !== 'https' && ! $local) {
            $this->error('The server address must start with https:// so the token is not sent in clear text.');

            return self::FAILURE;
        }

        try {
            $res = (new Client(['timeout' => 10, 'connect_timeout' => 6, 'http_errors' => false]))->post($url.'/sync/ping', [
                'headers' => ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'],
            ]);
        } catch (\Throwable $e) {
            $this->error('Could not reach the server: '.$e->getMessage());

            return self::FAILURE;
        }

        $body = json_decode((string) $res->getBody(), true);

        if ($res->getStatusCode() === 200 && ($body['ok'] ?? false)) {
            $this->info('Connected as "'.$body['device'].'" ('.$body['device_id'].'). Server time: '.$body['server_time']);

            return self::SUCCESS;
        }

        $this->error('Refused (HTTP '.$res->getStatusCode().'): '.($body['message'] ?? 'no message. The server may not be set to DANTE_ROLE=primary.'));

        return self::FAILURE;
    }
}
