<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpiryWatchController extends Controller
{
    public function index(Request $request)
    {
        if (! auth()->user()->can('product.view')) {
            abort(403, 'Unauthorized action.');
        }

        $bid = $request->session()->get('user.business_id');
        $locationId = $request->input('location_id');
        $horizon = in_array((int) $request->input('days', 90), [30, 60, 90, 180], true) ? (int) $request->input('days', 90) : 90;
        $today = Carbon::today();

        $remaining = '(pl.quantity - pl.quantity_sold - pl.quantity_adjusted - pl.quantity_returned)';

        $rows = DB::table('purchase_lines as pl')
            ->join('transactions as t', 't.id', '=', 'pl.transaction_id')
            ->join('products as p', 'p.id', '=', 'pl.product_id')
            ->join('variations as v', 'v.id', '=', 'pl.variation_id')
            ->leftJoin('business_locations as l', 'l.id', '=', 't.location_id')
            ->where('t.business_id', $bid)
            ->whereIn('t.type', ['purchase', 'opening_stock'])
            ->where('t.status', 'received')
            ->whereNotNull('pl.exp_date')
            ->whereRaw($remaining.' > 0')
            ->where('pl.exp_date', '<=', $today->copy()->addDays($horizon)->toDateString())
            ->when($locationId, fn ($q) => $q->where('t.location_id', $locationId))
            ->orderBy('pl.exp_date')
            ->get([
                'p.name', 'p.type', 'v.name as vname', 'v.sub_sku', 'pl.lot_number', 'pl.exp_date',
                'pl.purchase_price_inc_tax', 'l.name as location',
                DB::raw($remaining.' as remaining'),
            ]);

        $sections = [
            'expired' => ['title' => 'Already expired', 'class' => 'box-danger', 'rows' => [], 'value' => 0],
            'd30' => ['title' => 'Expires within 30 days', 'class' => 'box-warning', 'rows' => [], 'value' => 0],
            'd60' => ['title' => 'Expires in 31-60 days', 'class' => 'box-info', 'rows' => [], 'value' => 0],
            'd90' => ['title' => 'Expires in 61-90 days', 'class' => 'box-default', 'rows' => [], 'value' => 0],
            'later' => ['title' => 'Expires in 91-180 days', 'class' => 'box-default', 'rows' => [], 'value' => 0],
        ];

        foreach ($rows as $r) {
            $days = (int) $today->diffInDays(Carbon::parse($r->exp_date)->startOfDay(), false);
            $key = $days < 0 ? 'expired' : ($days <= 30 ? 'd30' : ($days <= 60 ? 'd60' : ($days <= 90 ? 'd90' : 'later')));
            $value = (float) $r->remaining * (float) $r->purchase_price_inc_tax;

            $sections[$key]['rows'][] = [
                'name' => $r->type === 'variable' ? $r->name.' - '.$r->vname : $r->name,
                'sku' => $r->sub_sku, 'lot' => $r->lot_number, 'location' => $r->location,
                'exp' => $r->exp_date, 'days' => $days, 'qty' => (float) $r->remaining, 'value' => $value,
            ];
            $sections[$key]['value'] += $value;
        }

        if ($request->input('export') === 'csv') {
            return response()->streamDownload(function () use ($sections) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Status', 'Product', 'SKU', 'Lot', 'Location', 'Expiry date', 'Days left', 'Qty remaining', 'Value at cost']);
                foreach ($sections as $s) {
                    foreach ($s['rows'] as $r) {
                        fputcsv($out, [$s['title'], $r['name'], $r['sku'], $r['lot'], $r['location'], $r['exp'], $r['days'], $r['qty'], round($r['value'], 2)]);
                    }
                }
                fclose($out);
            }, 'expiry-watch-'.$today->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
        }

        $locations = BusinessLocation::forDropdown($bid, true);

        return view('report.expiry_watch', compact('sections', 'locations', 'locationId', 'horizon'));
    }
}
