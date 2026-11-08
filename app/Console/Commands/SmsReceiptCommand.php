<?php

namespace App\Console\Commands;

use App\Services\SmsReceiptService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SmsReceiptCommand extends Command
{
    protected $signature = 'receipt:sms {invoice? : Invoice number or sale id} {--dry-run : Show the message without sending} {--force : Ignore the 24-hour, already-sent and credit-only checks} {--retry-failed : Resend receipts that failed in the last 3 days}';

    protected $description = 'Send, preview or retry the SMS receipt for a sale';

    public function handle(SmsReceiptService $service)
    {
        if ($this->option('retry-failed')) {
            $ids = DB::table('sms_receipts')->where('status', 'failed')
                ->where('created_at', '>=', now()->subDays(3))->pluck('transaction_id');

            if ($ids->isEmpty()) {
                $this->info('No failed receipts in the last 3 days.');

                return 0;
            }

            foreach ($ids as $id) {
                $r = $service->send((int) $id, true);
                $this->line('Sale #'.$id.': '.$r['reason']);
            }

            return 0;
        }

        $arg = (string) $this->argument('invoice');

        if ($arg === '') {
            $this->error('Give an invoice number (for example 0014), or use --retry-failed.');

            return 1;
        }

        $t = DB::table('transactions')->where('type', 'sell')->where('invoice_no', $arg)->orderByDesc('id')->first();

        if (! $t && ctype_digit($arg)) {
            $t = DB::table('transactions')->where('type', 'sell')->where('id', $arg)->first();
        }

        if (! $t) {
            $this->error('No sale found for "'.$arg.'".');

            return 1;
        }

        if ($this->option('dry-run')) {
            $p = $service->prepare($t->id, (bool) $this->option('force'));

            if (! $p['ok']) {
                $this->warn('Would NOT send: '.$p['reason']);

                return 0;
            }

            $this->line('To: +'.$p['phone']);
            $this->line($p['message']);
            $this->line('('.strlen($p['message']).' characters)');

            return 0;
        }

        $r = $service->send($t->id, (bool) $this->option('force'));
        $r['ok'] ? $this->info($r['reason']) : $this->warn($r['reason']);

        return $r['ok'] ? 0 : 1;
    }
}
