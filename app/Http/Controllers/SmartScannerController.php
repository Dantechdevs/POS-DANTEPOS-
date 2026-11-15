<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\Category;
use App\Product;
use App\Services\ProductLookupService;
use App\Transaction;
use App\Unit;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use App\Variation;
use App\VariationLocationDetails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Smart Scanner: scan a barcode, then either register the product or add stock to it.
 *
 * Checkout is not touched. Everything saved here is an ordinary single product,
 * with stock added the same way the system's own opening-stock import adds it.
 */
class SmartScannerController extends Controller
{
    protected $productUtil;

    protected $moduleUtil;

    protected $lookup;

    public function __construct(ProductUtil $productUtil, ModuleUtil $moduleUtil, ProductLookupService $lookup)
    {
        $this->productUtil = $productUtil;
        $this->moduleUtil = $moduleUtil;
        $this->lookup = $lookup;
    }

    public function index(Request $request)
    {
        $this->authorizeUser();

        $business_id = $request->session()->get('user.business_id');
        $location = $this->shopLocation($business_id);

        $units = Unit::where('business_id', $business_id)
            ->orderBy('actual_name')
            ->get(['id', 'actual_name', 'short_name', 'allow_decimal']);

        $default_unit = $units->first(function ($unit) {
            return strtolower($unit->actual_name) === 'pieces';
        }) ?: $units->first();
        $default_unit_id = $default_unit ? $default_unit->id : null;

        $categories = Category::where('business_id', $business_id)
            ->where('category_type', 'product')
            ->where('parent_id', 0)
            ->orderBy('name')
            ->pluck('name', 'id');

        return view('smart_scanner.index', compact('units', 'default_unit_id', 'categories', 'location'));
    }

    /**
     * Step 1 after a scan: is it already ours? If not, ask the online database.
     */
    public function lookup(Request $request)
    {
        $this->authorizeUser();

        $business_id = $request->session()->get('user.business_id');
        $code = ProductLookupService::normalizeCode($request->input('code'));

        if ($code === '' || mb_strlen($code) > 50) {
            return response()->json(['status' => 'invalid', 'message' => 'Scan a barcode or type a code first.']);
        }

        $location = $this->shopLocation($business_id);
        if (! $location) {
            return response()->json(['status' => 'invalid', 'message' => 'No business location found.']);
        }

        $own = $this->findOwnProduct($business_id, $code, $location->id);
        if ($own) {
            return response()->json(['status' => 'own', 'code' => $code] + $own);
        }

        $result = $this->lookup->find($code);

        if ($result['status'] === 'found') {
            return response()->json(['status' => 'found', 'code' => $code] + $result['data']);
        }

        if ($result['status'] === 'unavailable') {
            return response()->json([
                'status' => 'unavailable',
                'code' => $code,
                'message' => 'Could not reach the online product database. Type the product name yourself.',
            ]);
        }

        return response()->json([
            'status' => 'not_found',
            'code' => $code,
            'message' => 'Not in the online database. Type the product name and continue.',
        ]);
    }

    /**
     * Step 2: save. mode=register creates a new product with its stock,
     * mode=add_stock adds stock to a product that already exists.
     */
    public function save(Request $request)
    {
        $this->authorizeUser();

        $business_id = $request->session()->get('user.business_id');
        $location = $this->shopLocation($business_id);
        if (! $location) {
            return response()->json(['success' => 0, 'message' => 'No business location found.'], 422);
        }

        if (! $this->moduleUtil->isSubscribed($business_id)) {
            return response()->json(['success' => 0, 'message' => 'Your subscription has expired.'], 422);
        }

        return $request->input('mode') === 'add_stock'
            ? $this->addStock($request, $business_id, $location)
            : $this->registerProduct($request, $business_id, $location);
    }

    private function registerProduct(Request $request, $business_id, $location)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'unit_id' => 'required|integer',
            'category_id' => 'nullable|integer',
            'buying_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0.01',
            'quantity' => 'required|numeric|min:0',
            'image_url' => 'nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => 0, 'message' => $validator->errors()->first()], 422);
        }

        $code = ProductLookupService::normalizeCode($request->input('code'));
        $name = trim($request->input('name'));
        $buying = round((float) $request->input('buying_price'), 4);
        $selling = round((float) $request->input('selling_price'), 4);
        $quantity = (float) $request->input('quantity');

        if ($code === '' || $name === '') {
            return response()->json(['success' => 0, 'message' => 'Barcode and product name are required.'], 422);
        }

        if (! $this->moduleUtil->isQuotaAvailable('products', $business_id)) {
            return response()->json(['success' => 0, 'message' => 'Your plan has reached its product limit.'], 422);
        }

        $unit = Unit::where('business_id', $business_id)->find($request->input('unit_id'));
        if (! $unit) {
            return response()->json(['success' => 0, 'message' => 'Choose a unit.'], 422);
        }
        if (! $unit->allow_decimal && floor($quantity) != $quantity) {
            return response()->json(['success' => 0, 'message' => 'This unit does not allow decimals. Enter a whole number.'], 422);
        }

        $category_id = null;
        if ($request->filled('category_id')) {
            $category = Category::where('business_id', $business_id)->find($request->input('category_id'));
            $category_id = $category ? $category->id : null;
        }

        if ($this->findOwnProduct($business_id, $code, $location->id)) {
            return response()->json(['success' => 0, 'message' => 'This barcode is already registered. Scan it again to add stock.'], 422);
        }

        // Copy the picture first. If anything fails afterwards the file is removed again.
        $image = $this->lookup->downloadImage($request->input('image_url'), $code);

        try {
            DB::beginTransaction();

            $product = Product::create([
                'name' => $name,
                'business_id' => $business_id,
                'created_by' => $request->session()->get('user.id'),
                'type' => 'single',
                'unit_id' => $unit->id,
                'category_id' => $category_id,
                'sku' => $code,
                'barcode_type' => 'C128',
                'enable_stock' => 1,
                'tax' => null,
                'tax_type' => 'inclusive',
                'image' => $image,
            ]);

            $product->product_locations()->sync([$location->id]);

            $profit_percent = $buying > 0 ? round((($selling - $buying) / $buying) * 100, 2) : 0;

            // Same number formatting the importer uses, so separators never get misread.
            $this->productUtil->createSingleProductVariation(
                $product,
                $code,
                $this->productUtil->num_f($buying),
                $this->productUtil->num_f($buying),
                $this->productUtil->num_f($profit_percent),
                $this->productUtil->num_f($selling),
                $this->productUtil->num_f($selling)
            );

            if ($quantity > 0) {
                $variation = Variation::where('product_id', $product->id)->firstOrFail();
                $this->addStockLine($product, $variation, $quantity, $buying, $location->id, $business_id, $request->session()->get('user.id'));
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($image) {
                @unlink(public_path('uploads/'.config('constants.product_img_path').'/'.$image));
            }
            \Log::emergency('SmartScanner register. File:'.$e->getFile().' Line:'.$e->getLine().' Message:'.$e->getMessage());

            return response()->json(['success' => 0, 'message' => 'Could not save the product. Nothing was changed.'], 500);
        }

        return response()->json([
            'success' => 1,
            'message' => 'Saved: '.$name,
            'item' => ['name' => $name, 'code' => $code, 'quantity' => $quantity, 'action' => 'New'],
        ]);
    }

    private function addStock(Request $request, $business_id, $location)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50',
            'quantity' => 'required|numeric|min:0.0001',
            'buying_price' => 'required|numeric|min:0',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => 0, 'message' => $validator->errors()->first()], 422);
        }

        $code = ProductLookupService::normalizeCode($request->input('code'));
        $quantity = (float) $request->input('quantity');
        $buying = round((float) $request->input('buying_price'), 4);

        $own = $this->findOwnProduct($business_id, $code, $location->id);
        if (! $own || ! $own['can_add_stock']) {
            return response()->json(['success' => 0, 'message' => 'Stock cannot be added to this product here. Use Purchases.'], 422);
        }

        $product = Product::where('business_id', $business_id)->with('unit')->find($own['product_id']);
        $variation = Variation::find($own['variation_id']);
        if (! $product || ! $variation) {
            return response()->json(['success' => 0, 'message' => 'Product not found.'], 422);
        }

        if ($product->unit && ! $product->unit->allow_decimal && floor($quantity) != $quantity) {
            return response()->json(['success' => 0, 'message' => 'This product is sold in whole units. Enter a whole number.'], 422);
        }

        try {
            DB::beginTransaction();

            $product->product_locations()->syncWithoutDetaching([$location->id]);
            $this->addStockLine($product, $variation, $quantity, $buying, $location->id, $business_id, $request->session()->get('user.id'));

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::emergency('SmartScanner add stock. File:'.$e->getFile().' Line:'.$e->getLine().' Message:'.$e->getMessage());

            return response()->json(['success' => 0, 'message' => 'Could not add the stock. Nothing was changed.'], 500);
        }

        return response()->json([
            'success' => 1,
            'message' => 'Added '.$quantity.' to '.$product->name,
            'item' => ['name' => $product->name, 'code' => $code, 'quantity' => $quantity, 'action' => 'Stock added'],
        ]);
    }

    /**
     * Adds stock the way the system's own opening-stock import does: one opening-stock
     * transaction per product and location, with one purchase line per batch.
     */
    private function addStockLine($product, $variation, $quantity, $unit_cost, $location_id, $business_id, $user_id)
    {
        $transaction = Transaction::where('business_id', $business_id)
            ->where('location_id', $location_id)
            ->where('type', 'opening_stock')
            ->where('opening_stock_product_id', $product->id)
            ->first();

        $line_total = $quantity * $unit_cost;

        if (! $transaction) {
            $start = request()->session()->get('financial_year.start');
            $transaction_date = $start
                ? \Carbon::createFromFormat('Y-m-d', $start)->toDateTimeString()
                : now()->startOfYear()->toDateTimeString();

            $transaction = new Transaction();
            $transaction->type = 'opening_stock';
            $transaction->status = 'received';
            $transaction->opening_stock_product_id = $product->id;
            $transaction->business_id = $business_id;
            $transaction->transaction_date = $transaction_date;
            $transaction->location_id = $location_id;
            $transaction->payment_status = 'paid';
            $transaction->created_by = $user_id;
            $transaction->total_before_tax = 0;
            $transaction->final_total = 0;
        }
        $transaction->total_before_tax += $line_total;
        $transaction->final_total += $line_total;
        $transaction->save();

        $transaction->purchase_lines()->create([
            'product_id' => $product->id,
            'variation_id' => $variation->id,
            'quantity' => $quantity,
            'item_tax' => 0,
            'tax_id' => null,
            'pp_without_discount' => $unit_cost,
            'purchase_price' => $unit_cost,
            'purchase_price_inc_tax' => $unit_cost,
        ]);

        // $uf_data = false: the quantity is already a plain number, not a formatted one.
        $this->productUtil->updateProductQuantity($location_id, $product->id, $variation->id, $quantity, 0, null, false);
    }

    private function findOwnProduct($business_id, $code, $location_id): ?array
    {
        $row = Variation::join('products as p', 'variations.product_id', '=', 'p.id')
            ->where('p.business_id', $business_id)
            ->where(function ($query) use ($code) {
                $query->where('p.sku', $code)->orWhere('variations.sub_sku', $code);
            })
            ->select(
                'variations.id as variation_id',
                'variations.default_purchase_price',
                'variations.sell_price_inc_tax',
                'p.id as product_id',
                'p.name',
                'p.type',
                'p.image',
                'p.enable_stock'
            )
            ->first();

        if (! $row) {
            return null;
        }

        $stock = VariationLocationDetails::where('variation_id', $row->variation_id)
            ->where('location_id', $location_id)
            ->value('qty_available');

        return [
            'product_id' => $row->product_id,
            'variation_id' => $row->variation_id,
            'name' => $row->name,
            'product_type' => $row->type,
            'stock' => (float) $stock,
            'buying_price' => (float) $row->default_purchase_price,
            'selling_price' => (float) $row->sell_price_inc_tax,
            'image_url' => ! empty($row->image) ? asset('/uploads/'.config('constants.product_img_path').'/'.rawurlencode($row->image)) : null,
            'can_add_stock' => $row->type === 'single' && (int) $row->enable_stock === 1,
        ];
    }

    private function shopLocation($business_id)
    {
        return BusinessLocation::where('business_id', $business_id)->orderBy('id')->first();
    }

    private function authorizeUser()
    {
        if (! auth()->user()->can('product.create')) {
            abort(403, 'Unauthorized action.');
        }
    }
}
