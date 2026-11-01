<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class AfricasTalkingSms
{
    public function configured(): bool
    {
        return ! empty(config('africastalking.username')) && ! empty(config('africastalking.api_key'));
    }

    public static function normalizePhone(?string $phone): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $phone);

        if (preg_match('/^0([17]\d{8})$/', $d, $m)) {
            return '+254'.$m[1];
        }
        if (preg_match('/^([17]\d{8})$/', $d, $m)) {
            return '+254'.$m[1];
        }
        if (preg_match('/^254([17]\d{8})$/', $d, $m)) {
            return '+254'.$m[1];
        }

        return null;
    }

    /**
     * @return array{ok:bool,error:?string,response:mixed}
     */
    public function send(string $phone, string $message): array
    {
        if (! $this->configured()) {
            return ['ok' => false, 'error' => 'Africa\'s Talking is not configured (set AT_USERNAME and AT_API_KEY).', 'response' => null];
        }

        $to = self::normalizePhone($phone);
        if (! $to) {
            return ['ok' => false, 'error' => 'Invalid phone number: '.$phone, 'response' => null];
        }

        $base = config('africastalking.env') === 'production'
            ? 'https://api.africastalking.com'
            : 'https://api.sandbox.africastalking.com';

        $form = [
            'username' => config('africastalking.username'),
            'to' => $to,
            'message' => $message,
        ];
        if (! empty(config('africastalking.sender_id'))) {
            $form['from'] = config('africastalking.sender_id');
        }

        try {
            $client = new Client(['timeout' => 20, 'connect_timeout' => 8, 'http_errors' => false]);
            $res = $client->post($base.'/version1/messaging', [
                'headers' => [
                    'apiKey' => config('africastalking.api_key'),
                    'Accept' => 'application/json',
                ],
                'form_params' => $form,
            ]);

            $body = json_decode((string) $res->getBody(), true);
            $rec = $body['SMSMessageData']['Recipients'][0] ?? null;
            $ok = $rec && in_array((int) ($rec['statusCode'] ?? 0), [100, 101, 102], true);

            return [
                'ok' => $ok,
                'error' => $ok ? null : ($rec['status'] ?? ($body['SMSMessageData']['Message'] ?? 'SMS failed')),
                'response' => $body,
            ];
        } catch (\Throwable $e) {
            Log::error('Africa\'s Talking SMS error: '.$e->getMessage());

            return ['ok' => false, 'error' => $e->getMessage(), 'response' => null];
        }
    }
}
