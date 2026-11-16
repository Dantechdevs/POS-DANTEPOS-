<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DanteMpesaSetupController extends Controller
{
    private function allowed(): void
    {
        if (! auth()->user()->can('dantepos.settings')) {
            abort(403, 'Unauthorized action.');
        }
    }

    private static function businessId(): int
    {
        return (int) session('user.business_id');
    }

    private static function row(int $bid): array
    {
        return (array) DB::table('mpesa_settings')->where('business_id', $bid)->first();
    }

    private static function fingerprint(array $r): string
    {
        return sha1(($r['consumer_key_enc'] ?? '').'|'.($r['consumer_secret_enc'] ?? '').'|'.($r['daraja_env'] ?? 'sandbox'));
    }

    private function back(string $kind, string $message)
    {
        return redirect(route('dantepos.payments').'#mpesa-setup')->with($kind, $message);
    }

    /** Data for the setup box. Never returns a secret. */
    public static function viewData(): array
    {
        $bid = self::businessId();
        $r = self::row($bid);

        $type = ($r['account_type'] ?? '') === 'paybill' ? 'paybill' : 'till';
        $has = [
            'key' => ! empty($r['consumer_key_enc']),
            'secret' => ! empty($r['consumer_secret_enc']),
            'passkey' => ! empty($r['passkey_enc']),
        ];
        $numbers = $type === 'paybill'
            ? ! empty($r['shortcode'])
            : (! empty($r['till_number']) && ! empty($r['stk_shortcode']));
        $complete = $has['key'] && $has['secret'] && $has['passkey'] && $numbers;
        $env = ($r['daraja_env'] ?? 'sandbox') === 'live' ? 'live' : 'sandbox';

        $test = Cache::get('dante_mpesa_test_'.$bid);
        if (! $complete) {
            $state = 'not_set';
        } elseif (! is_array($test) || ($test['fp'] ?? '') !== self::fingerprint($r)) {
            $state = 'untested';
        } else {
            $state = ! empty($test['ok']) ? 'accepted' : 'failed';
        }

        return [
            'type' => $type,
            'env' => $env,
            'has' => $has,
            'state' => $state,
            'test' => is_array($test) ? $test : null,
            'till_number' => $r['till_number'] ?? '',
            'stk_shortcode' => $r['stk_shortcode'] ?? '',
            'shortcode' => $r['shortcode'] ?? '',
            'account_hint' => $r['account_hint'] ?? '',
            'callback' => ! empty($r['callback_secret']) ? url('/mpesa/callback/'.$r['callback_secret']) : null,
        ];
    }

    public function save(Request $request)
    {
        $this->allowed();
        $bid = self::businessId();
        if ($bid <= 0) {
            abort(403, 'Unauthorized action.');
        }
        $r = self::row($bid);

        $type = $request->input('account_type') === 'paybill' ? 'paybill' : 'till';
        $rules = [
            'daraja_env' => 'required|in:sandbox,live',
            'account_hint' => 'nullable|string|max:60',
            'consumer_key' => 'nullable|string|max:200',
            'consumer_secret' => 'nullable|string|max:200',
            'passkey' => 'nullable|string|max:300',
        ];
        if ($type === 'paybill') {
            $rules['shortcode'] = 'required|digits_between:5,7';
        } else {
            $rules['till_number'] = 'required|digits_between:5,7';
            $rules['stk_shortcode'] = 'required|digits_between:5,7';
        }

        $v = Validator::make($request->all(), $rules);
        $v->after(function ($v) use ($request, $r) {
            foreach ([
                ['consumer_key', 'consumer_key_enc', 'the consumer key'],
                ['consumer_secret', 'consumer_secret_enc', 'the consumer secret'],
                ['passkey', 'passkey_enc', 'the passkey'],
            ] as [$in, $col, $label]) {
                if (trim((string) $request->input($in)) === '' && empty($r[$col])) {
                    $v->errors()->add($in, 'Enter '.$label.' from your Daraja app.');
                }
            }
        });
        if ($v->fails()) {
            return redirect(route('dantepos.payments').'#mpesa-setup')
                ->withErrors($v)
                ->withInput($request->except(['consumer_key', 'consumer_secret', 'passkey']));
        }

        $row = [
            'account_type' => $type,
            'daraja_env' => $request->input('daraja_env'),
            'account_hint' => trim((string) $request->input('account_hint')) ?: null,
            'transaction_type' => $type === 'paybill' ? 'CustomerPayBillOnline' : 'CustomerBuyGoodsOnline',
            'updated_at' => now(),
        ];
        if ($type === 'paybill') {
            $row['shortcode'] = trim($request->input('shortcode'));
            $row['stk_shortcode'] = $row['shortcode'];
        } else {
            $row['till_number'] = trim($request->input('till_number'));
            $row['stk_shortcode'] = trim($request->input('stk_shortcode'));
            $row['shortcode'] = $row['stk_shortcode'];
        }
        foreach ([
            'consumer_key' => 'consumer_key_enc',
            'consumer_secret' => 'consumer_secret_enc',
            'passkey' => 'passkey_enc',
        ] as $in => $col) {
            $val = trim((string) $request->input($in));
            if ($val !== '') {
                $row[$col] = Crypt::encryptString($val);
            }
        }
        if (empty($r['callback_secret']) || $request->boolean('regen_secret')) {
            $row['callback_secret'] = Str::random(40);
        }
        if (! $r) {
            $row['created_at'] = now();
        }

        try {
            DB::table('mpesa_settings')->updateOrInsert(['business_id' => $bid], $row);
        } catch (\Throwable $e) {
            Log::error('M-Pesa setup save failed: '.$e->getMessage());

            return $this->back('dantepos_error', 'Could not save the M-Pesa setup. Nothing was changed. See the log for details.');
        }
        Cache::forget('dante_mpesa_test_'.$bid);

        return $this->back('dantepos_msg', 'M-Pesa setup saved. Press "Test connection" to check your keys.');
    }

    public function test(Request $request)
    {
        $this->allowed();
        $bid = self::businessId();
        $r = self::row($bid);

        if (empty($r['consumer_key_enc']) || empty($r['consumer_secret_enc'])) {
            return $this->back('dantepos_error', 'Save the consumer key and secret first.');
        }

        try {
            $key = Crypt::decryptString($r['consumer_key_enc']);
            $secret = Crypt::decryptString($r['consumer_secret_enc']);
        } catch (\Throwable $e) {
            return $this->back('dantepos_error', 'The saved keys cannot be read (the app key may have changed). Enter them again and save.');
        }

        $base = ($r['daraja_env'] ?? 'sandbox') === 'live' ? 'https://api.safaricom.co.ke' : 'https://sandbox.safaricom.co.ke';

        try {
            $res = Http::withBasicAuth($key, $secret)->acceptJson()->timeout(20)
                ->get($base.'/oauth/v1/generate', ['grant_type' => 'client_credentials']);
            $ok = $res->successful() && ! empty($res->json('access_token'));
            $msg = $ok
                ? 'Safaricom accepted the keys.'
                : 'Safaricom replied HTTP '.$res->status().' ('.Str::limit(strip_tags((string) ($res->json('errorMessage') ?: 'wrong keys, or the keys belong to the other environment')), 120).').';
        } catch (\Throwable $e) {
            $ok = false;
            $msg = 'Could not reach Safaricom: '.Str::limit($e->getMessage(), 120);
        }

        Cache::forever('dante_mpesa_test_'.$bid, [
            'ok' => $ok,
            'msg' => $msg,
            'at' => now()->format('d M Y H:i'),
            'fp' => self::fingerprint($r),
        ]);

        return $this->back($ok ? 'dantepos_msg' : 'dantepos_error', $msg);
    }
}
