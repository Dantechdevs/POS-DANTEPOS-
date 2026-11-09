<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\MpesaStatementImporter;

class MpesaReconciliationController extends Controller
{
    public function index(Request $request)
    {
        if (! auth()->user()->can('mpesa.reconcile')) {
            abort(403, 'Unauthorized action.');
        }

        $bid = $request->session()->get('user.business_id');
        $from = Carbon::parse($request->input('from', now()->subDays(30)->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('to', now()->toDateString()))->endOfDay();
        $grace = now()->subMinutes(15); // ignore payments that may still be mid-sale

        $base = DB::table('mpesa_transactions as m')
            ->where(function ($q) use ($bid) {
                $q->where('m.business_id', $bid)->orWhereNull('m.business_id');
            })
            ->whereBetween('m.created_at', [$from, $to]);

        $map = fn ($m) => [
            'id' => $m->id ?? null,
            'date' => $m->created_at, 'phone' => $m->phone, 'amount' => $m->amount,
            'receipt' => $m->mpesa_receipt_number, 'detail' => $m->result_desc, 'checkout' => $m->checkout_request_id,
        ];

        $manual = (clone $base)->where('m.status', 'manual')->orderBy('m.created_at')->get()->map(function ($m) {
            return [
                'id' => $m->id, 'date' => $m->created_at, 'phone' => $m->phone, 'amount' => $m->amount,
                'receipt' => $m->mpesa_receipt_number, 'checkout' => null,
                'detail' => 'Typed at the till'.($m->transaction_id ? ' (sale #'.$m->transaction_id.')' : '')
                    .(\Carbon\Carbon::parse($m->created_at)->lt(now()->subDay()) ? ' - OVER 24 HOURS, not yet verified' : ''),
            ];
        })->all();

        $review = (clone $base)->where('m.status', 'review')->orderByDesc('m.created_at')->get()->map($map)->all();

        $unmatched = (clone $base)->where('m.status', 'success')->where('m.created_at', '<', $grace)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('transaction_payments as tp')
                  ->whereColumn('tp.mpesa_receipt_number', 'm.mpesa_receipt_number');
            })
            ->orderByDesc('m.created_at')->get()->map($map)->all();

        $stale = (clone $base)->where('m.status', 'pending')->where('m.created_at', '<', $grace)
            ->orderByDesc('m.created_at')->get()->map($map)->all();

        $noReceipt = DB::table('transaction_payments as tp')
            ->leftJoin('transactions as t', 't.id', '=', 'tp.transaction_id')
            ->leftJoin('contacts as c', 'c.id', '=', 't.contact_id')
            ->where('tp.business_id', $bid)
            ->where('tp.method', 'mpesa')
            ->whereNull('tp.parent_id')
            ->where(function ($q) {
                $q->whereNull('tp.mpesa_receipt_number')->orWhere('tp.mpesa_receipt_number', '');
            })
            ->whereBetween('tp.paid_on', [$from, $to])
            ->orderByDesc('tp.paid_on')
            ->get(['tp.paid_on', 'tp.amount', 't.invoice_no', 'c.name'])
            ->map(fn ($p) => [
                'date' => $p->paid_on, 'phone' => '', 'amount' => $p->amount, 'receipt' => '(none)',
                'detail' => trim(($p->invoice_no ? 'Invoice '.$p->invoice_no : 'Customer payment').' '.($p->name ? '- '.$p->name : '')),
                'checkout' => null,
            ])->all();

        $sections = [
            ['key' => 'manual', 'title' => 'Manual M-Pesa payments: waiting to be verified', 'class' => 'box-warning', 'rows' => $manual, 'recheck' => false, 'verify' => true,
             'help' => 'The cashier typed these codes in by hand. Upload your M-Pesa statement below to verify them all at once, or check one against the statement or your phone and press Verify. Reject if the code is wrong or the money never arrived; the sale then needs follow-up with the customer.'],
            ['key' => 'review', 'title' => 'Amount mismatch: needs manual review', 'class' => 'box-danger', 'rows' => $review, 'recheck' => false,
             'help' => 'The customer paid less than the amount requested. Check the M-Pesa statement, then collect the balance or refund.'],
            ['key' => 'unmatched', 'title' => 'Paid but not matched to any sale or payment', 'class' => 'box-warning', 'rows' => $unmatched, 'recheck' => false,
             'help' => 'Money arrived but no sale or customer payment carries this receipt (older than 15 minutes). Find the customer by phone, then complete the sale or refund them.'],
            ['key' => 'stale', 'title' => 'Stuck pending (STK sent, no result)', 'class' => 'box-info', 'rows' => $stale, 'recheck' => true,
             'help' => 'No result was received. Re-check asks Safaricom directly and records the outcome.'],
            ['key' => 'noreceipt', 'title' => 'M-Pesa payments saved with no receipt number', 'class' => 'box-warning', 'rows' => $noReceipt, 'recheck' => false,
             'help' => 'Recorded as M-Pesa without a confirmed STK payment. Verify each one against your M-Pesa statement.'],
        ];

        $summary = [
            'received' => (clone $base)->where('m.status', 'success')->sum('m.amount'),
            'unmatched' => array_sum(array_column($unmatched, 'amount')),
            'review' => count($review),
            'manual' => count($manual),
            'manual_amount' => array_sum(array_column($manual, 'amount')),
            'stale' => count($stale),
        ];

        if ($request->input('export') === 'csv') {
            return response()->streamDownload(function () use ($sections) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Section', 'Date', 'Phone', 'Amount', 'Receipt', 'Detail']);
                foreach ($sections as $s) {
                    foreach ($s['rows'] as $r) {
                        fputcsv($out, [$s['key'], $r['date'], $r['phone'], $r['amount'], $r['receipt'], $r['detail']]);
                    }
                }
                fclose($out);
            }, 'mpesa-reconciliation-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
        }

        return view('report.mpesa_reconciliation', compact('sections', 'summary', 'from', 'to'));
    }

    private function authorise(): void
    {
        if (! auth()->user()->can('mpesa.reconcile')) {
            abort(403, 'Unauthorized action.');
        }
    }

    private function manualRow(Request $request, $id)
    {
        $row = DB::table('mpesa_transactions')
            ->where('id', $id)->where('business_id', (int) $request->session()->get('user.business_id'))
            ->where('source', 'manual')->where('status', 'manual')->first();
        abort_if(! $row, 404);

        return $row;
    }

    public function verify(Request $request, $id)
    {
        $this->authorise();
        $row = $this->manualRow($request, $id);
        $by = auth()->user()->first_name ?? 'manager';
        MpesaStatementImporter::mark((int) $row->id, 'success', (int) auth()->id(), 'Verified by '.$by.' on '.now()->format('d/m H:i'));

        return back()->with('status', ['success' => 1, 'msg' => 'Payment '.$row->mpesa_receipt_number.' marked as verified.']);
    }

    public function reject(Request $request, $id)
    {
        $this->authorise();
        $request->validate(['reason' => 'nullable|string|max:150']);
        $row = $this->manualRow($request, $id);
        MpesaStatementImporter::mark((int) $row->id, 'review', (int) auth()->id(), 'REJECTED: '.($request->input('reason') ?: 'not confirmed on statement'));

        return back()->with('status', ['success' => 1, 'msg' => 'Payment '.$row->mpesa_receipt_number.' rejected and moved to review. Follow up with the customer about sale #'.$row->transaction_id.'.']);
    }

    public function importStatement(Request $request)
    {
        $this->authorise();
        $request->validate(['statement' => 'required|file|max:10240']);

        $r = MpesaStatementImporter::import($request->file('statement')->getRealPath(), (int) $request->session()->get('user.business_id'), (int) auth()->id());

        if ($r['error']) {
            return back()->with('status', ['success' => 0, 'msg' => $r['error']]);
        }

        return back()->with('status', ['success' => 1, 'msg' => "Statement read ({$r['rows']} payments). Verified: {$r['matched']}. Amount mismatch (needs review): {$r['mismatch']}. Still not on the statement: {$r['not_found']}."]);
    }
}
