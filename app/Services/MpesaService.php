<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class MpesaService
{
    protected $client;
    protected $baseUrl;
    protected $config;

    public function __construct()
    {
        $this->config = config('services.mpesa');
        $this->baseUrl = $this->config['env'] === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';

        $this->client = new Client(['base_uri' => $this->baseUrl]);
    }

    public function getAccessToken()
    {
        $response = $this->client->get('/oauth/v1/generate', [
            'query' => ['grant_type' => 'client_credentials'],
            'auth' => [$this->config['consumer_key'], $this->config['consumer_secret']],
        ]);

        $body = json_decode($response->getBody(), true);

        return $body['access_token'] ?? null;
    }

    public function stkPush($phone, $amount, $accountReference, $description = 'POS Payment')
    {
        $accessToken = $this->getAccessToken();

        if (!$accessToken) {
            throw new \Exception('Unable to authenticate with M-Pesa. Check your consumer key/secret.');
        }

        $shortcode = $this->config['shortcode'];
        $timestamp = date('YmdHis');
        $password = base64_encode($shortcode . $this->config['passkey'] . $timestamp);

        $payload = [
            'BusinessShortCode' => $shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => $this->config['transaction_type'],
            'Amount' => (int) round($amount),
            'PartyA' => $this->normalizePhone($phone),
            'PartyB' => $shortcode,
            'PhoneNumber' => $this->normalizePhone($phone),
            'CallBackURL' => $this->config['callback_url'],
            'AccountReference' => substr($accountReference, 0, 12),
            'TransactionDesc' => $description,
        ];

        try {
            $response = $this->client->post('/mpesa/stkpush/v1/processrequest', [
                'headers' => ['Authorization' => 'Bearer ' . $accessToken],
                'json' => $payload,
            ]);

            return json_decode($response->getBody(), true);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $body = $e->getResponse() ? (string) $e->getResponse()->getBody() : $e->getMessage();
            Log::error('M-Pesa STK push failed: ' . $body);
            throw new \Exception('M-Pesa request failed: ' . $body);
        }
    }

    public function stkQuery($checkoutRequestId)
    {
        $accessToken = $this->getAccessToken();
        $shortcode = $this->config['shortcode'];
        $timestamp = date('YmdHis');
        $password = base64_encode($shortcode . $this->config['passkey'] . $timestamp);

        $response = $this->client->post('/mpesa/stkpushquery/v1/query', [
            'headers' => ['Authorization' => 'Bearer ' . $accessToken],
            'json' => [
                'BusinessShortCode' => $shortcode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'CheckoutRequestID' => $checkoutRequestId,
            ],
        ]);

        return json_decode($response->getBody(), true);
    }

    protected function normalizePhone($phone)
    {
        $phone = preg_replace('/\D/', '', $phone);

        if (substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        } elseif (substr($phone, 0, 1) === '7' || substr($phone, 0, 1) === '1') {
            $phone = '254' . $phone;
        }

        return $phone;
    }
}
