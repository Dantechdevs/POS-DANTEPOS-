<?php

return [
    // How long a password confirmation unlocks the payment settings.
    'reauth_minutes' => (int) env('DANTE_REAUTH_MINUTES', 15),
    // True only on a dedicated demo install with its own database.
    'demo_mode' => (bool) env('DANTE_DEMO_MODE', false),
    'demo_ids_csv' => (string) env('DANTE_DEMO_BUSINESS_IDS', ''),
];
