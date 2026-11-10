<?php

return [
    // log   = flag unconfirmed M-Pesa sale payments, never reject a sale
    // block = reject unconfirmed M-Pesa sale payments unless within the manual limit
    // off   = no checking
    'sale_mode' => env('DANTE_MPESA_SALE_MODE', 'log'),
];
