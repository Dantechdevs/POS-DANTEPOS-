<?php

namespace App\Http\Controllers;

use App\Services\MpesaSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MpesaSettingsController extends Controller
{
    private function authorise(): void
    {
        if (! auth()->user()->can('business_settings.access')) {
            abort(403, 'Unauthorized action.');
        }
    }

    public function edit(Request $request)
    {
        $this->authorise();
        $bid = (int) $request->session()->get('user.business_id');
        $row = DB::table('mpesa_settings')->where('business_id', $bid)->first();

        return view('mpesa.settings', ['s' => $row, 'envShortcode' => (string) config('services.mpesa.shortcode')]);
    }

    public function update(Request $request)
    {

        $this->authorise();

        $data = $request->validate([
            'account_type' => 'required|in:till,paybill',
            'shortcode' => 'required|digits_between:5,8',
            'till_number' => 'nullable|digits_between:5,8|required_if:account_type,till',
            'account_hint' => 'nullable|string|max:80',
            'manual_unverified_cap' => 'required|numeric|min:0|max:100000000',
        ]);

        $bid = (int) $request->session()->get('user.business_id');

        DB::table('mpesa_settings')->updateOrInsert(['business_id' => $bid], [
            'account_type' => $data['account_type'],
            'shortcode' => $data['shortcode'],
            'till_number' => $data['account_type'] === 'till' ? $data['till_number'] : null,
            'account_hint' => $data['account_type'] === 'paybill' ? ($data['account_hint'] ?? null) : null,
            'manual_enabled' => $request->boolean('manual_enabled') ? 1 : 0,
            'manual_unverified_cap' => $data['manual_unverified_cap'],
            'updated_at' => now(),
        ]);
        MpesaSettings::forget();

        return back()->with('status', ['success' => 1, 'msg' => 'M-Pesa settings saved.']);
    }
}
