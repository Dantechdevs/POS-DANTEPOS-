<?php

return [
    'enabled' => env('ETIMS_ENABLED', false),
    'env' => env('ETIMS_ENV', 'sandbox'),
    'base_url' => env('ETIMS_BASE_URL'),      // OSCU address supplied by KRA
    'tin' => env('ETIMS_TIN'),                // your KRA PIN
    'branch_id' => env('ETIMS_BRANCH_ID', '00'),
    'cmc_key' => env('ETIMS_CMC_KEY'),        // communication key from device init
    'item_class' => env('ETIMS_ITEM_CLASS'),  // default item classification code
    'zero_class' => env('ETIMS_ZERO_CLASS', 'C'), // tax class for 0% items: A exempt, C zero-rated, D non-VAT
    'max_attempts' => 5,

    // VAT rate (%) => KRA tax class. B = 16%, E = 8%
    'tax_map' => ['16' => 'B', '8' => 'E'],
];
