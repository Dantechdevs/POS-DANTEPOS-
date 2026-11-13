<?php

return [

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'mpesa' => [
        'callback_secret' => env('MPESA_CALLBACK_SECRET'),
        'env' => env('MPESA_ENV', 'sandbox'),
        'consumer_key' => env('MPESA_CONSUMER_KEY'),
        'consumer_secret' => env('MPESA_CONSUMER_SECRET'),
        'shortcode' => env('MPESA_SHORTCODE'),
        'passkey' => env('MPESA_PASSKEY'),
        'transaction_type' => env('MPESA_TRANSACTION_TYPE', 'CustomerBuyGoodsOnline'),
        'callback_url' => env('MPESA_CALLBACK_URL'),
        'till_number' => env('MPESA_TILL_NUMBER'),
    ],

    'africastalking' => [
        'env' => env('AT_ENV', 'sandbox'),
        'username' => env('AT_USERNAME', 'sandbox'),
        'api_key' => env('AT_API_KEY'),
        'sender_id' => env('AT_SENDER_ID'),
    ],

    'daily_summary' => [
        'to' => env('DAILY_SUMMARY_TO'),
    ],
    'sms_receipt' => [
        'enabled' => env('SMS_RECEIPT_ENABLED', false),
        'mode' => env('SMS_RECEIPT_MODE', 'all'), // all, or credit (only sales with a balance)
    ],
    'offline_pos' => [
        // Optional: the custom payment slot used for "customer paid to our M-Pesa till" (custom_pay_1 .. custom_pay_7).
        'till_method' => env('OFFLINE_TILL_METHOD'),
        'till_label' => env('OFFLINE_TILL_LABEL', 'M-Pesa Till'),
    ],
    'gift_card' => [
        // The custom payment slot (custom_pay_1 .. custom_pay_7) that means "paid with a gift card". Empty = switched off.
        'method' => env('GIFT_CARD_METHOD'),
        'prefix' => env('GIFT_CARD_PREFIX', 'GC'),
    ],
];
