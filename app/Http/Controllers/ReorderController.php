<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\Services\AfricasTalkingSms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReorderController extends Controller
{
    public function index(Request $request)
    {
        if (! auth()->user()->can('product.view')) {
            abort(403, 'Unauthorized action.');
        }

        $bid = $request->session()->get('user.business_id');
        $locationId = $request->input('location_id');

        $low = DB::table('variation_location_details as vld')
            ->join('products as p', 'p.id', '=', 'vld.product_id')
            ->join('variations as v', 'v.id', '=', 'vld.variation_id')
            ->join('business_locations as l', 'l.id', '=', 'vld.location_id')
            ->where('p.business_id', $bid)
            ->where('p.enable_stock', 1)
            ->where('p.is_inactive', 0)
            ->where('p.alert_quantity', '>', 0)
            ->whereColumn('vld.qty_available', '<=', 'p.alert_quantity')
            ->whereNull('v.deleted_at')
            ->when($locationId, fn ($q) => $q->where('vld.location_id', $locationId))
            ->orderBy('p.name')
            ->get(['vld.variation_id', 'vld.location_id', 'vld.qty_available', 'p.name', 'p.type',
                   'p.alert_quantity', 'v.name as vname', 'v.sub_sku', 'l.name as location']);

        $vids = $low->pluck('variation_id')->unique()->all();
        $sold = [];
        $last = [];

        if (! empty($vids)) {
            $sold = DB::table('transaction_sell_lines as sl')
                ->join('transactions as t', 't.id', '=', 'sl.transaction_id')
                ->where('t.business_id', $bid)->where('t.type', 'sell')->where('t.status', 'final')
                ->where('t.transaction_date', '>=', now()->subDays(30))
                ->whereIn('sl.variation_id', $vids)
                ->groupBy('sl.variation_id')
                ->selectRaw('sl.variation_id, SUM(sl.quantity - sl.quantity_returned) as q')
                ->pluck('q', 'variation_id')->all();

            $purchases = DB::table('purchase_lines as pl')
                ->join('transactions as t', 't.id', '=', 'pl.transaction_id')
                ->leftJoin('contacts as c', 'c.id', '=', 't.contact_id')
                ->where('t.business_id', $bid)->where('t.type', 'purchase')
                ->whereIn('pl.variation_id', $vids)
                ->orderByDesc('t.transaction_date')->orderByDesc('t.id')
                ->get(['pl.variation_id', 'pl.purchase_price_inc_tax', 't.contact_id', 'c.name', 'c.supplier_business_name', 'c.mobile']);

            foreach ($purchases as $r) {
                if (! isset($last[$r->variation_id])) {
                    $last[$r->variation_id] = $r;
                }
            }
        }

        $groups = [];
        $flat = [];
        $business = $request->session()->get('business.name') ?: 'our shop';

        foreach ($low as $r) {
            $s30 = (float) ($sold[$r->variation_id] ?? 0);
            $target = max((float) $r->alert_quantity * 2, ceil($s30));
            $suggest = (int) max(0, ceil($target - (float) $r->qty_available));
            $lp = $last[$r->variation_id] ?? null;
            $cost = $lp ? (float) $lp->purchase_price_inc_tax : 0;
            $name = $r->type === 'variable' ? $r->name.' - '.$r->vname : $r->name;

            $item = [
                'name' => $name, 'sku' => $r->sub_sku, 'location' => $r->location,
                'qty' => (float) $r->qty_available, 'alert' => (float) $r->alert_quantity,
                'sold30' => $s30, 'suggest' => $suggest, 'cost' => $cost, 'line_cost' => $suggest * $cost,
            ];

            $key = $lp && $lp->contact_id ? $lp->contact_id : 0;
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'supplier' => $lp && $lp->contact_id ? ($lp->supplier_business_name ?: $lp->name) : 'No purchase history',
                    'mobile' => $lp->mobile ?? null, 'items' => [], 'cost' => 0, 'wa' => null,
                ];
            }
            $groups[$key]['items'][] = $item;
            $groups[$key]['cost'] += $item['line_cost'];
            $flat[] = array_merge($item, ['supplier' => $groups[$key]['supplier']]);
        }

        foreach ($groups as $k => &$g) {
            $phone = AfricasTalkingSms::normalizePhone($g['mobile']);
            if ($k !== 0 && $phone) {
                $lines = [];
                foreach ($g['items'] as $it) {
                    if ($it['suggest'] > 0) {
                        $lines[] = '- '.$it['name'].' x '.$it['suggest'];
                    }
                }
                if ($lines) {
                    $first = explode(' ', trim($g['supplier']))[0];
                    $msg = 'Hello '.$first.', this is '.$business.". We would like to order:\n".implode("\n", $lines)."\n\nPlease confirm availability, price and delivery. Thank you.";
                    $g['wa'] = 'https://wa.me/'.ltrim($phone, '+').'?text='.rawurlencode($msg);
                }
            }
        }
        unset($g);

        uasort($groups, fn ($a, $b) => $b['cost'] <=> $a['cost']);

        if ($request->input('export') === 'csv') {
            return response()->streamDownload(function () use ($flat) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Supplier', 'Product', 'SKU', 'Location', 'In stock', 'Alert level', 'Sold last 30 days', 'Suggested order', 'Last cost', 'Est. cost']);
                foreach ($flat as $f) {
                    fputcsv($out, [$f['supplier'], $f['name'], $f['sku'], $f['location'], $f['qty'], $f['alert'], $f['sold30'], $f['suggest'], $f['cost'], $f['line_cost']]);
                }
                fclose($out);
            }, 'reorder-list-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
        }

        $locations = BusinessLocation::forDropdown($bid, true);
        $totalCost = array_sum(array_column($groups, 'cost'));
        $itemCount = count($flat);

        return view('report.reorder', compact('groups', 'locations', 'locationId', 'totalCost', 'itemCount'));
    }
}
