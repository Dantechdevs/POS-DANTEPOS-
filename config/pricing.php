<?php

return [
    // warn = log below-cost sales but allow them; block = reject unless the user has price.below_cost
    'guard_mode' => env('PRICE_GUARD_MODE', 'warn'),
];
