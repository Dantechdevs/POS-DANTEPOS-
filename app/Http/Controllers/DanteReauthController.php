<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class DanteReauthController extends Controller
{
    private function allowed(): void
    {
        if (! auth()->user()->can('dantepos.settings')) {
            abort(403, 'Unauthorized action.');
        }
    }

    public function show()
    {
        $this->allowed();

        return view('dantepos_settings.reauth');
    }

    public function check(Request $request)
    {
        $this->allowed();
        $request->validate(['password' => 'required|string']);
        $user = $request->user();

        if (! Hash::check((string) $request->input('password'), (string) $user->password)) {
            Log::warning('Payment settings: wrong password confirmation', ['user_id' => $user->id, 'ip' => $request->ip()]);

            return back()->withErrors(['password' => 'That password is not correct.']);
        }

        $request->session()->put('dante_reauth_at', time());

        return redirect()->route('dantepos.payments');
    }
}
