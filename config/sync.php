<?php

return [
    // standalone = normal single install | primary = the live server | satellite = an offline-capable PC
    'role' => env('DANTE_ROLE', 'standalone'),
    'device_id' => env('DANTE_DEVICE_ID'),
    'device_token' => env('DANTE_DEVICE_TOKEN'),
    'server_url' => env('DANTE_SYNC_URL'),
];
