<?php

namespace App\Console\Commands;

use App\Services\AfricasTalkingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LowStockAlert extends Command
{
    protected $signature = 'report:low-stock {--dry-run : Show the full list and the SMS without sending} {--to= : Override recipients (comma-separated)}';

    protected $description = 'SMS the owner the products that are out of stock or at/below their alert quantity';

    private const MAX_ITEMS_IN_SMS = 6;

    public function handle(AfricasTalkingService $sms)
    {
        $to = $this->option('to') ?: config('services.daily_summary.to');
        $recipients = array_values(array_filter(array_map('trim', explode(',', (string) $to))));

        if (empty($recipients) && ! $this->option('dry-run')) {
            $this->error('Set DAILY_SUMMARY_TO in .env (or pass --to=), then run php artisan config:clear.');

            return 1;
        }

        foreach (DB::table('business')->get() as $business) {
            $items = $this->lowStock($business->id);

            if (empty($items)) {
                $this->line($business->name.': nothing is below its alert quantity.');
                continue;
            }

            $message = $this->buildMessage($business, $items);

            if ($this->option('dry-run')) {
                $this->info($business->name.': '.count($items).' low-stock lines');
                $this->table(
                    ['Product', 'Location', 'Stock', 'Alert at', 'Status'],
                    array_map(fn ($i) => [$i['name'], $i['location'], $this->qty($i['stock']), $this->qty($i['alert']), $i['out'] ? 'OUT' : 'low'], $items)
                );
                $this->line("SMS preview:\n".$message."\n(".strlen($message).' characters)');
                continue;
            }

            try {
                $result = $sms->send($recipients, $message);
                $this->info($business->name.': '.$result['sent'].' of '.count($recipients).' accepted');
            } catch (\Throwable $e) {
                Log::error('Low stock SMS failed: '.$e->getMessage());
                $this->error($business->name.': '.$e->getMessage());
            }
        }

        return 0;
    }

    private function lowStock($businessId): array
    {
        $rows = DB::table('variation_location_details as vld')
            ->join('product_variations as pv', 'vld.product_variation_id', '=', 'pv.id')
            ->join('variations as v', 'vld.variation_id', '=', 'v.id')
            ->join('products as p', 'vld.product_id', '=', 'p.id')
            ->leftJoin('business_locations as l', 'vld.location_id', '=', 'l.id')
            ->where('p.business_id', $businessId)
            ->where('p.enable_stock', 1)
            ->where('p.is_inactive', 0)
            ->whereNull('v.deleted_at')
            ->whereNotNull('p.alert_quantity')
            ->whereRaw('vld.qty_available <= p.alert_quantity')
            ->select('p.name as product', 'p.type', 'v.name as variation', 'l.name as location', 'vld.qty_available as stock', 'p.alert_quantity as alert')
            ->get();

        $items = $rows->map(function ($r) {
            $stock = (float) $r->stock;
            $alert = (float) $r->alert;

            return [
                'name' => $r->type === 'variable' ? $r->product.' - '.$r->variation : $r->product,
                'location' => $r->location,
                'stock' => $stock,
                'alert' => $alert,
                'out' => $stock <= 0,
                'ratio' => $alert > 0 ? $stock / $alert : 0,
            ];
        })->all();

        // Out of stock first, then the lowest compared with its alert level.
        usort($items, fn ($a, $b) => [! $a['out'], $a['ratio']] <=> [! $b['out'], $b['ratio']]);

        return $items;
    }

    private function buildMessage($business, array $items): string
    {
        $tz = $business->time_zone ?: 'Africa/Nairobi';
        $out = count(array_filter($items, fn ($i) => $i['out']));
        $low = count($items) - $out;
        $multiLocation = count(array_unique(array_column($items, 'location'))) > 1;

        $lines = [
            'LOW STOCK '.substr($business->name, 0, 16).' '.now($tz)->format('d/m'),
            $out.' out, '.$low.' low',
        ];

        foreach (array_slice($items, 0, self::MAX_ITEMS_IN_SMS) as $i) {
            $name = substr($i['name'], 0, 24);
            $where = $multiLocation && $i['location'] ? ' @'.substr($i['location'], 0, 8) : '';
            $lines[] = ($i['out'] ? 'OUT ' : '').$name.$where.' '.$this->qty($i['stock']).'/'.$this->qty($i['alert']);
        }

        if (count($items) > self::MAX_ITEMS_IN_SMS) {
            $lines[] = '+'.(count($items) - self::MAX_ITEMS_IN_SMS).' more. See Reports > Stock';
        }

        // Plain ASCII keeps the SMS in the cheaper single-byte encoding.
        return preg_replace('/[^\x20-\x7E\n]/', '', implode("\n", $lines));
    }

    private function qty(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.') ?: '0';
    }
}
