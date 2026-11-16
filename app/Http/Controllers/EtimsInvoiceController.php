<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EtimsInvoiceController extends Controller
{
    private function businessId(): int
    {
        if (! auth()->user()->can('business_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        return (int) request()->session()->get('user.business_id');
    }

    public function index(Request $request)
    {
        $businessId = $this->businessId();
        $status = $request->query('status');
        $search = trim((string) $request->query('q', ''));

        $query = DB::table('etims_invoices as e')
            ->join('transactions as t', 't.id', '=', 'e.transaction_id')
            ->where('e.business_id', $businessId)
            ->select('e.*', 't.invoice_no', 't.final_total', 't.transaction_date');

        if (in_array($status, ['pending', 'submitted', 'failed'], true)) {
            $query->where('e.status', $status);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('t.invoice_no', 'like', "%{$search}%")
                    ->orWhere('e.cu_receipt_no', 'like', "%{$search}%");
            });
        }

        $rows = $query->orderByDesc('e.id')->paginate(25)->withQueryString();

        $counts = DB::table('etims_invoices')
            ->where('business_id', $businessId)
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return view('etims.index', compact('rows', 'counts', 'status', 'search'));
    }

    public function retry($id)
    {
        $businessId = $this->businessId();

        $n = DB::table('etims_invoices')
            ->where('id', $id)
            ->where('business_id', $businessId)
            ->where('status', 'failed')
            ->update(['status' => 'pending', 'attempts' => 0, 'last_error' => null, 'updated_at' => now()]);

        return redirect()->route('etims.index')->with('status', [
            'success' => $n ? 1 : 0,
            'msg' => $n ? 'Invoice queued for retry.' : 'Only failed invoices can be retried.',
        ]);
    }

    public function retryFailed()
    {
        $businessId = $this->businessId();

        $n = DB::table('etims_invoices')
            ->where('business_id', $businessId)
            ->where('status', 'failed')
            ->update(['status' => 'pending', 'attempts' => 0, 'last_error' => null, 'updated_at' => now()]);

        return redirect()->route('etims.index')->with('status', [
            'success' => $n ? 1 : 0,
            'msg' => $n ? "{$n} failed invoice(s) queued for retry." : 'There are no failed invoices.',
        ]);
    }
}
