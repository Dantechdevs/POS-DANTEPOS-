<?php

namespace App\Console\Commands;

use App\Services\AfricasTalkingSms;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SendDailySummary extends Command
{
    protected $signature = 'reports:daily-summary
        {--date= : Day to summarise, YYYY-MM-DD (default: today)}
        {--business= : Only this business id}
        {--to= : Phone numbers, comma separated (default: OWNER_PHONES)}
        {--dry-run : Print the message, send nothing}';

    protected $description = "SMS the owner a summary of the day's sales, collections, debts and alerts";

    private const PAID_SQL = '(SELECT COALESCE(SUM(IF(tp.is_return = 1, -1 * tp.amount, tp.amount)), 0)
                               FROM transaction_payments tp WHERE tp.transaction_id = t.id)';

    public function handle(AfricasTalkingSms $sms)
    {
        $dry = (bool) $this->option('dry-run');
        $phones = array_values(array_filter(array_map('trim', explode(',', (string) ($this->option('to') ?: config('summary.phones'))))));

        if (! $dry && empty($phones)) {
            $this->error('No phone numbers. Set OWNER_PHONES in .env (comma separated) or pass --to=07XXXXXXXX.');

            return self::FAILURE;
        }
        if (! $dry && ! $sms->configured()) {
            $this->error('Africa\'s Talking is not configured (AT_USERNAME / AT_API_KEY).');

            return self::FAILURE;
        }

        $businesses = DB::table('business')
            ->when($this->option('business'), fn ($q, $id) => $q->where('id', $id))
            ->get(['id', 'name', 'time_zone']);

        foreach ($businesses as $b) {
            $tz = $b->time_zone ?: config('app.timezone');
            $day = $this->option('date') ? Carbon::parse($this->option('date'), $tz) : Carbon::now($tz);
            $date = $day->toDateString();
            $guard = 'daily_summary_'.$b->id.'_'.$date;

            if (! $dry && ! $this->option('date') && Cache::has($guard)) {
                $this->line("Business {$b->id}: already sent for {$date}. Skipped.");
                continue;
            }

            $message = mb_substr($this->build($b, $day, $date.' 00:00:00', $date.' 23:59:59'), 0, 450);

            if ($dry) {
                $this->line("[dry-run] ({$b->name}) ".$message);
                continue;
            }

            $sent = 0;
            foreach ($phones as $phone) {
                $r = $sms->send($phone, $message);
                if ($r['ok']) {
                    $sent++;
                } else {
                    $this->warn("Failed to {$phone}: {$r['error']}");
                }
            }
            if ($sent > 0) {
                Cache::put($guard, 1, now()->addDays(2));
            }
            $this->info("Business {$b->id}: sent to {$sent} of ".count($phones).' number(s).');
        }

        return self::SUCCESS;
    }

    private function build($b, Carbon $day, string $from, string $to): string
    {
        $sells = fn () => DB::table('transactions')->where('business_id', $b->id)
            ->where('type', 'sell')->where('status', 'final')->whereBetween('transaction_date', [$from, $to]);

        $salesTotal = (float) $sells()->sum('final_total');
        $salesCount = $sells()->count();

        $returns = (float) DB::table('transactions')->where('business_id', $b->id)
            ->where('type', 'sell_return')->whereBetween('transaction_date', [$from, $to])->sum('final_total');

        $byMethod = DB::table('transaction_payments')
            ->where('business_id', $b->id)->whereNotNull('transaction_id')->where('is_return', 0)
            ->whereBetween('paid_on', [$from, $to])
            ->groupBy('method')->selectRaw('method, SUM(amount) as total')->pluck('total', 'method');

        $labels = ['cash' => 'Cash', 'mpesa' => 'M-Pesa', 'card' => 'Card', 'bank_transfer' => 'Bank',
                   'cheque' => 'Cheque', 'gift_card' => 'Gift card'];
        $paid = [];
        $other = 0;
        foreach ($byMethod as $m => $amt) {
            if ((float) $amt <= 0) {
                continue;
            }
            if (isset($labels[$m])) {
                $paid[] = $labels[$m].' '.number_format($amt, 0);
            } else {
                $other += $amt;
            }
        }
        if ($other > 0) {
            $paid[] = 'Other '.number_format($other, 0);
        }

        $outstanding = fn () => DB::table('transactions as t')->where('t.business_id', $b->id)
            ->where('t.type', 'sell')->where('t.status', 'final')->whereIn('t.payment_status', ['due', 'partial']);

        $creditToday = (float) $outstanding()->whereBetween('t.transaction_date', [$from, $to])
            ->selectRaw('COALESCE(SUM(t.final_total - '.self::PAID_SQL.'), 0) as v')->value('v');
        $owed = (float) $outstanding()
            ->selectRaw('COALESCE(SUM(t.final_total - '.self::PAID_SQL.'), 0) as v')->value('v');

        $lowStock = DB::table('variation_location_details as vld')
            ->join('products as p', 'p.id', '=', 'vld.product_id')
            ->where('p.business_id', $b->id)->where('p.enable_stock', 1)->where('p.is_inactive', 0)
            ->where('p.alert_quantity', '>', 0)
            ->whereColumn('vld.qty_available', '<=', 'p.alert_quantity')
            ->distinct()->count('p.id');

        $mpesaCheck = DB::table('mpesa_transactions as m')
            ->where(function ($q) use ($b) {
                $q->where('m.business_id', $b->id)->orWhereNull('m.business_id');
            })
            ->whereBetween('m.created_at', [$from, $to])
            ->where(function ($q) {
                $q->where('m.status', 'review')->orWhere(function ($q2) {
                    $q2->where('m.status', 'success')->where('m.created_at', '<', now()->subMinutes(15))
                       ->whereNotExists(function ($q3) {
                           $q3->select(DB::raw(1))->from('transaction_payments as tp')
                              ->whereColumn('tp.mpesa_receipt_number', 'm.mpesa_receipt_number');
                       });
                });
            })->count();

        $parts = [($b->name ?: 'Shop').' '.$day->format('d/m').': Sales KSh '.number_format($salesTotal, 0).' ('.$salesCount.')'];
        if ($returns > 0) {
            $parts[] = 'Returns KSh '.number_format($returns, 0);
        }
        if ($paid) {
            $parts[] = 'Paid in: '.implode(', ', $paid);
        }
        if ($creditToday > 0) {
            $parts[] = 'Credit unpaid today KSh '.number_format($creditToday, 0);
        }
        $parts[] = 'Total owed KSh '.number_format($owed, 0);
        if ($lowStock > 0) {
            $parts[] = 'Low stock: '.$lowStock;
        }
        if ($mpesaCheck > 0) {
            $parts[] = 'M-Pesa to check: '.$mpesaCheck;
        }

        return implode('. ', $parts).'.';
    }
}
