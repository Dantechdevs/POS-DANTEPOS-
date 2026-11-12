<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncController extends Controller
{
    private function device(Request $request)
    {
        if (config('sync.role') !== 'primary') {
            abort(404);
        }

        $token = $request->bearerToken();
        if (! $token) {
            return null;
        }

        return DB::table('sync_devices')
            ->where('token_hash', hash('sha256', $token))
            ->where('active', 1)
            ->first() ?: null;
    }

    /** Satellites call this to prove they can reach the server and that their token works. */
    public function ping(Request $request)
    {
        $device = $this->device($request);
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

    /** Satellites upload queued records here. Records are stored as received. Nothing is applied yet. */
    public function push(Request $request)
    {
        $device = $this->device($request);
        if (! $device) {
            return response()->json(['ok' => false, 'message' => 'Unknown or disabled device.'], 401);
        }

        $items = $request->input('items');
        if (! is_array($items) || count($items) < 1 || count($items) > 50) {
            return response()->json(['ok' => false, 'message' => 'Send between 1 and 50 items.'], 422);
        }

        $results = [];
        foreach ($items as $it) {
            $uuid = (string) ($it['uuid'] ?? '');
            $entity = (string) ($it['entity'] ?? '');
            $payload = $it['payload'] ?? null;

            if ($uuid === '' || strlen($uuid) > 40 || strpos($uuid, $device->device_id.':') !== 0) {
                $results[$uuid] = 'rejected: bad id';
                continue;
            }
            if ($entity !== 'sale') {
                $results[$uuid] = 'rejected: unknown type';
                continue;
            }
            if (! is_string($payload) || strlen($payload) > 500000 || json_decode($payload) === null) {
                $results[$uuid] = 'rejected: bad payload';
                continue;
            }

            $inserted = DB::table('sync_inbox')->insertOrIgnore([
                'uuid' => $uuid,
                'device_id' => $device->device_id,
                'entity' => $entity,
                'payload' => $payload,
                'status' => 'received',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $results[$uuid] = $inserted ? 'received' : 'duplicate';
        }

        DB::table('sync_devices')->where('id', $device->id)->update(['last_seen_at' => now(), 'updated_at' => now()]);

        return response()->json(['ok' => true, 'results' => $results]);
    }
}
