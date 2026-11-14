<?php

namespace App\Console\Commands;

use App\Business;
use App\BusinessLocation;
use App\Category;
use App\Contact;
use App\Product;
use App\Services\DemoGuard;
use App\Transaction;
use App\User;
use App\Utils\BusinessUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DemoSeed extends Command
{
    protected $signature = 'demo:seed {--rollback : Run everything, print the counts, then undo it}';

    protected $description = 'Fill the DantePOS Demo business with sample products, customers and sales';

    private const NAME = 'DantePOS Demo';

    // name, category, cost, price, opening stock
    private const PRODUCTS = [
        ['Maize flour 2kg', 'Groceries', 150, 175, 120],
        ['Sugar 1kg', 'Groceries', 140, 165, 100],
        ['Cooking oil 1L', 'Groceries', 260, 310, 80],
        ['Rice 1kg', 'Groceries', 190, 230, 90],
        ['Tea leaves 250g', 'Groceries', 95, 120, 70],
        ['Bread 400g', 'Groceries', 55, 65, 60],
        ['Fresh milk 500ml', 'Groceries', 50, 60, 90],
        ['Eggs tray of 30', 'Groceries', 420, 480, 40],
        ['Salt 500g', 'Groceries', 25, 35, 110],
        ['Spaghetti 400g', 'Groceries', 75, 95, 80],
        ['Laundry detergent 1kg', 'Household', 230, 280, 50],
        ['Bar soap 800g', 'Household', 140, 170, 60],
        ['Toilet paper 4-pack', 'Household', 160, 200, 55],
        ['Bathing soap', 'Household', 45, 60, 100],
        ['Toothpaste 100ml', 'Household', 110, 140, 70],
        ['Soda 500ml', 'Drinks', 40, 55, 150],
        ['Bottled water 1L', 'Drinks', 30, 45, 140],
        ['Juice 1L', 'Drinks', 120, 150, 60],
        ['Energy drink 300ml', 'Drinks', 70, 100, 80],
        ['Exercise book 96 pages', 'Stationery & electronics', 30, 45, 120],
        ['Ballpoint pens box of 20', 'Stationery & electronics', 150, 200, 40],
        ['Phone charger USB-C', 'Stationery & electronics', 350, 500, 30],
        ['Earphones', 'Stationery & electronics', 250, 400, 30],
        ['AA batteries 4-pack', 'Stationery & electronics', 120, 180, 50],
        ['Power bank 10000mAh', 'Stationery & electronics', 1200, 1700, 15],
    ];

    private const CUSTOMERS = [
        'Demo Mama Mboga', 'Demo Hardware Store', 'Demo Salon', 'Demo Boda Boda Stage', 'Demo School Canteen',
        'Demo Kiosk', 'Demo Chama Group', 'Demo Church Office', 'Demo Clinic', 'Demo Cyber Cafe',
    ];

    public function handle(ProductUtil $pu, TransactionUtil $tu, BusinessUtil $bu): int
    {
        $business = Business::where('name', self::NAME)->first();
        if (! $business) {
            $this->error('The demo business does not exist. Run demo:setup first.');

            return 1;
        }
        $bid = $business->id;

        if (! in_array($bid, DemoGuard::ids(), true)) {
            $this->error("Business $bid is not in DANTE_DEMO_BUSINESS_IDS. Nothing was created.");

            return 1;
        }
        foreach (['app/Console/Commands/SendDebtReminders.php', 'app/Console/Commands/SendDailySummary.php'] as $f) {
            if (strpos((string) @file_get_contents(base_path($f)), 'DemoGuard') === false) {
                $this->error("$f does not exclude demo businesses yet. Apply the demo guard patch first. Nothing was created.");

                return 1;
            }
        }
        foreach ([[TransactionUtil::class, 'createSellTransaction', 4], [TransactionUtil::class, 'createOrUpdateSellLines', 6], [TransactionUtil::class, 'createOrUpdatePaymentLines', 4]] as [$c, $m, $i]) {
            $params = (new \ReflectionMethod($c, $m))->getParameters();
            if (! isset($params[$i]) || $params[$i]->getName() !== 'uf_data') {
                $this->error("$m does not have the uf_data parameter where expected. Nothing was created. Paste this message.");

                return 1;
            }
        }
        if (Product::where('business_id', $bid)->exists()) {
            $this->error('The demo business already has products. Nothing was created.');

            return 1;
        }

        $owner = User::where('business_id', $bid)->where('username', 'like', 'demo_owner_%')->first();
        $location = BusinessLocation::where('business_id', $bid)->first();
        $walkin = Contact::where('business_id', $bid)->where('is_default', 1)->first();
        $unitId = DB::table('units')->where('business_id', $bid)->value('id');
        if (! $owner || ! $location || ! $walkin || ! $unitId) {
            $this->error('The demo business is missing its owner, location, Walk-In customer or unit. Nothing was created.');

            return 1;
        }
        $uid = $owner->id;
        $locId = $location->id;

        // Nothing may leave the building while seeding.
        config(['africastalking.api_key' => null, 'africastalking.username' => null, 'mail.default' => 'array']);

        // The utilities read the logged-in user, business and currency from the session.
        $currency = $business->currency;
        $store = app('session.store');
        $store->put('user', ['id' => $uid, 'business_id' => $bid, 'first_name' => $owner->first_name, 'last_name' => $owner->last_name, 'language' => $owner->language]);
        $store->put('business', $business);
        $store->put('currency', ['id' => $currency->id, 'code' => $currency->code, 'symbol' => $currency->symbol, 'thousand_separator' => $currency->thousand_separator, 'decimal_separator' => $currency->decimal_separator]);
        request()->setLaravelSession($store);
        Auth::setUser($owner);

        mt_srand(20261110);
        DB::beginTransaction();
        try {
            $catIds = [];
            foreach (array_unique(array_column(self::PRODUCTS, 1)) as $cat) {
                $catIds[$cat] = Category::create(['name' => $cat, 'business_id' => $bid, 'parent_id' => 0, 'created_by' => $uid, 'category_type' => 'product'])->id;
            }

            $openDate = Carbon::now()->subDays(35)->format('Y-m-d H:i:s');
            $catalog = [];
            $left = [];
            foreach (self::PRODUCTS as [$name, $cat, $cost, $price, $qty]) {
                $p = Product::create([
                    'name' => $name, 'business_id' => $bid, 'type' => 'single', 'unit_id' => $unitId,
                    'category_id' => $catIds[$cat], 'tax_type' => 'exclusive', 'enable_stock' => 1,
                    'alert_quantity' => 10, 'sku' => ' ', 'barcode_type' => 'C128', 'not_for_selling' => 0, 'created_by' => $uid,
                ]);
                $p->sku = $pu->generateProductSku($p->id);
                $p->save();

                $margin = round(($price - $cost) / $cost * 100, 2);
                $c2 = number_format($cost, 2, '.', '');
                $p2 = number_format($price, 2, '.', '');
                $pu->createSingleProductVariation($p->id, $p->sku, $c2, $c2, $margin, $p2, $p2);
                $p->product_locations()->sync([$locId]);

                $fresh = Product::with('variations')->find($p->id);
                $pu->addSingleProductOpeningStock($bid, $fresh, [$locId => ['purchase_price' => $c2, 'quantity' => (string) $qty]], $openDate, $uid);

                $catalog[$p->id] = ['vid' => $fresh->variations->first()->id, 'price' => $price];
                $left[$p->id] = $qty;
            }

            $stocked = DB::table('variation_location_details')->where('location_id', $locId)->whereIn('product_id', array_keys($catalog))->count();
            if ($stocked !== count($catalog)) {
                throw new \RuntimeException("Opening stock was recorded for $stocked of ".count($catalog).' products. Rolled back.');
            }

            $named = [];
            foreach (self::CUSTOMERS as $i => $cname) {
                $named[] = Contact::create([
                    'business_id' => $bid, 'type' => 'customer', 'name' => $cname,
                    'mobile' => sprintf('00000000%02d', $i + 1), 'created_by' => $uid, 'contact_status' => 'active',
                    'credit_limit' => 50000, 'pay_term_number' => 14, 'pay_term_type' => 'days',
                ])->id;
            }

            $bd = $bu->getDetails($bid);
            $pos = empty($bd->pos_settings) ? $bu->defaultPosSettings() : json_decode($bd->pos_settings, true);
            $biz = ['id' => $bid, 'accounting_method' => $business->accounting_method, 'location_id' => $locId, 'pos_settings' => $pos];

            $dates = [];
            for ($i = 0; $i < 40; $i++) {
                $dates[] = Carbon::now()->subDays(mt_rand(1, 29))->setTime(mt_rand(8, 19), mt_rand(0, 59), 0)->format('Y-m-d H:i:s');
            }
            sort($dates);
            $kinds = array_merge(array_fill(0, 7, 'credit'), array_fill(0, 3, 'partial'), array_fill(0, 14, 'mpesa'), array_fill(0, 16, 'cash'));
            shuffle($kinds);

            $made = 0;
            foreach ($dates as $i => $when) {
                $kind = $kinds[$i];
                $ids = array_keys($catalog);
                shuffle($ids);
                $lines = [];
                $total = 0;
                foreach (array_slice($ids, 0, mt_rand(1, 4)) as $pid) {
                    $q = mt_rand(1, 3);
                    if ($left[$pid] < $q) {
                        continue;
                    }
                    $price = (float) $catalog[$pid]['price'];
                    $lines[] = [
                        'product_id' => $pid, 'variation_id' => $catalog[$pid]['vid'], 'quantity' => $q,
                        'unit_price' => $price, 'unit_price_inc_tax' => $price, 'item_tax' => 0, 'tax_id' => null,
                        'line_discount_type' => 'fixed', 'line_discount_amount' => 0, 'sell_line_note' => '',
                        'enable_stock' => 1, 'product_unit_id' => $unitId, 'product_type' => 'single', 'base_unit_multiplier' => 1,
                    ];
                    $total += $q * $price;
                    $left[$pid] -= $q;
                }
                if (! $lines) {
                    continue;
                }
                $total = round($total, 2);
                $isCredit = in_array($kind, ['credit', 'partial'], true);
                $contactId = $isCredit ? $named[array_rand($named)] : (mt_rand(1, 10) <= 3 ? $named[array_rand($named)] : $walkin->id);

                $input = [
                    'location_id' => $locId, 'status' => 'final', 'contact_id' => $contactId, 'transaction_date' => $when,
                    'final_total' => $total, 'discount_type' => 'fixed', 'discount_amount' => 0, 'is_direct_sale' => 0, 'is_quotation' => 0,
                ];
                if ($isCredit) {
                    $input['pay_term_number'] = 14;
                    $input['pay_term_type'] = 'days';
                }

                $tx = $tu->createSellTransaction($bid, $input, ['total_before_tax' => $total, 'tax' => 0], $uid, false);
                $tu->createOrUpdateSellLines($tx, $lines, $locId, false, null, [], false);

                $payments = [];
                if ($kind === 'cash') {
                    $payments[] = ['amount' => $total, 'method' => 'cash', 'paid_on' => $when];
                } elseif ($kind === 'mpesa') {
                    $payments[] = ['amount' => $total, 'method' => 'mpesa', 'paid_on' => $when, 'mpesa_phone' => '254000000000', 'mpesa_receipt_number' => 'DEMO'.strtoupper(substr(md5($when.$i), 0, 6))];
                } elseif ($kind === 'partial') {
                    $payments[] = ['amount' => round($total * 0.4, 2), 'method' => 'cash', 'paid_on' => $when];
                }
                if ($payments) {
                    $tu->createOrUpdatePaymentLines($tx, $payments, $bid, $uid, false);
                }

                foreach ($lines as $l) {
                    $pu->decreaseProductQuantity($l['product_id'], $l['variation_id'], $locId, $l['quantity']);
                }
                $tx->payment_status = $tu->updatePaymentStatus($tx->id, $tx->final_total);
                $tx->save();
                $tu->mapPurchaseSell($biz, $tx->sell_lines, 'purchase');
                $made++;
            }

            $sells = DB::table('transactions')->where('business_id', $bid)->where('type', 'sell');
            $this->info('Products: '.count($catalog).' | customers: '.count($named).' | sales: '.$made);
            $this->line('Sales total: KSh '.number_format((float) (clone $sells)->sum('final_total'), 2));
            foreach ((clone $sells)->select('payment_status', DB::raw('COUNT(*) as n'))->groupBy('payment_status')->get() as $r) {
                $this->line('  '.$r->payment_status.': '.$r->n);
            }
            foreach (DB::table('transaction_payments')->where('business_id', $bid)->select('method', DB::raw('COUNT(*) as n'))->groupBy('method')->get() as $r) {
                $this->line('  payments '.$r->method.': '.$r->n);
            }

            if ($this->option('rollback')) {
                DB::rollBack();
                $this->warn('Rolled back as requested. Nothing was kept.');
            } else {
                DB::commit();
                $this->info('Demo data saved.');
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Nothing was created. '.$e->getMessage());
            $this->line('at '.basename($e->getFile()).':'.$e->getLine());

            return 1;
        }

        return 0;
    }
}
