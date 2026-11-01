<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DebtorsAgingController extends Controller
{
    public function index(Request $request)
    {
        if (! auth()->user()->can('sell.view') && ! auth()->user()->can('direct_sell.access')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');
        $location_id = $request->input('location_id');
        $today = Carbon::today();

        $rows = DB::table('transactions as t')
            ->join('contacts as c', 'c.id', '=', 't.contact_id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->whereIn('t.payment_status', ['due', 'partial'])
            ->when($location_id, fn ($q) => $q->where('t.location_id', $location_id))
            ->select(
                't.id', 't.invoice_no', 't.transaction_date', 't.final_total',
                'c.id as contact_id', 'c.name', 'c.mobile',
                DB::raw('(SELECT COALESCE(SUM(IF(tp.is_return = 1, -1 * tp.amount, tp.amount)), 0)
                          FROM transaction_payments tp WHERE tp.transaction_id = t.id) as paid')
            )
            ->get();

        $debtors = [];
        foreach ($rows as $r) {
            $balance = round($r->final_total - $r->paid, 2);
            if ($balance <= 0) {
                continue;
            }
            $days = Carbon::parse($r->transaction_date)->startOfDay()->diffInDays($today);
            $bucket = $days <= 30 ? 'b0' : ($days <= 60 ? 'b31' : ($days <= 90 ? 'b61' : 'b90'));

            $d = &$debtors[$r->contact_id];
            if (! $d) {
                $d = ['name' => $r->name, 'mobile' => $r->mobile, 'b0' => 0, 'b31' => 0,
                      'b61' => 0, 'b90' => 0, 'total' => 0, 'oldest' => $r->transaction_date, 'invoices' => 0];
            }
            $d[$bucket] += $balance;
            $d['total'] += $balance;
            $d['invoices']++;
            if ($r->transaction_date < $d['oldest']) {
                $d['oldest'] = $r->transaction_date;
            }
            unset($d);
        }

        uasort($debtors, fn ($a, $b) => $b['total'] <=> $a['total']);

        $totals = ['b0' => 0, 'b31' => 0, 'b61' => 0, 'b90' => 0, 'total' => 0];
        foreach ($debtors as $d) {
            foreach ($totals as $k => $_) {
                $totals[$k] += $d[$k];
            }
        }

        if ($request->input('export') === 'csv') {
            return response()->streamDownload(function () use ($debtors, $totals) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Customer', 'Phone', 'Invoices', 'Oldest Invoice', '0-30', '31-60', '61-90', '90+', 'Total Due']);
                foreach ($debtors as $d) {
                    fputcsv($out, [$d['name'], $d['mobile'], $d['invoices'], substr($d['oldest'], 0, 10),
                        $d['b0'], $d['b31'], $d['b61'], $d['b90'], $d['total']]);
                }
                fputcsv($out, ['TOTAL', '', '', '', $totals['b0'], $totals['b31'], $totals['b61'], $totals['b90'], $totals['total']]);
                fclose($out);
            }, 'debtors-aging-'.$today->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
        }

        $locations = BusinessLocation::forDropdown($business_id, true);

        return view('report.debtors_aging', compact('debtors', 'totals', 'locations', 'location_id'));
    }
}
