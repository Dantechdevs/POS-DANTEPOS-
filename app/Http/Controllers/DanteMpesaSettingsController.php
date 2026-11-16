<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DanteMpesaSettingsController extends Controller
{
    public function save(Request $request)
    {
        if (! auth()->user()->can('dantepos.settings')) {
            abort(403, 'Unauthorized action.');
        }
        $bid = (int) $request->session()->get('user.business_id');
        $manual = $request->boolean('manual_enabled');

        $request->validate([
            'account_type' => ['required', 'in:till,paybill'],
            'till_number' => ['nullable', 'required_if:account_type,till', 'digits_between:5,7'],
            'shortcode' => ['nullable', 'required_if:account_type,paybill', 'digits_between:5,7'],
            'account_hint' => ['nullable', 'string', 'max:60'],
            'manual_unverified_cap' => [$manual ? 'required' : 'nullable', 'numeric', 'min:0', 'max:1000000'],
        ], [
            'till_number.required_if' => 'Enter the till number.',
            'till_number.digits_between' => 'A till number has 5 to 7 digits.',
            'shortcode.required_if' => 'Enter the paybill number.',
            'shortcode.digits_between' => 'A paybill number has 5 to 7 digits.',
            'manual_unverified_cap.required' => 'Set a maximum amount before allowing unconfirmed M-Pesa payments.',
            'manual_unverified_cap.max' => 'The limit cannot be more than KSh 1,000,000.',
        ]);

        $strip = fn ($v, $n) => ($v = mb_substr(trim(strip_tags((string) $v)), 0, $n)) === '' ? null : $v;
        $data = [
            'account_type' => $request->input('account_type'),
            'till_number' => $strip($request->input('till_number'), 10),
            'shortcode' => $strip($request->input('shortcode'), 10),
            'account_hint' => $strip($request->input('account_hint'), 60),
            'manual_enabled' => $manual ? 1 : 0,
            'manual_unverified_cap' => round((float) $request->input('manual_unverified_cap', 0), 2),
            'updated_at' => now(),
        ];

        $q = DB::table('mpesa_settings')->where('business_id', $bid);
        if ($q->exists()) {
            $q->update($data);
        } else {
            DB::table('mpesa_settings')->insert($data + ['business_id' => $bid, 'created_at' => now()]);
        }

        return back()->with('dantepos_msg', 'M-Pesa settings saved.');
    }
}
