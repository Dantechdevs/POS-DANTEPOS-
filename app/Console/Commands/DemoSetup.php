<?php

namespace App\Console\Commands;

use App\Business;
use App\User;
use App\Utils\BusinessUtil;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DemoSetup extends Command
{
    protected $signature = 'demo:setup {--same-database : Create the demo next to real businesses} {--password= : Password for the public demo login}';

    protected $description = 'Create the DantePOS demo business, its demo login and fake payment settings';

    private const NAME = 'DantePOS Demo';

    private const PAYMENT_KEYS = [
        'cash', 'card', 'cheque', 'bank_transfer', 'mpesa', 'gift_card', 'other',
        'custom_pay_1', 'custom_pay_2', 'custom_pay_3', 'custom_pay_4', 'custom_pay_5', 'custom_pay_6', 'custom_pay_7',
    ];

    // Permissions the demo login should have. Any name that does not exist in this install is skipped and listed.
    // dantepos.settings is deliberately NOT here: that page is global and the demo login is public.
    private const DEMO_PERMISSIONS = [
        'access_all_locations', 'dashboard.data',
        'sell.view', 'sell.create', 'direct_sell.access', 'view_cash_register',
        'product.view', 'product.create', 'supplier.view', 'supplier.create', 'customer.view', 'customer.create',
        'purchase.view', 'purchase.create', 'view_purchase_price', 'expense.access', 'account.access', 'tax_rate.view',
        'profit_loss_report.view', 'purchase_n_sell_report.view', 'contacts_report.view', 'stock_report.view',
        'trending_product_report.view', 'register_report.view', 'sales_representative.view', 'expense_report.view',
    ];

    public function handle(BusinessUtil $businessUtil): int
    {
        $existing = Business::where('name', self::NAME)->first();
        if ($existing) {
            $this->info('The demo business already exists.');
            $this->line('DEMO_BUSINESS_ID='.$existing->id);

            return 0;
        }

        $others = Business::count();
        if ($others === 0 && ! config('dante.demo_mode')) {
            $this->error('This is an empty install. Set DANTE_DEMO_MODE=true in .env first, so a real install is never turned into a demo by accident.');

            return 1;
        }
        if ($others > 0 && ! $this->option('same-database')) {
            $this->error('Other businesses exist in this database. A public demo belongs in its own database. To create it here anyway, run: php artisan demo:setup --same-database');

            return 1;
        }
        if (User::where('username', 'demo')->exists()) {
            $this->error('A user named "demo" already exists. Nothing was created.');

            return 1;
        }

        $currencyId = DB::table('currencies')->where('code', 'KES')->value('id');
        if (! $currencyId) {
            $this->error('Currency KES was not found in the currencies table. Nothing was created.');

            return 1;
        }

        $password = (string) ($this->option('password') ?: 'DemoPOS-2026');

        DB::beginTransaction();
        try {
            // Hidden owner: owns the business and holds the Admin role, nobody logs in as this user.
            $owner = User::create_user([
                'surname' => '',
                'first_name' => 'Demo',
                'last_name' => 'Owner',
                'username' => 'demo_owner_'.Str::lower(Str::random(6)),
                'email' => null,
                'password' => Str::random(32),
                'language' => config('app.locale'),
            ]);

            $business = $businessUtil->createNewBusiness([
                'name' => self::NAME,
                'start_date' => null,
                'currency_id' => $currencyId,
                'time_zone' => 'Africa/Nairobi',
                'fy_start_month' => 1,
                'accounting_method' => 'fifo',
                'tax_label_1' => null,
                'tax_number_1' => null,
                'tax_label_2' => null,
                'tax_number_2' => null,
                'owner_id' => $owner->id,
                'enabled_modules' => ['purchases', 'add_sale', 'pos_sale', 'stock_transfers', 'stock_adjustment', 'expenses'],
            ]);

            $owner->business_id = $business->id;
            $owner->save();

            $businessUtil->newBusinessDefaultResources($business->id, $owner->id);

            $location = $businessUtil->addLocation($business->id, [
                'name' => self::NAME,
                'country' => 'Kenya',
                'state' => 'Nairobi',
                'city' => 'Nairobi',
                'zip_code' => '00100',
                'landmark' => 'Demo store (not a real shop)',
                'website' => null,
                'mobile' => null,
                'alternate_number' => null,
            ]);
            Permission::firstOrCreate(['name' => 'location.'.$location->id, 'guard_name' => 'web']);

            // Limited role and the public demo login.
            $have = Permission::whereIn('name', self::DEMO_PERMISSIONS)->pluck('name')->all();
            $missing = array_values(array_diff(self::DEMO_PERMISSIONS, $have));

            $role = Role::create([
                'name' => 'Demo#'.$business->id,
                'guard_name' => 'web',
                'business_id' => $business->id,
                'is_default' => 0,
            ]);
            $role->syncPermissions($have);

            $demo = User::create_user([
                'surname' => '',
                'first_name' => 'Demo',
                'last_name' => 'User',
                'username' => 'demo',
                'email' => null,
                'password' => $password,
                'language' => config('app.locale'),
            ]);
            $demo->business_id = $business->id;
            $demo->save();
            $demo->assignRole($role->name);

            // Every payment type switched on for the location, then narrowed by the Dante payment config.
            $accounts = [];
            foreach (self::PAYMENT_KEYS as $k) {
                $accounts[$k] = ['is_enabled' => 1, 'account' => null];
            }
            DB::table('business_locations')->where('id', $location->id)
                ->update(['default_payment_accounts' => json_encode($accounts)]);

            $enabled = ['cash', 'mpesa', 'gift_card'];
            DB::table('dantepos_payment_config')->updateOrInsert(
                ['business_id' => $business->id],
                [
                    'config' => json_encode([
                        'labels' => [],
                        'disabled' => array_values(array_diff(self::PAYMENT_KEYS, $enabled)),
                        'default' => 'cash',
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // Obviously fake M-Pesa details. Encrypted like real ones so the page behaves the same.
            DB::table('mpesa_settings')->updateOrInsert(
                ['business_id' => $business->id],
                [
                    'account_type' => 'till',
                    'shortcode' => '654321',
                    'till_number' => '123456',
                    'stk_shortcode' => '654321',
                    'account_hint' => '',
                    'manual_enabled' => 1,
                    'manual_unverified_cap' => 5000,
                    'daraja_env' => 'sandbox',
                    'transaction_type' => 'CustomerBuyGoodsOnline',
                    'consumer_key_enc' => Crypt::encryptString('DEMO-FAKE-KEY'),
                    'consumer_secret_enc' => Crypt::encryptString('DEMO-FAKE-SECRET'),
                    'passkey_enc' => Crypt::encryptString('DEMO-FAKE-PASSKEY'),
                    'callback_secret' => Str::random(40),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            DB::table('dantepos_bank_accounts')->insert([
                [
                    'business_id' => $business->id,
                    'bank_name' => 'Sample Bank Kenya',
                    'branch_name' => 'Nairobi CBD',
                    'account_name' => 'DantePOS Demo Ltd',
                    'account_number' => '0000000001',
                    'branch_code' => '000',
                    'swift_code' => 'DEMOKENA',
                    'currency' => 'KES',
                    'instructions' => 'Demo details only. Do not send money.',
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'business_id' => $business->id,
                    'bank_name' => 'Example Co-operative Bank',
                    'branch_name' => 'Westlands',
                    'account_name' => 'DantePOS Demo Ltd',
                    'account_number' => '0000000002',
                    'branch_code' => '000',
                    'swift_code' => 'DEMOKENB',
                    'currency' => 'KES',
                    'instructions' => 'Demo details only. Do not send money.',
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Nothing was created. '.$e->getMessage());
            $this->line('at '.basename($e->getFile()).':'.$e->getLine());

            return 1;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info('Demo business created.');
        $this->line('Login: demo / '.$password);
        if ($missing) {
            $this->warn('Permissions not found in this install (skipped): '.implode(', ', $missing));
        }
        $this->line('Not seeded yet: run php artisan demo:seed for products, customers and sales.');
        $this->line('DEMO_BUSINESS_ID='.$business->id);

        return 0;
    }
}
