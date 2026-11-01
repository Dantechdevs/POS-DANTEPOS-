<?php

namespace App\Console\Commands;

use App\Services\EtimsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SubmitEtimsInvoices extends Command
{
    protected $signature = 'etims:submit {--limit=20 : Max invoices per run} {--dry-run : Print payloads, send nothing}';

    protected $description = 'Submit queued sales to KRA eTIMS';

    public function handle(EtimsService $etims)
    {
        $dry = (bool) $this->option('dry-run');

        if (! $dry) {
            if (! config('etims.enabled')) {
                $this->error('eTIMS is disabled. Set ETIMS_ENABLED=true in .env first.');

                return self::FAILURE;
            }
            if (! $etims->configured()) {
                $this->error('eTIMS credentials missing. Set ETIMS_BASE_URL, ETIMS_TIN, ETIMS_BRANCH_ID and ETIMS_CMC_KEY in .env.');

                return self::FAILURE;
            }
        }

        $rows = DB::table('etims_invoices')
            ->whereIn('status', ['pending', 'failed'])
            ->where('attempts', '<', config('etims.max_attempts'))
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get();

        $ok = $bad = 0;
        foreach ($rows as $row) {
            try {
                $payload = $etims->buildPayload($row->transaction_id, $row->id);
            } catch (\Throwable $e) {
                $this->warn("#{$row->id} payload error: ".$e->getMessage());
                if (! $dry) {
                    DB::table('etims_invoices')->where('id', $row->id)->update([
                        'status' => 'failed', 'attempts' => $row->attempts + 1,
                        'last_error' => $e->getMessage(), 'updated_at' => now(),
                    ]);
                }
                $bad++;
                continue;
            }

            if ($dry) {
                $this->line("--- invoice #{$row->id} (transaction {$row->transaction_id}) ---");
                $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                continue;
            }

            $res = $etims->submit($payload);
            $data = $res['response']['data'] ?? [];

            DB::table('etims_invoices')->where('id', $row->id)->update([
                'status' => $res['ok'] ? 'submitted' : 'failed',
                'attempts' => $row->attempts + 1,
                'last_error' => $res['error'],
                'payload' => json_encode($payload),
                'response' => json_encode($res['response']),
                'cu_receipt_no' => $res['ok'] ? ($data['rcptNo'] ?? null) : null,
                'cu_signature' => $res['ok'] ? ($data['rcptSign'] ?? null) : null,
                'cu_datetime' => $res['ok'] ? ($data['sdcDateTime'] ?? null) : null,
                'submitted_at' => $res['ok'] ? now() : null,
                'updated_at' => now(),
            ]);

            if ($res['ok']) {
                $ok++;
            } else {
                $bad++;
                $this->warn("#{$row->id} failed: {$res['error']}");
            }
        }

        $this->info($dry ? 'Dry run complete.' : "Done. Submitted: {$ok}, failed: {$bad}.");

        return self::SUCCESS;
    }
}
