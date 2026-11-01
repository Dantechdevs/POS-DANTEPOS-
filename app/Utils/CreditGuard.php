<?php

namespace App\Utils;

use App\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CreditGuard
{
    /** Kept so the existing SellPosController hook keeps working. */
    public static function walkInCreditViolation(Request $request, $business_id)
    {
        return self::violation($request, $business_id);
    }

    public static function canOverrideLimit(): bool
    {
        return auth()->check() && auth()->user()->can('credit.override');
    }

    /**
     * Returns an error message if this is a credit/part-payment sale that
     * breaks a credit rule, otherwise null.
     */
    public static function violation(Request $request, $business_id)
    {
        if ($request->input('status') !== 'final') {
            return null; // drafts, quotations and suspended sales are fine
        }

        $contact = Contact::where('business_id', $business_id)->find($request->input('contact_id'));
        if (! $contact) {
            return null;
        }

        $util = app(Util::class);
        $total = (float) $util->num_uf($request->input('final_total', 0));

        $paid = 0;
        foreach ((array) $request->input('payment', []) as $p) {
            if (($p['method'] ?? '') === 'advance') {
                continue;
            }
            $paid += (float) $util->num_uf($p['amount'] ?? 0);
        }

        $isCredit = $total > 0 && ((int) $request->input('is_credit_sale') === 1 || $paid + 0.009 < $total);
        if (! $isCredit) {
            return null;
        }

        if ($contact->is_default) {
            return 'Credit or part-payment sales are not allowed for the Walk-In customer. Select or add a named customer with a phone number.';
        }

        if (config('credit.require_phone')) {
            $digits = preg_replace('/\D+/', '', (string) $contact->mobile);
            if (! preg_match('/^(254|0)?[17]\d{8}$/', $digits)) {
                return 'Add a valid phone number (07XX XXX XXX) to '.$contact->name.' before selling on credit.';
            }
        }

        if (config('credit.require_limit') && ($contact->credit_limit === null || $contact->credit_limit === '') && ! self::canOverrideLimit()) {
            return 'Set a credit limit for '.$contact->name.' (Contacts > Edit) before selling on credit, or ask a manager.';
        }

        if (self::canOverrideLimit()) {
            Log::info('Credit sale by user with credit.override', [
                'user_id' => auth()->id(),
                'contact_id' => $contact->id,
                'contact' => $contact->name,
                'sale_total' => $total,
                'paid_now' => $paid,
                'credit_limit' => $contact->credit_limit,
            ]);
        }

        return null;
    }
}
