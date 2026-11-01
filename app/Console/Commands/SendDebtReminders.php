<?php

namespace App\Console\Commands;

use App\Services\AfricasTalkingSms;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendDebtReminders extends Command
{
    protected $signature = 'debts:remind
        {--business= : Only this business id (default: all)}
        {--grace=0 : Days after the due date before a customer is reminded}
        {--every=7 : Minimum days between reminders to the same customer}
        {--min=1 : Minimum overdue balance in KSh}
        {--dry-run : Show what would be sent without sending}';

    protected $description = 'SMS customers their overdue credit balances via Africa\'s Talking';

    public function handle(AfricasTalkingSms $sms)
    {
        $today = Carbon::today();
        $grace = (int) $this->option('grace');
        $every = (int) $this->option('every');
        $min = (float) $this->option('min');
        $dry = (bool) $this->option('dry-run');

        if (! $dry && ! $sms->configured()) {
            $this->error('Africa\'s Talking is not configured. Set AT_USERNAME and AT_API_KEY in .env, then run php artisan optimize:clear.');

            return self::FAILURE;
        }

        $rows = DB::table('transactions as t')
            ->join('contacts as c', 'c.id', '=', 't.contact_id')
            ->leftJoin('business as b', 'b.id', '=', 't.business_id')
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->whereIn('t.payment_status', ['due', 'partial'])
            ->where('c.is_default', 0)
            ->whereNotNull('c.mobile')
            ->where('c.mobile', '<>', '')
            ->when($this->option('business'), fn ($q, $id) => $q->where('t.business_id', $id))
            ->select(
                't.business_id', 't.transaction_date', 't.pay_term_number', 't.pay_term_type', 't.final_total',
                'c.id as contact_id', 'c.name', 'c.mobile', 'b.name as business_name',
                DB::raw('(SELECT COALESCE(SUM(IF(tp.is_return = 1, -1 * tp.amount, tp.amount)), 0)
                          FROM transaction_payments tp WHERE tp.transaction_id = t.id) as paid')
            )
            ->get();

        $customers = [];
        foreach ($rows as $r) {
            $balance = round($r->final_total - $r->paid, 2);
            if ($balance <= 0) {
                continue;
            }

            $due = Carbon::parse($r->transaction_date)->startOfDay();
            if (! empty($r->pay_term_number)) {
                $r->pay_term_type === 'months' ? $due->addMonths((int) $r->pay_term_number) : $due->addDays((int) $r->pay_term_number);
            }
            if (! $due->copy()->addDays($grace)->lt($today)) {
                continue; // not overdue yet
            }

            $c = &$customers[$r->contact_id];
            if (! $c) {
                $c = ['business_id' => $r->business_id, 'business' => $r->business_name, 'name' => $r->name,
                      'mobile' => $r->mobile, 'amount' => 0, 'oldest' => $due, 'count' => 0];
            }
            $c['amount'] += $balance;
            $c['count']++;
            if ($due->lt($c['oldest'])) {
                $c['oldest'] = $due;
            }
            unset($c);
        }

        $sent = $failed = $skipped = 0;

        foreach ($customers as $contactId => $c) {
            if ($c['amount'] < $min) {
                continue;
            }

            $phone = AfricasTalkingSms::normalizePhone($c['mobile']);
            if (! $phone) {
                $this->warn("Skipped {$c['name']}: invalid phone {$c['mobile']}");
                $skipped++;
                continue;
            }

            $recent = DB::table('debt_reminder_logs')
                ->where('contact_id', $contactId)
                ->where('status', 'sent')
                ->where('sent_at', '>=', now()->subDays($every))
                ->exists();
            if ($recent) {
                $skipped++;
                continue;
            }

            $first = explode(' ', trim($c['name']))[0];
            $pay = config('africastalking.pay_instructions');
            $message = sprintf(
                'Dear %s, your account at %s has KSh %s overdue (oldest due %s). Please pay %s. Thank you.',
                $first,
                $c['business'] ?: 'our shop',
                number_format($c['amount'], 2),
                $c['oldest']->format('d/m/Y'),
                $pay ?: 'at your earliest convenience'
            );

            if ($dry) {
                $this->line("[dry-run] {$phone}: {$message}");
                continue;
            }

            $result = $sms->send($phone, $message);

            DB::table('debt_reminder_logs')->insert([
                'business_id' => $c['business_id'],
                'contact_id' => $contactId,
                'phone' => $phone,
                'message' => $message,
                'status' => $result['ok'] ? 'sent' : 'failed',
                'response' => json_encode($result['response'] ?? ['error' => $result['error']]),
                'sent_at' => $result['ok'] ? now() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($result['ok']) {
                $sent++;
            } else {
                $failed++;
                $this->warn("Failed for {$c['name']}: {$result['error']}");
            }
        }

        $this->info("Done. Sent: {$sent}, failed: {$failed}, skipped: {$skipped}.");

        return self::SUCCESS;
    }
}
