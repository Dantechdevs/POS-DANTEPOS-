<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayablesAgingController extends Controller
{
    public function index(Request $request)
    {
        $u = auth()->user();
        if (! ($u->can('purchase.view') || $u->can('supplier.view'))) {
            abort(403, 'Unauthorized action.');
        }

        $bid = $request->session()->get('user.business_id');
        $locationId = $request->input('location_id');
        $today = Carbon::today();

        $rows = DB::table('transactions as t')
            ->join('contacts as c', 'c.id', '=', 't.contact_id')
            ->where('t.business_id', $bid)
            ->where('t.type', 'purchase')
            ->where('t.status', 'received')
            ->whereIn('t.payment_status', ['due', 'partial'])
            ->when($locationId, fn ($q) => $q->where('t.location_id', $locationId))
            ->select(
                't.id', 't.ref_no', 't.transaction_date', 't.final_total', 't.pay_term_number', 't.pay_term_type',
                'c.id as contact_id', 'c.name', 'c.supplier_business_name', 'c.mobile',
                DB::raw('(SELECT COALESCE(SUM(IF(tp.is_return = 1, -1 * tp.amount, tp.amount)), 0)
                          FROM transaction_payments tp WHERE tp.transaction_id = t.id) as paid')
            )
            ->get();

        $suppliers = [];
        foreach ($rows as $r) {
            $balance = round($r->final_total - $r->paid, 2);
            if ($balance <= 0) {
                continue;
            }

            $due = Carbon::parse($r->transaction_date)->startOfDay();
            if (! empty($r->pay_term_number)) {
                $r->pay_term_type === 'months' ? $due->addMonths((int) $r->pay_term_number) : $due->addDays((int) $r->pay_term_number);
            }

            $overdue = (int) $due->diffInDays($today, false); // > 0 means past due
            $bucket = $overdue <= 0 ? 'b0' : ($overdue <= 30 ? 'b30' : ($overdue <= 60 ? 'b60' : ($overdue <= 90 ? 'b90' : 'b90p')));

            $s = &$suppliers[$r->contact_id];
            if (! $s) {
                $s = ['name' => $r->supplier_business_name ?: $r->name, 'mobile' => $r->mobile,
                      'b0' => 0, 'b30' => 0, 'b60' => 0, 'b90' => 0, 'b90p' => 0, 'total' => 0, 'bills' => 0, 'oldest_due' => $due->toDateString()];
            }
            $s[$bucket] += $balance;
            $s['total'] += $balance;
            $s['bills']++;
            if ($due->toDateString() < $s['oldest_due']) {
                $s['oldest_due'] = $due->toDateString();
            }
            unset($s);
        }

        uasort($suppliers, fn ($a, $b) => $b['total'] <=> $a['total']);

        $totals = ['b0' => 0, 'b30' => 0, 'b60' => 0, 'b90' => 0, 'b90p' => 0, 'total' => 0];
        foreach ($suppliers as $s) {
            foreach ($totals as $k => $_) {
                $totals[$k] += $s[$k];
            }
        }

        if ($request->input('export') === 'csv') {
            return response()->streamDownload(function () use ($suppliers, $totals) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Supplier', 'Phone', 'Bills', 'Oldest due', 'Not yet due', '1-30 overdue', '31-60', '61-90', '90+', 'Total owed']);
                foreach ($suppliers as $s) {
                    fputcsv($out, [$s['name'], $s['mobile'], $s['bills'], $s['oldest_due'], $s['b0'], $s['b30'], $s['b60'], $s['b90'], $s['b90p'], $s['total']]);
                }
                fputcsv($out, ['TOTAL', '', '', '', $totals['b0'], $totals['b30'], $totals['b60'], $totals['b90'], $totals['b90p'], $totals['total']]);
                fclose($out);
            }, 'payables-aging-'.$today->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
        }

        $locations = BusinessLocation::forDropdown($bid, true);

        return view('report.payables_aging', compact('suppliers', 'totals', 'locations', 'locationId'));
    }
}
