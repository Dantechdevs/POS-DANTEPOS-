<?php

return [
    // How long a password confirmation unlocks the payment settings.
    'reauth_minutes' => (int) env('DANTE_REAUTH_MINUTES', 15),
];
