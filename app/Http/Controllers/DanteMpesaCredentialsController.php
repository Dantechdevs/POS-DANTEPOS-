<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DanteMpesaCredentialsController extends Controller
{
    public function save(Request $request)
    {
        if (! auth()->user()->can('dantepos.settings')) {
            abort(403, 'Unauthorized action.');
        }
        $bid = (int) $request->session()->get('user.business_id');
        $row = DB::table('mpesa_settings')->where('business_id', $bid)->first();

        $request->validate([
            'daraja_env' => ['required', 'in:sandbox,production'],
            'consumer_key' => [empty($row->consumer_key_enc) ? 'required' : 'nullable', 'string', 'max:200'],
            'consumer_secret' => [empty($row->consumer_secret_enc) ? 'required' : 'nullable', 'string', 'max:300'],
            'passkey' => [empty($row->passkey_enc) ? 'required' : 'nullable', 'string', 'max:500'],
            'stk_shortcode' => ['required', 'digits_between:5,7'],
            'transaction_type' => ['required', 'in:CustomerBuyGoodsOnline,CustomerPayBillOnline'],
        ], [
            'consumer_key.required' => 'Enter the Daraja consumer key.',
            'consumer_secret.required' => 'Enter the Daraja consumer secret.',
            'passkey.required' => 'Enter the Daraja passkey.',
            'stk_shortcode.required' => 'Enter the shortcode used for STK push.',
            'stk_shortcode.digits_between' => 'The shortcode has 5 to 7 digits.',
        ]);

        $data = [
            'daraja_env' => $request->input('daraja_env'),
            'stk_shortcode' => trim($request->input('stk_shortcode')),
            'transaction_type' => $request->input('transaction_type'),
            'updated_at' => now(),
        ];
        foreach (['consumer_key' => 'consumer_key_enc', 'consumer_secret' => 'consumer_secret_enc', 'passkey' => 'passkey_enc'] as $in => $col) {
            $v = trim((string) $request->input($in));
            if ($v !== '') {
                $data[$col] = Crypt::encryptString($v);
            }
        }
        if (empty($row->callback_secret) || $request->boolean('regenerate_callback')) {
            $data['callback_secret'] = Str::random(40);
        }

        if ($row) {
            DB::table('mpesa_settings')->where('business_id', $bid)->update($data);
        } else {
            DB::table('mpesa_settings')->insert($data + [
                'business_id' => $bid, 'account_type' => 'till', 'manual_enabled' => 0,
                'manual_unverified_cap' => 0, 'created_at' => now(),
            ]);
        }

        return back()->with('dantepos_msg', 'Daraja details saved.');
    }
}
