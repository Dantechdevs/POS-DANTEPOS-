<?php

namespace App\Http\Controllers;

use App\Business;
use App\BusinessLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DantePaymentController extends Controller
{
    private function allowed(): void
    {
        if (! auth()->user()->can('dantepos.settings')) {
            abort(403, 'Unauthorized action.');
        }
    }

    private function rawMethods($business_id): array
    {
        config(['dantepos_payments_raw' => true]);
        try {
            return app(\App\Utils\Util::class)->payment_types(null, false, $business_id);
        } finally {
            config(['dantepos_payments_raw' => false]);
        }
    }

    private function state($business_id): array
    {
        $methods = $this->rawMethods($business_id);
        $locations = BusinessLocation::where('business_id', $business_id)->get();

        $coverage = [];
        foreach ($methods as $key => $name) {
            $coverage[$key] = 0;
            foreach ($locations as $loc) {
                $a = json_decode((string) $loc->default_payment_accounts, true) ?: [];
                if (! empty($a[$key]['is_enabled'])) {
                    $coverage[$key]++;
                }
            }
        }

        $row = DB::table('dantepos_payment_config')->where('business_id', $business_id)->value('config');
        $cfg = $row ? (json_decode($row, true) ?: null) : null;

        if ($cfg) {
            $enabled = array_values(array_diff(array_keys($methods), $cfg['disabled'] ?? []));
            $labels = $cfg['labels'] ?? [];
            $default = $cfg['default'] ?? 'cash';
        } else {
            $enabled = array_keys(array_filter($coverage));
            $labels = [];
            $default = 'cash';
        }

        return [
            'methods' => $methods,
            'coverage' => $coverage,
            'total' => $locations->count(),
            'enabled' => $enabled,
            'labels' => $labels,
            'default' => $default,
        ];
    }

    public function index()
    {
        $this->allowed();
        $business_id = request()->session()->get('user.business_id');

        return view('dantepos_settings.payments', $this->state($business_id));
    }

    public function update(Request $request)
    {
        $this->allowed();
        $business_id = $request->session()->get('user.business_id');
        $st = $this->state($business_id);
        $all = $st['methods'];

        $enabled = array_values(array_intersect(array_keys($all), (array) $request->input('enabled', [])));
        if (count($enabled) === 0) {
            return back()->with('dantepos_error', 'Keep at least one payment method enabled. Nothing was saved.');
        }

        $default = $request->input('default');
        if (! in_array($default, $enabled, true)) {
            $default = $enabled[0];
        }

        $labels = [];
        foreach ((array) $request->input('label', []) as $key => $value) {
            $value = mb_substr(trim(strip_tags((string) $value)), 0, 40);
            if (isset($all[$key]) && $value !== '' && $value !== $all[$key]) {
                $labels[$key] = $value;
            }
        }

        $config = [
            'labels' => $labels,
            'disabled' => array_values(array_diff(array_keys($all), $enabled)),
            'default' => $default,
        ];

        // Methods ticked for the first time are switched on at every location.
        $newly = array_values(array_diff($enabled, $st['enabled']));

        DB::transaction(function () use ($business_id, $config, $newly) {
            DB::table('dantepos_payment_config')->updateOrInsert(
                ['business_id' => $business_id],
                ['config' => json_encode($config), 'updated_at' => now()]
            );

            if ($newly) {
                foreach (BusinessLocation::where('business_id', $business_id)->get() as $loc) {
                    $a = json_decode((string) $loc->default_payment_accounts, true) ?: [];
                    $changed = false;
                    foreach ($newly as $key) {
                        if (empty($a[$key]['is_enabled'])) {
                            $a[$key] = ['is_enabled' => 1, 'account' => $a[$key]['account'] ?? null];
                            $changed = true;
                        }
                    }
                    if ($changed) {
                        $loc->default_payment_accounts = json_encode($a);
                        $loc->save();
                    }
                }
            }
        });

        return back()->with('dantepos_msg', 'Payment configuration saved.');
    }
}
