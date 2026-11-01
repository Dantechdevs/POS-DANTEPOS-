<?php

return [
    'mysqldump' => env('BACKUP_MYSQLDUMP', 'C:\\xampp\\mysql\\bin\\mysqldump.exe'),
    'path' => env('BACKUP_PATH'),               // default: storage/app/backups
    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),
    'copy_path' => env('BACKUP_COPY_PATH'),     // optional second location, e.g. E:\dantepos-backups
    'prefix' => 'dantepos',
];
