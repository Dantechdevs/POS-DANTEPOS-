<?php

return [
    'env' => env('AT_ENV', 'sandbox'), // sandbox | production
    'username' => env('AT_USERNAME'),
    'api_key' => env('AT_API_KEY'),
    'sender_id' => env('AT_SENDER_ID'), // optional, must be approved by Africa's Talking
    'pay_instructions' => env('SMS_PAY_INSTRUCTIONS'), // e.g. "via M-Pesa Till 123456"
];
