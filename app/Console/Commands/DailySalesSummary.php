<?php

namespace App\Console\Commands;

use App\Services\AfricasTalkingService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DailySalesSummary extends Command
{
    protected $signature = 'report:daily-summary {--date= : Day to report as YYYY-MM-DD (default: today)} {--dry-run : Print the message instead of sending it}';

    protected $description = 'SMS the owner a summary of the day\'s sales, collections, credit and expenses';

    public function handle(AfricasTalkingService $sms)
    {
        $recipients = array_values(array_filter(array_map('trim', explode(',', (string) config('services.daily_summary.to')))));

        if (empty($recipients) && ! $this->option('dry-run')) {
            $this->error('Set DAILY_SUMMARY_TO in .env (comma-separated phone numbers), then run php artisan config:clear.');

            return 1;
        }

        foreach (DB::table('business')->get() as $business) {
            $message = $this->buildMessage($business);

            if ($this->option('dry-run')) {
                $this->line($message);
                $this->line('('.strlen($message).' characters)');
                continue;
            }

            try {
                $result = $sms->send($recipients, $message);
                $this->info($business->name.': '.$result['sent'].' of '.count($recipients).' accepted');
            } catch (\Throwable $e) {
                Log::error('Daily summary SMS failed: '.$e->getMessage());
                $this->error($business->name.': '.$e->getMessage());
            }
        }

        return 0;
    }

    private function buildMessage($business): string
    {
        // POS dates are stored in the business timezone.
        $tz = $business->time_zone ?: 'Africa/Nairobi';
        date_default_timezone_set($tz);
        config(['app.timezone' => $tz]);

        $day = $this->option('date') ? Carbon::parse($this->option('date'), $tz) : Carbon::now($tz);
        $start = $day->copy()->startOfDay()->toDateTimeString();
        $end = $day->copy()->endOfDay()->toDateTimeString();
        $bid = $business->id;
        $k = fn ($n) => number_format((float) $n, 0);

        $sales = DB::table('transactions')
            ->where('business_id', $bid)->where('type', 'sell')->where('status', 'final')->where('is_suspend', 0)
            ->whereBetween('transaction_date', [$start, $end])
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(final_total), 0) as total')
            ->first();

        $returns = DB::table('transactions')
            ->where('business_id', $bid)->where('type', 'sell_return')
            ->whereBetween('transaction_date', [$start, $end])
            ->sum('final_total');

        $expenses = DB::table('transactions')
            ->where('business_id', $bid)->where('type', 'expense')
            ->whereBetween('transaction_date', [$start, $end])
            ->sum('final_total');

        // Money received today, including customers paying off old debts.
        $payments = DB::table('transaction_payments')
            ->where('business_id', $bid)
            ->where('is_return', 0)
            ->where('method', '!=', 'advance')
            ->whereBetween('paid_on', [$start, $end]);

        if (Schema::hasColumn('transaction_payments', 'parent_id')) {
            $payments->whereNull('parent_id'); // avoid counting split child rows twice
        }

        $collected = $payments->select('method', DB::raw('SUM(amount) as total'))->groupBy('method')->pluck('total', 'method');

        $labels = ['cash' => 'Cash', 'mpesa' => 'M-Pesa', 'card' => 'Card', 'bank_transfer' => 'Bank', 'cheque' => 'Cheque'];
        $grouped = [];
        foreach ($collected as $method => $total) {
            $label = $labels[$method] ?? 'Other';
            $grouped[$label] = ($grouped[$label] ?? 0) + $total;
        }
        arsort($grouped);
        $collectedText = $grouped
            ? implode(', ', array_map(fn ($l, $t) => $l.' '.$k($t), array_keys($grouped), $grouped))
            : 'none';

        $creditToday = $this->unpaid($bid, $start, $end);
        $owedAll = $this->unpaid($bid);

        $mpesaCheck = null;
        try {
            $cutoff = now()->subMinutes(15);

            $base = DB::table('mpesa_transactions as m')
                ->where(function ($q) use ($bid) {
                    $q->where('m.business_id', $bid)->orWhereNull('m.business_id');
                })
                ->whereBetween('m.created_at', [$start, $end]);

            $needsReview = (clone $base)->where('m.status', 'review')->count();
            $stuck = (clone $base)->where('m.status', 'pending')->where('m.created_at', '<', $cutoff)->count();
            $unmatched = (clone $base)->where('m.status', 'success')->where('m.created_at', '<', $cutoff)
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))->from('transaction_payments as tp')
                        ->whereColumn('tp.mpesa_receipt_number', 'm.mpesa_receipt_number');
                })->count();

            $mpesaCheck = $needsReview + $stuck + $unmatched;
        } catch (\Throwable $e) {
            // M-Pesa tables or columns differ here: leave this line out.
        }

        $lines = [
            substr($business->name, 0, 18).' '.$day->format('d/m'),
            'Sales KSh '.$k($sales->total).' ('.$sales->n.' sales)',
            'Collected: '.$collectedText,
            'Credit given KSh '.$k($creditToday),
            'Owed by customers KSh '.$k($owedAll),
            'Expenses KSh '.$k($expenses),
        ];

        if ($returns > 0) {
            $lines[] = 'Returns KSh '.$k($returns);
        }
        if ($mpesaCheck) {
            $lines[] = 'M-Pesa to check: '.$mpesaCheck;
        }

        // Plain ASCII keeps the SMS in the cheaper single-byte encoding.
        return preg_replace('/[^\x20-\x7E\n]/', '', implode("\n", $lines));
    }

    private function unpaid($bid, $start = null, $end = null): float
    {
        $q = DB::table('transactions as t')
            ->where('t.business_id', $bid)
            ->where('t.type', 'sell')->where('t.status', 'final')->where('t.is_suspend', 0)
            ->where('t.payment_status', '!=', 'paid');

        if ($start && $end) {
            $q->whereBetween('t.transaction_date', [$start, $end]);
        }

        return (float) $q->selectRaw('COALESCE(SUM(t.final_total - COALESCE((SELECT SUM(tp.amount) FROM transaction_payments tp WHERE tp.transaction_id = t.id AND tp.is_return = 0), 0)), 0) as owed')
            ->value('owed');
    }
}
