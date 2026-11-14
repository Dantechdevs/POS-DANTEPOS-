<?php

return [
    // log   = flag unconfirmed M-Pesa sale payments, never reject a sale
    // block = reject unconfirmed M-Pesa sale payments unless within the manual limit
    // off   = no checking
    'sale_mode' => env('DANTE_MPESA_SALE_MODE', 'log'),

    // true  = a business with no Daraja details of its own uses the .env credentials (single-business installs)
    // false = every business must enter its own details (use this when hosting several businesses)
    'allow_env_fallback' => env('DANTE_MPESA_ENV_FALLBACK', true),
];
