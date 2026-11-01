<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetAlertLevels extends Command
{
    protected $signature = 'stock:set-alerts
        {--days=30 : Days of sales history to measure}
        {--cover=14 : Days of stock the alert level should cover}
        {--min=2 : Smallest alert level to set}
        {--all : Also overwrite alert levels that are already set}
        {--business= : Only this business id}
        {--apply : Save the changes (without this it only previews)}';

    protected $description = 'Set reorder alert levels from each product\'s recent sales rate';

    public function handle()
    {
        $days = max(1, (int) $this->option('days'));
        $cover = max(1, (int) $this->option('cover'));
        $min = max(1, (int) $this->option('min'));
        $apply = (bool) $this->option('apply');

        $sales = DB::table('transaction_sell_lines as sl')
            ->join('transactions as t', 't.id', '=', 'sl.transaction_id')
            ->join('products as p', 'p.id', '=', 'sl.product_id')
            ->where('t.type', 'sell')->where('t.status', 'final')
            ->where('t.transaction_date', '>=', now()->subDays($days))
            ->where('p.enable_stock', 1)->where('p.is_inactive', 0)
            ->when($this->option('business'), fn ($q, $id) => $q->where('p.business_id', $id))
            ->when(! $this->option('all'), function ($q) {
                $q->where(function ($w) {
                    $w->whereNull('p.alert_quantity')->orWhere('p.alert_quantity', '<=', 0);
                });
            })
            ->groupBy('sl.product_id', 'p.name', 'p.alert_quantity')
            ->selectRaw('sl.product_id, p.name, p.alert_quantity, SUM(sl.quantity - sl.quantity_returned) as sold')
            ->get();

        $changes = [];
        foreach ($sales as $r) {
            if ((float) $r->sold <= 0) {
                continue;
            }
            $level = max($min, (int) ceil(((float) $r->sold / $days) * $cover));
            if ((float) $r->alert_quantity === (float) $level) {
                continue;
            }
            $changes[] = ['id' => $r->product_id, 'name' => $r->name, 'old' => $r->alert_quantity, 'new' => $level, 'sold' => (float) $r->sold];
        }

        if (empty($changes)) {
            $this->info('Nothing to change.');

            return self::SUCCESS;
        }

        $this->table(['Product', 'Sold ('.$days.'d)', 'Old alert', 'New alert'], array_map(fn ($c) => [
            mb_substr($c['name'], 0, 40), $c['sold'], $c['old'] ?: '-', $c['new'],
        ], array_slice($changes, 0, 40)));

        if (count($changes) > 40) {
            $this->line('...and '.(count($changes) - 40).' more.');
        }

        if (! $apply) {
            $this->warn(count($changes).' product(s) would change. Preview only. Add --apply to save.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($changes) {
            foreach ($changes as $c) {
                DB::table('products')->where('id', $c['id'])->update(['alert_quantity' => $c['new'], 'updated_at' => now()]);
            }
        });

        $this->info(count($changes).' alert level(s) saved.');

        return self::SUCCESS;
    }
}
