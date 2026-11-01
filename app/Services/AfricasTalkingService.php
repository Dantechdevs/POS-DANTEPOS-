<?php

namespace App\Services;

use GuzzleHttp\Client;

class AfricasTalkingService
{
    private const PHONE_REGEX = '/^(?:254|0)?([17]\d{8})$/';

    /**
     * Send one SMS to one or more Kenyan numbers.
     *
     * @param  string|array  $to
     * @return array{sent:int, recipients:array}
     */
    public function send($to, string $message): array
    {
        $cfg = config('services.africastalking');

        if (empty($cfg['api_key'])) {
            throw new \RuntimeException('AT_API_KEY is not set in .env.');
        }

        $production = ($cfg['env'] ?? 'sandbox') === 'production';
        $baseUrl = $production ? 'https://api.africastalking.com' : 'https://api.sandbox.africastalking.com';

        $numbers = array_values(array_filter(array_map([$this, 'normalise'], (array) $to)));
        if (empty($numbers)) {
            throw new \RuntimeException('No valid recipient phone numbers.');
        }

        $form = [
            'username' => $cfg['username'],
            'to' => implode(',', $numbers),
            'message' => $message,
        ];

        // A sender ID must be registered with Africa's Talking first.
        if ($production && ! empty($cfg['sender_id'])) {
            $form['from'] = $cfg['sender_id'];
        }

        $client = new Client(['base_uri' => $baseUrl, 'timeout' => 20, 'connect_timeout' => 8]);

        $response = $client->post('/version1/messaging', [
            'headers' => ['apiKey' => $cfg['api_key'], 'Accept' => 'application/json'],
            'form_params' => $form,
            'http_errors' => false,
        ]);

        if ($response->getStatusCode() >= 300) {
            throw new \RuntimeException('Africa\'s Talking error '.$response->getStatusCode().': '.substr((string) $response->getBody(), 0, 200));
        }

        $body = json_decode((string) $response->getBody(), true) ?: [];
        $recipients = $body['SMSMessageData']['Recipients'] ?? [];

        // statusCode 101 means the message was sent to that recipient.
        $sent = count(array_filter($recipients, fn ($r) => ($r['statusCode'] ?? 0) == 101));

        return ['sent' => $sent, 'recipients' => $recipients];
    }

    private function normalise(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        return preg_match(self::PHONE_REGEX, $digits, $m) ? '+254'.$m[1] : null;
    }
}
