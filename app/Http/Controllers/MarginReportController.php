<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarginReportController extends Controller
{
    public function index(Request $request)
    {
        if (! auth()->user()->can('profit_loss_report.view')) {
            abort(403, 'Unauthorized action.');
        }

        $bid = $request->session()->get('user.business_id');
        $locationId = $request->input('location_id');
        $from = Carbon::parse($request->input('from', now()->subDays(30)->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('to', now()->toDateString()))->endOfDay();

        $base = DB::table('transaction_sell_lines as sl')
            ->join('transactions as t', 't.id', '=', 'sl.transaction_id')
            ->join('variations as v', 'v.id', '=', 'sl.variation_id')
            ->join('products as p', 'p.id', '=', 'sl.product_id')
            ->where('t.business_id', $bid)
            ->where('t.type', 'sell')->where('t.status', 'final')
            ->whereBetween('t.transaction_date', [$from, $to])
            ->when($locationId, fn ($q) => $q->where('t.location_id', $locationId));

        $net = '(sl.quantity - sl.quantity_returned)';

        $rows = (clone $base)
            ->whereRaw($net.' > 0')
            ->groupBy('sl.variation_id', 'p.name', 'p.type', 'v.name', 'v.sub_sku', 'v.default_purchase_price')
            ->selectRaw("p.name, p.type, v.name as vname, v.sub_sku, v.default_purchase_price as unit_cost,
                SUM($net) as qty, SUM($net * sl.unit_price) as revenue, SUM($net * v.default_purchase_price) as cost")
            ->get();

        $products = [];
        $sum = ['revenue' => 0, 'cost' => 0];
        foreach ($rows as $r) {
            $profit = (float) $r->revenue - (float) $r->cost;
            $products[] = [
                'name' => $r->type === 'variable' ? $r->name.' - '.$r->vname : $r->name,
                'sku' => $r->sub_sku, 'qty' => (float) $r->qty,
                'revenue' => (float) $r->revenue, 'cost' => (float) $r->cost, 'profit' => $profit,
                'margin' => (float) $r->revenue > 0 ? $profit / (float) $r->revenue * 100 : null,
                'no_cost' => (float) $r->unit_cost <= 0,
            ];
            $sum['revenue'] += (float) $r->revenue;
            $sum['cost'] += (float) $r->cost;
        }
        usort($products, fn ($a, $b) => $b['profit'] <=> $a['profit']);

        $sum['profit'] = $sum['revenue'] - $sum['cost'];
        $sum['margin'] = $sum['revenue'] > 0 ? $sum['profit'] / $sum['revenue'] * 100 : 0;

        $below = (clone $base)
            ->whereRaw($net.' > 0')
            ->where('v.default_purchase_price', '>', 0)
            ->whereColumn('sl.unit_price', '<', 'v.default_purchase_price')
            ->orderByDesc('t.transaction_date')
            ->limit(200)
            ->get(['t.invoice_no', 't.transaction_date', 'p.name', 'sl.quantity', 'sl.unit_price', 'v.default_purchase_price as cost'])
            ->map(fn ($r) => [
                'invoice' => $r->invoice_no, 'date' => $r->transaction_date, 'name' => $r->name,
                'qty' => (float) $r->quantity, 'price' => (float) $r->unit_price, 'cost' => (float) $r->cost,
                'loss' => ((float) $r->cost - (float) $r->unit_price) * (float) $r->quantity,
            ])->all();

        if ($request->input('export') === 'csv') {
            return response()->streamDownload(function () use ($products) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Product', 'SKU', 'Qty sold', 'Revenue (ex VAT)', 'Cost', 'Profit', 'Margin %']);
                foreach ($products as $p) {
                    fputcsv($out, [$p['name'], $p['sku'], $p['qty'], round($p['revenue'], 2), round($p['cost'], 2), round($p['profit'], 2), $p['margin'] === null ? '' : round($p['margin'], 1)]);
                }
                fclose($out);
            }, 'margins-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
        }

        $locations = BusinessLocation::forDropdown($bid, true);

        return view('report.margins', compact('products', 'sum', 'below', 'locations', 'locationId', 'from', 'to'));
    }
}
