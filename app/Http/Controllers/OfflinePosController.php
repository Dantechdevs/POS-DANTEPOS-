<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OfflinePosController extends Controller
{
    private const MAX_SALES_PER_SYNC = 20;

    private function authorizePos(): void
    {
        if (! auth()->user()->can('sell.create')) {
            abort(403, 'Unauthorized action.');
        }
    }

    private function businessId(Request $request): int
    {
        return (int) $request->session()->get('user.business_id');
    }

    /** @return array{0:int,1:string} */
    private function pickLocation(Request $request): array
    {
        $locations = BusinessLocation::forDropdown($this->businessId($request))->toArray();
        $wanted = (int) $request->input('location_id');
        $id = array_key_exists($wanted, $locations) ? $wanted : (int) array_key_first($locations);

        abort_if(! $id, 403, 'No business location is available for your user.');

        return [$id, (string) $locations[$id]];
    }

    public function page(Request $request)
    {
        $this->authorizePos();
        [$locationId, $locationName] = $this->pickLocation($request);

        $user = auth()->user();
        $till = (string) config('services.offline_pos.till_method');

        $cfg = [
            'base' => $request->getBasePath(),
            'locationId' => $locationId,
            'locationName' => $locationName,
            'userName' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
            'currency' => $request->session()->get('currency.symbol', 'KSh'),
            'tillEnabled' => $till !== '',
            'tillLabel' => (string) config('services.offline_pos.till_label', 'M-Pesa Till'),
            'catalogUrl' => url('/offline/catalog'),
            'syncUrl' => url('/offline/sync'),
            'tokenUrl' => url('/offline/token'),
        ];

        return view('offline.pos', compact('cfg'));
    }

    public function token()
    {
        $this->authorizePos();

        return response()->json(['token' => csrf_token()]);
    }

    public function catalog(Request $request)
    {
        $this->authorizePos();
        $bid = $this->businessId($request);
        [$locationId, $locationName] = $this->pickLocation($request);

        $rows = DB::table('product_locations as pl')
            ->join('products as p', 'p.id', '=', 'pl.product_id')
            ->join('variations as v', 'v.product_id', '=', 'p.id')
            ->leftJoin('variation_location_details as vld', function ($join) use ($locationId) {
                $join->on('vld.variation_id', '=', 'v.id')->where('vld.location_id', '=', $locationId);
            })
            ->where('pl.location_id', $locationId)
            ->where('p.business_id', $bid)
            ->where('p.is_inactive', 0)
            ->where('p.not_for_selling', 0)
            ->whereIn('p.type', ['single', 'variable'])
            ->whereNull('v.deleted_at')
            ->orderBy('p.name')
            ->get([
                'p.id as product_id', 'v.id as variation_id', 'p.name', 'p.type', 'v.name as variation_name',
                'v.sub_sku', 'p.enable_stock', 'p.unit_id', 'p.tax as tax_id',
                'v.default_sell_price', 'v.sell_price_inc_tax', 'vld.qty_available',
            ]);

        $products = $rows->map(function ($r) {
            $ex = (float) $r->default_sell_price;
            $inc = (float) $r->sell_price_inc_tax;

            return [
                'variation_id' => (int) $r->variation_id,
                'product_id' => (int) $r->product_id,
                'name' => $r->type === 'variable' ? $r->name.' - '.$r->variation_name : $r->name,
                'sku' => (string) $r->sub_sku,
                'product_type' => $r->type,
                'enable_stock' => (int) $r->enable_stock,
                'unit_id' => $r->unit_id ? (int) $r->unit_id : null,
                'tax_id' => $r->tax_id ? (int) $r->tax_id : null,
                'unit_price' => round($ex, 4),
                'unit_price_inc_tax' => round($inc > 0 ? $inc : $ex, 4),
                'stock' => $r->enable_stock ? (float) $r->qty_available : null,
            ];
        })->values()->all();

        $customers = DB::table('contacts')
            ->where('business_id', $bid)
            ->whereIn('type', ['customer', 'both'])
            ->where(function ($q) {
                $q->whereNull('contact_status')->orWhere('contact_status', 'active');
            })
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->limit(5000)
            ->get(['id', 'name', 'supplier_business_name', 'mobile', 'is_default'])
            ->map(fn ($c) => [
                'id' => (int) $c->id,
                'name' => trim($c->name.($c->supplier_business_name ? ' ('.$c->supplier_business_name.')' : '')),
                'mobile' => (string) $c->mobile,
                'is_default' => (int) $c->is_default,
            ])->all();

        $business = DB::table('business')->where('id', $bid)->first(['name', 'tax_label_1', 'tax_number_1']);
        $loc = DB::table('business_locations')->where('id', $locationId)->first();

        $footer = null;
        if ($loc && $loc->invoice_layout_id) {
            $footer = DB::table('invoice_layouts')->where('id', $loc->invoice_layout_id)->value('footer_text');
        }
        if (! $footer) {
            $footer = DB::table('invoice_layouts')->where('business_id', $bid)->where('is_default', 1)->value('footer_text');
        }

        $address = $loc ? implode(', ', array_filter([$loc->landmark, $loc->city, $loc->state])) : '';

        return response()->json([
            'products' => $products,
            'customers' => $customers,
            'business' => [
                'name' => $business->name ?? '',
                'location' => $locationName,
                'address' => $address,
                'mobile' => $loc->mobile ?? '',
                'tax_label' => $business->tax_label_1 ?? '',
                'tax_number' => $business->tax_number_1 ?? '',
                'footer' => trim(strip_tags((string) $footer)),
            ],
            'currency' => $request->session()->get('currency.symbol', 'KSh'),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function sync(Request $request)
    {
        $this->authorizePos();

        $sales = $request->input('sales');

        if (! is_array($sales) || empty($sales)) {
            return response()->json(['results' => []]);
        }

        $results = [];

        foreach (array_slice($sales, 0, self::MAX_SALES_PER_SYNC) as $sale) {
            $results[] = $this->syncOne($request, is_array($sale) ? $sale : []);
        }

        return response()->json(['results' => $results]);
    }

    private function syncOne(Request $request, array $sale): array
    {
        $uuid = (string) ($sale['uuid'] ?? '');

        if (! preg_match('/^[A-Za-z0-9-]{8,64}$/', $uuid)) {
            return ['uuid' => $uuid, 'ok' => false, 'message' => 'Invalid sale id.'];
        }

        $bid = $this->businessId($request);
        $userId = (int) $request->session()->get('user.id');
        $marker = 'OFFLINE '.$uuid;

        $claimed = DB::table('offline_sales')->insertOrIgnore([
            'client_uuid' => $uuid,
            'business_id' => $bid,
            'user_id' => $userId,
            'status' => 'processing',
            'payload' => json_encode($sale),
            'client_time' => $this->clientTime($sale),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($claimed === 0) {
            $existing = DB::table('offline_sales')->where('client_uuid', $uuid)->first();

            if (! $existing || (int) $existing->business_id !== $bid) {
                return ['uuid' => $uuid, 'ok' => false, 'message' => 'This sale id belongs to another business.'];
            }

            if ($existing->status === 'done') {
                return ['uuid' => $uuid, 'ok' => true, 'invoice_no' => $existing->invoice_no, 'message' => 'Already synced.'];
            }

            if ($existing->status === 'processing' && Carbon::parse($existing->updated_at)->gt(now()->subMinutes(2))) {
                return ['uuid' => $uuid, 'ok' => false, 'message' => 'Still being processed. Try again in a minute.'];
            }

            DB::table('offline_sales')->where('client_uuid', $uuid)->update([
                'status' => 'processing', 'error' => null, 'payload' => json_encode($sale), 'updated_at' => now(),
            ]);
        }

        try {
            // A previous attempt may have saved the sale before failing later: never create it twice.
            $already = DB::table('transactions')->where('business_id', $bid)->where('type', 'sell')->where('staff_note', $marker)->first();

            if (! $already) {
                $payload = $this->buildPayload($request, $sale, $marker, $bid);

                $fake = Request::create('/pos', 'POST', $payload);
                $fake->headers->set('X-Requested-With', 'XMLHttpRequest'); // guards then answer with JSON
                $fake->setLaravelSession($request->session());
                $fake->setUserResolver(fn () => $request->user());

                $result = app(SellPosController::class)->store($fake);

                if ($result instanceof \Illuminate\Http\JsonResponse) {
                    $result = $result->getData(true);
                }

                if (! is_array($result)) {
                    // A guard redirected with a flashed message: show that instead of guessing.
                    $flash = $request->session()->pull('status');

                    throw new \RuntimeException(
                        is_array($flash) && ! empty($flash['msg'])
                            ? $flash['msg']
                            : 'Open a cash register first (Sell > POS), then sync again.'
                    );
                }

                if (empty($result['success'])) {
                    throw new \RuntimeException($result['msg'] ?? 'The sale was rejected.');
                }

                $already = DB::table('transactions')->where('business_id', $bid)->where('type', 'sell')->where('staff_note', $marker)->orderByDesc('id')->first();
            }

            if (! $already) {
                throw new \RuntimeException('The sale was accepted but could not be found afterwards. Check Sell > All Sales before retrying.');
            }

            $this->applyClientTime($request, $already->id, $sale);

            DB::table('offline_sales')->where('client_uuid', $uuid)->update([
                'status' => 'done', 'transaction_id' => $already->id, 'invoice_no' => $already->invoice_no, 'error' => null, 'updated_at' => now(),
            ]);

            return ['uuid' => $uuid, 'ok' => true, 'invoice_no' => $already->invoice_no, 'message' => 'Synced.'];
        } catch (\Throwable $e) {
            Log::error('Offline sale '.$uuid.' failed: '.$e->getMessage().' @'.$e->getFile().':'.$e->getLine());

            DB::table('offline_sales')->where('client_uuid', $uuid)->update([
                'status' => 'failed', 'error' => substr($e->getMessage(), 0, 1000), 'updated_at' => now(),
            ]);

            return ['uuid' => $uuid, 'ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function clientTime(array $sale): ?string
    {
        try {
            return Carbon::parse($sale['created_at'] ?? null)->toDateTimeString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function applyClientTime(Request $request, int $transactionId, array $sale): void
    {
        try {
            $tz = $request->session()->get('business.time_zone', config('app.timezone'));
            $when = Carbon::parse($sale['created_at'])->setTimezone($tz);

            if ($when->isFuture()) {
                $when = Carbon::now($tz);
            }

            $stamp = $when->toDateTimeString();

            DB::table('transactions')->where('id', $transactionId)->update(['transaction_date' => $stamp]);
            DB::table('transaction_payments')->where('transaction_id', $transactionId)->update(['paid_on' => $stamp]);
        } catch (\Throwable $e) {
            // Keep the server time if the device time cannot be read.
        }
    }

    /**
     * Turn the compact offline sale into the same form fields the POS screen posts.
     */
    private function buildPayload(Request $request, array $sale, string $marker, int $bid): array
    {
        $currency = $request->session()->get('currency', []);
        $decimal = $currency['decimal_separator'] ?? '.';
        $n = fn ($v) => number_format((float) $v, 4, $decimal, '');

        $locations = BusinessLocation::forDropdown($bid)->toArray();
        $locationId = (int) ($sale['location_id'] ?? 0);

        if (! array_key_exists($locationId, $locations)) {
            throw new \RuntimeException('You do not have access to the location of this sale.');
        }

        // Customer
        $contactId = (int) ($sale['contact_id'] ?? 0);
        $contact = $contactId
            ? DB::table('contacts')->where('business_id', $bid)->where('id', $contactId)->first()
            : DB::table('contacts')->where('business_id', $bid)->where('is_default', 1)->first();

        if (! $contact) {
            throw new \RuntimeException('Customer not found. Refresh the catalog and sell again.');
        }

        // Items: ids and stock flags come from the database, prices from the device.
        $lines = $sale['lines'] ?? [];

        if (! is_array($lines) || empty($lines)) {
            throw new \RuntimeException('The sale has no items.');
        }

        $variationIds = array_values(array_unique(array_map(fn ($l) => (int) ($l['variation_id'] ?? 0), $lines)));

        $known = DB::table('variations as v')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->where('p.business_id', $bid)
            ->whereIn('v.id', $variationIds)
            ->get(['v.id as variation_id', 'p.id as product_id', 'p.enable_stock', 'p.type', 'p.unit_id', 'p.tax as tax_id'])
            ->keyBy('variation_id');

        if ($known->count() !== count($variationIds)) {
            throw new \RuntimeException('Some products no longer exist. Refresh the catalog.');
        }

        $products = [];
        $total = 0.0;

        foreach (array_values($lines) as $i => $l) {
            $info = $known[(int) $l['variation_id']];
            $qty = round((float) ($l['qty'] ?? 0), 4);
            $ex = round((float) ($l['unit_price'] ?? 0), 4);
            $inc = round((float) ($l['unit_price_inc_tax'] ?? 0), 4);

            if ($qty <= 0 || $ex < 0 || $inc < 0) {
                throw new \RuntimeException('An item has an invalid quantity or price.');
            }

            $products[$i] = [
                'product_type' => $info->type === 'variable' ? 'variable' : 'single',
                'sell_line_note' => '',
                'product_id' => (int) $info->product_id,
                'variation_id' => (int) $info->variation_id,
                'enable_stock' => (int) $info->enable_stock,
                'quantity' => $n($qty),
                'product_unit_id' => $info->unit_id ?: null,
                'base_unit_multiplier' => 1,
                'unit_price' => $n($ex),
                'line_discount_type' => 'fixed',
                'line_discount_amount' => $n(0),
                'item_tax' => $n($inc - $ex),
                'tax_id' => $info->tax_id ?: null,
                'unit_price_inc_tax' => $n($inc),
            ];

            $total += $qty * $inc;
        }

        $discount = min(max(0, round((float) ($sale['discount'] ?? 0), 2)), $total);
        $final = round($total - $discount, 2);

        // Payment
        $p = is_array($sale['payment'] ?? null) ? $sale['payment'] : [];
        $method = (string) ($p['method'] ?? 'cash');
        $payment = [];
        $change = 0.0;
        $isCredit = false;

        if ($method === 'cash') {
            $tendered = round((float) ($p['tendered'] ?? $final), 2);

            if ($tendered < $final - 0.009) {
                throw new \RuntimeException('Cash received was less than the total.');
            }

            $change = round($tendered - $final, 2);
            $payment[0] = ['amount' => $n($tendered), 'method' => 'cash', 'note' => 'Offline sale'];
        } elseif ($method === 'till') {
            $tillMethod = (string) config('services.offline_pos.till_method');
            $code = trim((string) ($p['code'] ?? ''));

            if ($tillMethod === '' || ! preg_match('/^custom_pay_[1-7]$/', $tillMethod)) {
                throw new \RuntimeException('M-Pesa Till payments are not set up on the server.');
            }

            if ($code === '') {
                throw new \RuntimeException('The M-Pesa transaction code is missing.');
            }

            $payment[0] = [
                'amount' => $n($final),
                'method' => $tillMethod,
                'note' => 'Offline sale',
                'transaction_no_'.substr($tillMethod, -1) => $code,
            ];
        } elseif ($method === 'credit') {
            if ((int) $contact->is_default === 1) {
                throw new \RuntimeException('Credit sales need a named customer, not Walk-In.');
            }

            $isCredit = true;
        } else {
            throw new \RuntimeException('Unknown payment method.');
        }

        return [
            'location_id' => $locationId,
            'sell_price_tax' => 'includes',
            'price_group' => 0,
            'contact_id' => $contact->id,
            'products' => $products,
            'discount_type' => 'fixed',
            'discount_amount' => $n($discount),
            'rp_redeemed' => 0,
            'rp_redeemed_amount' => $n(0),
            'tax_rate_id' => '',
            'tax_calculation_amount' => $n(0),
            'shipping_details' => '',
            'shipping_address' => '',
            'shipping_status' => '',
            'delivered_to' => '',
            'shipping_charges' => $n(0),
            'payment' => $payment,
            'change_return' => $n($change),
            'is_credit_sale' => $isCredit ? 1 : 0,
            'sale_note' => '',
            'staff_note' => $marker,
            'final_total' => $n($final),
            'status' => 'final',
            'is_suspend' => 0,
            'exchange_rate' => 1,
            'pay_term_number' => '',
            'pay_term_type' => '',
        ];
    }
}
