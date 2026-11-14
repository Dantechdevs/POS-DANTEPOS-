<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Protects the payment settings pages: the user must confirm their password
 * again (see DanteReauthController) before the page opens.
 */
class DanteSensitiveGate
{
    public function handle(Request $request, Closure $next, string $mode = 'payments')
    {
        if (! $request->user()) {
            return $next($request);
        }

        if ($mode === 'payments') {
            $at = (int) $request->session()->get('dante_reauth_at', 0);
            $limit = max(1, (int) config('dante.reauth_minutes', 15)) * 60;
            if ($at <= 0 || (time() - $at) > $limit) {
                if ($request->expectsJson()) {
                    return response()->json(['success' => false, 'msg' => 'Confirm your password first.'], 423);
                }

                return redirect()->route('dante.reauth');
            }
        }

        return $next($request);
    }
}
