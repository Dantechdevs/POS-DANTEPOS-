<?php

namespace App\Services;

use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PriceGuard
{
    /** Returns an error message if the sale must be blocked, otherwise null. */
    public static function violation(Request $request, $business_id): ?string
    {
        if ($request->input('status') !== 'final') {
            return null;
        }

        $products = (array) $request->input('products', []);
        if (empty($products)) {
            return null;
        }

        $util = app(Util::class);
        $vids = array_values(array_filter(array_map(fn ($p) => (int) ($p['variation_id'] ?? 0), $products)));
        if (empty($vids)) {
            return null;
        }

        $costs = DB::table('variations')->whereIn('id', $vids)->pluck('default_purchase_price', 'id');
        $names = DB::table('variations as v')->join('products as p', 'p.id', '=', 'v.product_id')
            ->whereIn('v.id', $vids)->pluck('p.name', 'v.id');

        $bad = [];
        foreach ($products as $p) {
            $vid = (int) ($p['variation_id'] ?? 0);
            $cost = (float) ($costs[$vid] ?? 0);
            $qty = (float) $util->num_uf($p['quantity'] ?? 0);
            if ($vid === 0 || $cost <= 0 || $qty <= 0) {
                continue;
            }

            // Price after line discount, before tax.
            if (isset($p['unit_price_inc_tax']) && $p['unit_price_inc_tax'] !== '') {
                $net = (float) $util->num_uf($p['unit_price_inc_tax']) - (float) $util->num_uf($p['item_tax'] ?? 0);
            } else {
                $net = (float) $util->num_uf($p['unit_price'] ?? 0);
            }

            if ($net + 0.01 < $cost) {
                $bad[] = ['name' => $names[$vid] ?? ('#'.$vid), 'net' => round($net, 2), 'cost' => round($cost, 2), 'qty' => $qty];
            }
        }

        if (empty($bad)) {
            return null;
        }

        $canOverride = auth()->check() && auth()->user()->can('price.below_cost');

        Log::warning('Below-cost sale', [
            'user_id' => auth()->id(),
            'mode' => config('pricing.guard_mode'),
            'override_allowed' => $canOverride,
            'lines' => $bad,
        ]);

        if (config('pricing.guard_mode') !== 'block' || $canOverride) {
            return null;
        }

        $list = array_map(fn ($b) => $b['name'].' (price '.number_format($b['net'], 2).', cost '.number_format($b['cost'], 2).')', array_slice($bad, 0, 5));

        return 'Selling below cost is not allowed: '.implode('; ', $list).'. Raise the price or ask a manager.';
    }
}
