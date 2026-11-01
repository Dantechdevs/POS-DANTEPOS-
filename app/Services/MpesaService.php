<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
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

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 20,
            'connect_timeout' => 8,
        ]);
    }

    public function getAccessToken()
    {
        $cacheKey = 'mpesa_token_' . md5($this->baseUrl . $this->config['consumer_key']);

        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        $response = $this->client->get('/oauth/v1/generate', [
            'query' => ['grant_type' => 'client_credentials'],
            'auth' => [$this->config['consumer_key'], $this->config['consumer_secret']],
        ]);

        $body = json_decode($response->getBody(), true);
        $token = $body['access_token'] ?? null;

        if ($token) {
            // Daraja tokens last about an hour; refresh a little early.
            Cache::put($cacheKey, $token, now()->addSeconds(3000));
        }

        return $token;
    }

    public function stkPush($phone, $amount, $accountReference, $description = 'POS Payment')
    {
        $accessToken = $this->getAccessToken();

        if (!$accessToken) {
            throw new \Exception('Unable to authenticate with M-Pesa. Check your consumer key/secret.');
        }

        $shortcode = $this->config['shortcode'];
        $timestamp = $this->timestamp();
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
            'TransactionDesc' => substr($description, 0, 13),
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

    /**
     * Ask Safaricom for the result of an STK Push.
     * While the customer is still paying, Daraja replies with an error body and no ResultCode,
     * so HTTP errors are not thrown and the decoded body is returned as is.
     */
    public function stkQuery($checkoutRequestId)
    {
        $accessToken = $this->getAccessToken();

        if (!$accessToken) {
            throw new \Exception('Unable to authenticate with M-Pesa.');
        }

        $shortcode = $this->config['shortcode'];
        $timestamp = $this->timestamp();
        $password = base64_encode($shortcode . $this->config['passkey'] . $timestamp);

        $response = $this->client->post('/mpesa/stkpushquery/v1/query', [
            'headers' => ['Authorization' => 'Bearer ' . $accessToken],
            'json' => [
                'BusinessShortCode' => $shortcode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'CheckoutRequestID' => $checkoutRequestId,
            ],
            'http_errors' => false,
        ]);

        return json_decode((string) $response->getBody(), true) ?: [];
    }

    protected function timestamp()
    {
        return now('Africa/Nairobi')->format('YmdHis');
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
