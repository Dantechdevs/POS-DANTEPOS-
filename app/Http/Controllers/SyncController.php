<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncController extends Controller
{
    /** Satellites call this to prove they can reach the server and that their token works. */
    public function ping(Request $request)
    {
        if (config('sync.role') !== 'primary') {
            abort(404);
        }

        $token = $request->bearerToken();
        if (! $token) {
            return response()->json(['ok' => false, 'message' => 'Missing token.'], 401);
        }

        $device = DB::table('sync_devices')
            ->where('token_hash', hash('sha256', $token))
            ->where('active', 1)
            ->first();

        if (! $device) {
            return response()->json(['ok' => false, 'message' => 'Unknown or disabled device.'], 401);
        }

        DB::table('sync_devices')->where('id', $device->id)->update(['last_seen_at' => now(), 'updated_at' => now()]);

        return response()->json([
            'ok' => true,
            'device' => $device->name,
            'device_id' => $device->device_id,
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
