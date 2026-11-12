<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncPush extends Command
{
    protected $signature = 'sync:push {--url= : Server address (default DANTE_SYNC_URL)} {--token= : Device token (default DANTE_DEVICE_TOKEN)} {--limit=25 : Records per run}';

    protected $description = 'Upload queued sales from this device to the primary server';

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
            $this->error('The server address must start with https:// so sales are not sent in clear text.');

            return self::FAILURE;
        }

        $rows = DB::table('sync_outbox')
            ->whereIn('status', ['pending', 'failed'])
            ->where('attempts', '<', 50)
            ->orderBy('id')
            ->limit(max(1, min(50, (int) $this->option('limit'))))
            ->get();

        if ($rows->isEmpty()) {
            $this->info('Nothing to upload.');

            return self::SUCCESS;
        }

        $items = $rows->map(fn ($r) => ['uuid' => $r->uuid, 'entity' => $r->entity, 'payload' => $r->payload])->all();

        try {
            $res = (new Client(['timeout' => 30, 'connect_timeout' => 8, 'http_errors' => false]))->post($url.'/sync/push', [
                'headers' => ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'],
                'json' => ['items' => $items],
            ]);
        } catch (\Throwable $e) {
            $this->fail($rows->pluck('id')->all(), 'Could not reach the server: '.$e->getMessage());
            $this->error('Could not reach the server (still queued): '.$e->getMessage());

            return self::FAILURE;
        }

        $body = json_decode((string) $res->getBody(), true);
        if ($res->getStatusCode() !== 200 || ! ($body['ok'] ?? false)) {
            $msg = 'Refused (HTTP '.$res->getStatusCode().'): '.($body['message'] ?? 'no message');
            $this->fail($rows->pluck('id')->all(), $msg);
            $this->error($msg);

            return self::FAILURE;
        }

        $new = $dup = $bad = 0;
        foreach ($rows as $row) {
            $r = $body['results'][$row->uuid] ?? 'no answer';
            if ($r === 'received' || $r === 'duplicate') {
                DB::table('sync_outbox')->where('id', $row->id)->update([
                    'status' => 'sent', 'sent_at' => now(), 'last_error' => null, 'updated_at' => now(),
                ]);
                $r === 'received' ? $new++ : $dup++;
            } else {
                $this->fail([$row->id], $r);
                $this->warn("{$row->uuid}: {$r}");
                $bad++;
            }
        }

        $this->info("Uploaded {$new} new, {$dup} already on the server, {$bad} failed.");

        return $bad ? self::FAILURE : self::SUCCESS;
    }

    private function fail(array $ids, string $error): void
    {
        DB::table('sync_outbox')->whereIn('id', $ids)->update([
            'status' => 'failed',
            'attempts' => DB::raw('attempts + 1'),
            'last_error' => mb_substr($error, 0, 500),
            'updated_at' => now(),
        ]);
    }
}
