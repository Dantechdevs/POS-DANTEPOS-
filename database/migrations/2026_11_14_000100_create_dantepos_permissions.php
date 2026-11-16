<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private $names = [
        'dantepos.settings',
        'price.below_cost',
        'credit.override',
        'giftcard.manage',
        'mpesa.reconcile',
        'stocktake.manage',
    ];

    public function up()
    {
        foreach ($this->names as $name) {
            $exists = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->exists();
            if (! $exists) {
                DB::table('permissions')->insert([
                    'name' => $name,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down()
    {
        // Permissions are left in place on purpose, because roles may already use them.
    }
};
