<?php

namespace App\Http\Controllers;

use App\Contact;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WhatsAppStatementController extends Controller
{
    public function show(Request $request, $id)
    {
        $user = auth()->user();
        if (! ($user->can('sell.view') || $user->can('direct_sell.access') || $user->can('customer.view'))) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');
        $contact = Contact::where('business_id', $business_id)->findOrFail($id);

        $phone = $this->waNumber($contact->mobile);
        if (! $phone) {
            return response('Cannot send: '.$contact->name.' has no valid phone number. Add one under Contacts > Edit (07XX XXX XXX).', 422);
        }

        $paid = '(SELECT COALESCE(SUM(IF(tp.is_return = 1, -1 * tp.amount, tp.amount)), 0)
                  FROM transaction_payments tp WHERE tp.transaction_id = t.id) as paid';

        $rows = DB::table('transactions as t')
            ->where('t.business_id', $business_id)
            ->where('t.contact_id', $contact->id)
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->where('t.type', 'sell')->where('t.status', 'final')->whereIn('t.payment_status', ['due', 'partial']);
                })->orWhere('t.type', 'opening_balance');
            })
            ->select('t.invoice_no', 't.type', 't.transaction_date', 't.final_total', DB::raw($paid))
            ->orderBy('t.transaction_date')
            ->get();

        $lines = [];
        $total = 0;
        foreach ($rows as $r) {
            $balance = round($r->final_total - $r->paid, 2);
            if ($balance <= 0) {
                continue;
            }
            $total += $balance;
            $label = $r->type === 'opening_balance'
                ? 'Opening balance'
                : 'Invoice '.$r->invoice_no.' ('.Carbon::parse($r->transaction_date)->format('d/m/Y').')';
            $lines[] = $label.': KSh '.number_format($balance, 2);
        }

        if (empty($lines)) {
            return response($contact->name.' has no outstanding balance.', 422);
        }

        $maxLines = 15;
        $extra = count($lines) - $maxLines;
        $lines = array_slice($lines, 0, $maxLines);
        if ($extra > 0) {
            $lines[] = '...and '.$extra.' more invoice(s)';
        }

        $business = DB::table('business')->where('id', $business_id)->value('name') ?: 'us';
        $first = explode(' ', trim($contact->name))[0];
        $pay = config('africastalking.pay_instructions');

        $message = 'Hello '.$first.', here is your account statement from '.$business.":\n\n"
            .implode("\n", $lines)
            ."\n\n*TOTAL DUE: KSh ".number_format($total, 2).'*'
            ."\n\nPlease pay ".($pay ?: 'at your earliest convenience').'. Thank you.';

        return redirect()->away('https://wa.me/'.$phone.'?text='.rawurlencode($message));
    }

    private function waNumber($mobile)
    {
        $d = preg_replace('/\D+/', '', (string) $mobile);

        if (preg_match('/^0([17]\d{8})$/', $d, $m) || preg_match('/^([17]\d{8})$/', $d, $m)) {
            return '254'.$m[1];
        }
        if (preg_match('/^(254[17]\d{8})$/', $d, $m)) {
            return $m[1];
        }

        return null;
    }
}
