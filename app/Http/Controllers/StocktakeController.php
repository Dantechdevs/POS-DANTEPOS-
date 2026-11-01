<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\Events\StockAdjustmentCreatedOrModified;
use App\PurchaseLine;
use App\Transaction;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StocktakeController extends Controller
{
    protected $productUtil;
    protected $transactionUtil;

    public function __construct(ProductUtil $productUtil, TransactionUtil $transactionUtil)
    {
        $this->productUtil = $productUtil;
        $this->transactionUtil = $transactionUtil;
    }

    private function authorizeAccess(): void
    {
        if (! auth()->user()->can('stocktake.manage')) {
            abort(403, 'Unauthorized action.');
        }
    }

    private function businessId(Request $request): int
    {
        return (int) $request->session()->get('user.business_id');
    }

    private function findStocktake(Request $request, $id)
    {
        $st = DB::table('stocktakes')
            ->where('business_id', $this->businessId($request))
            ->where('id', $id)
            ->first();

        abort_if(! $st, 404);

        return $st;
    }

    private function fail(string $message)
    {
        return back()->with('status', ['success' => 0, 'msg' => $message]);
    }

    private function ok(string $message)
    {
        return back()->with('status', ['success' => 1, 'msg' => $message]);
    }

    public function index(Request $request)
    {
        $this->authorizeAccess();
        $bid = $this->businessId($request);

        $locations = BusinessLocation::forDropdown($bid)->toArray();

        $categories = DB::table('categories')
            ->where('business_id', $bid)
            ->where('category_type', 'product')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        $stocktakes = DB::table('stocktakes as s')
            ->leftJoin('business_locations as l', 'l.id', '=', 's.location_id')
            ->where('s.business_id', $bid)
            ->orderByDesc('s.id')
            ->limit(100)
            ->selectRaw('s.*, l.name as location_name,
                (SELECT COUNT(*) FROM stocktake_lines sl WHERE sl.stocktake_id = s.id) as total_lines,
                (SELECT COUNT(*) FROM stocktake_lines sl WHERE sl.stocktake_id = s.id AND sl.counted_qty IS NOT NULL) as counted_lines')
            ->get();

        return view('stocktake.index', compact('locations', 'categories', 'stocktakes'));
    }

    public function store(Request $request)
    {
        $this->authorizeAccess();

        $request->validate([
            'location_id' => 'required|integer',
            'category_id' => 'nullable|integer',
            'name' => 'nullable|string|max:120',
        ]);

        $bid = $this->businessId($request);
        $locations = BusinessLocation::forDropdown($bid)->toArray();
        $locationId = (int) $request->input('location_id');

        if (! array_key_exists($locationId, $locations)) {
            return back()->withErrors(['location_id' => 'Pick a valid location.'])->withInput();
        }

        $query = DB::table('variation_location_details as vld')
            ->join('variations as v', 'vld.variation_id', '=', 'v.id')
            ->join('products as p', 'vld.product_id', '=', 'p.id')
            ->where('p.business_id', $bid)
            ->where('p.enable_stock', 1)
            ->where('p.is_inactive', 0)
            ->whereNull('v.deleted_at')
            ->where('vld.location_id', $locationId);

        $categoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;

        if ($categoryId) {
            $query->where(function ($q) use ($categoryId) {
                $q->where('p.category_id', $categoryId)->orWhere('p.sub_category_id', $categoryId);
            });
        }

        $rows = $query->orderBy('p.name')->get([
            'vld.product_id', 'vld.variation_id', 'p.name', 'p.type',
            'v.name as variation_name', 'v.sub_sku', 'vld.qty_available', 'v.dpp_inc_tax',
        ]);

        if ($rows->isEmpty()) {
            return back()->withErrors(['location_id' => 'No stock-tracked products found for that location and category.'])->withInput();
        }

        $userId = $request->session()->get('user.id');

        $stocktakeId = DB::transaction(function () use ($rows, $bid, $locationId, $categoryId, $request, $userId) {
            $now = now();

            $id = DB::table('stocktakes')->insertGetId([
                'business_id' => $bid,
                'location_id' => $locationId,
                'name' => $request->input('name'),
                'category_id' => $categoryId,
                'status' => 'counting',
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('stocktakes')->where('id', $id)->update(['ref_no' => 'ST'.str_pad((string) $id, 4, '0', STR_PAD_LEFT)]);

            foreach ($rows->chunk(500) as $chunk) {
                DB::table('stocktake_lines')->insert($chunk->map(function ($r) use ($id, $now) {
                    return [
                        'stocktake_id' => $id,
                        'product_id' => $r->product_id,
                        'variation_id' => $r->variation_id,
                        'name' => substr($r->type === 'variable' ? $r->name.' - '.$r->variation_name : $r->name, 0, 255),
                        'sku' => $r->sub_sku,
                        'system_qty' => $r->qty_available,
                        'unit_cost' => $r->dpp_inc_tax ?? 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })->values()->all());
            }

            return $id;
        });

        return redirect('stocktakes/'.$stocktakeId)->with('status', [
            'success' => 1,
            'msg' => 'Stocktake started with '.$rows->count().' items. Enter what you count on the shelves.',
        ]);
    }

    public function show(Request $request, $id)
    {
        $this->authorizeAccess();
        $st = $this->findStocktake($request, $id);

        $location = DB::table('business_locations')->where('id', $st->location_id)->value('name');
        $lines = $this->linesWithCurrentStock($st);

        $summary = ['total' => $lines->count(), 'counted' => 0, 'short_qty' => 0, 'short_value' => 0, 'over_qty' => 0, 'over_value' => 0];

        foreach ($lines as $l) {
            if ($l->counted_qty === null) {
                continue;
            }

            $summary['counted']++;

            $diff = ($st->status === 'applied' && $l->variance !== null)
                ? (float) $l->variance
                : (float) $l->counted_qty - (float) ($l->current_qty ?? 0);

            if ($diff < 0) {
                $summary['short_qty'] += -$diff;
                $summary['short_value'] += -$diff * (float) $l->unit_cost;
            } elseif ($diff > 0) {
                $summary['over_qty'] += $diff;
                $summary['over_value'] += $diff * (float) $l->unit_cost;
            }
        }

        $applied = $st->summary ? json_decode($st->summary, true) : null;

        return view('stocktake.show', compact('st', 'location', 'lines', 'summary', 'applied'));
    }

    public function saveCounts(Request $request, $id)
    {
        $this->authorizeAccess();
        $st = $this->findStocktake($request, $id);

        if ($st->status !== 'counting') {
            return $this->fail('This stocktake is already closed.');
        }

        $counts = json_decode((string) $request->input('counts_json'), true);

        if (! is_array($counts) || empty($counts)) {
            return $this->fail('No changes to save.');
        }

        $saved = 0;

        DB::transaction(function () use ($counts, $st, &$saved) {
            foreach ($counts as $lineId => $value) {
                $value = is_string($value) ? trim($value) : $value;

                if ($value === '' || $value === null) {
                    $qty = null;
                } elseif (is_numeric($value) && (float) $value >= 0) {
                    $qty = round((float) $value, 4);
                } else {
                    continue;
                }

                $saved += DB::table('stocktake_lines')
                    ->where('stocktake_id', $st->id)
                    ->where('id', (int) $lineId)
                    ->update(['counted_qty' => $qty, 'updated_at' => now()]);
            }
        });

        return $this->ok($saved.' count(s) saved.');
    }

    public function export(Request $request, $id)
    {
        $this->authorizeAccess();
        $st = $this->findStocktake($request, $id);
        $lines = $this->linesWithCurrentStock($st);

        return response()->streamDownload(function () use ($lines) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['line_id', 'sku', 'product', 'expected_now', 'counted']);

            foreach ($lines as $l) {
                fputcsv($out, [
                    $l->id,
                    $l->sku,
                    $l->name,
                    $this->qty($l->current_qty ?? 0),
                    $l->counted_qty === null ? '' : $this->qty($l->counted_qty),
                ]);
            }

            fclose($out);
        }, $st->ref_no.'-count-sheet.csv', ['Content-Type' => 'text/csv']);
    }

    public function import(Request $request, $id)
    {
        $this->authorizeAccess();
        $st = $this->findStocktake($request, $id);

        if ($st->status !== 'counting') {
            return $this->fail('This stocktake is already closed.');
        }

        $request->validate(['csv_file' => 'required|file|mimes:csv,txt|max:5120']);

        $handle = fopen($request->file('csv_file')->getRealPath(), 'r');
        $header = fgetcsv($handle) ?: [];
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header);

        $idCol = array_search('line_id', $header, true);
        $countCol = array_search('counted', $header, true);

        if ($idCol === false || $countCol === false) {
            fclose($handle);

            return $this->fail('The CSV needs line_id and counted columns. Use the exported count sheet.');
        }

        $updated = 0;

        DB::transaction(function () use ($handle, $idCol, $countCol, $st, &$updated) {
            while (($row = fgetcsv($handle)) !== false) {
                $value = trim((string) ($row[$countCol] ?? ''));

                if ($value === '' || ! is_numeric($value) || (float) $value < 0) {
                    continue;
                }

                $updated += DB::table('stocktake_lines')
                    ->where('stocktake_id', $st->id)
                    ->where('id', (int) ($row[$idCol] ?? 0))
                    ->update(['counted_qty' => round((float) $value, 4), 'updated_at' => now()]);
            }
        });

        fclose($handle);

        return $this->ok($updated.' count(s) imported.');
    }

    public function cancel(Request $request, $id)
    {
        $this->authorizeAccess();
        $st = $this->findStocktake($request, $id);

        if ($st->status !== 'counting') {
            return $this->fail('This stocktake is already closed.');
        }

        DB::table('stocktakes')->where('id', $st->id)->update(['status' => 'cancelled', 'updated_at' => now()]);

        return redirect('stocktakes')->with('status', ['success' => 1, 'msg' => 'Stocktake cancelled. No stock was changed.']);
    }

    /**
     * Set stock to the counted quantities.
     * Shortages are booked as a normal stock adjustment; overages (optional) as opening-stock entries.
     * Items that were not counted are left untouched.
     */
    public function apply(Request $request, $id)
    {
        $this->authorizeAccess();
        $st = $this->findStocktake($request, $id);
        $bid = $this->businessId($request);
        $userId = $request->session()->get('user.id');
        $bookOverages = $request->boolean('book_overages');

        try {
            DB::beginTransaction();

            $locked = DB::table('stocktakes')->where('id', $st->id)->lockForUpdate()->first();

            if (! $locked || $locked->status !== 'counting') {
                DB::rollBack();

                return $this->fail('This stocktake is already closed.');
            }

            $lines = DB::table('stocktake_lines')->where('stocktake_id', $st->id)->whereNotNull('counted_qty')->get();

            if ($lines->isEmpty()) {
                DB::rollBack();

                return $this->fail('Nothing has been counted yet.');
            }

            $variationIds = $lines->pluck('variation_id')->all();

            $current = DB::table('variation_location_details')
                ->where('location_id', $st->location_id)
                ->whereIn('variation_id', $variationIds)
                ->pluck('qty_available', 'variation_id');

            $variations = DB::table('variations')
                ->whereIn('id', $variationIds)
                ->get(['id', 'default_purchase_price', 'dpp_inc_tax'])
                ->keyBy('id');

            $productTax = DB::table('products')
                ->whereIn('id', $lines->pluck('product_id')->unique()->all())
                ->pluck('tax', 'id');

            $shortages = [];
            $overages = [];
            $variances = [];

            foreach ($lines as $l) {
                $diff = round((float) $l->counted_qty - (float) ($current[$l->variation_id] ?? 0), 4);
                $variances[$l->id] = $diff;

                if ($diff < 0) {
                    $shortages[] = ['line' => $l, 'qty' => -$diff];
                } elseif ($diff > 0) {
                    $overages[] = ['line' => $l, 'qty' => $diff];
                }
            }

            $now = \Carbon\Carbon::now($request->session()->get('business.time_zone', config('app.timezone')))->toDateTimeString();

            // Shortages: same steps as Stock Adjustments > Add.
            $shortQty = 0;
            $shortValue = 0;
            $adjustment = null;

            if (! empty($shortages)) {
                $refCount = $this->productUtil->setAndGetReferenceCount('stock_adjustment');
                $refNo = $this->productUtil->generateReferenceNumber('stock_adjustment', $refCount);
                $adjustmentLines = [];

                foreach ($shortages as $s) {
                    $l = $s['line'];
                    $unit = (float) ($variations[$l->variation_id]->dpp_inc_tax ?? 0);

                    $this->productUtil->decreaseProductQuantity($l->product_id, $l->variation_id, $st->location_id, $s['qty']);

                    $adjustmentLines[] = [
                        'product_id' => $l->product_id,
                        'variation_id' => $l->variation_id,
                        'quantity' => $s['qty'],
                        'unit_price' => $unit,
                    ];

                    $shortQty += $s['qty'];
                    $shortValue += $unit * $s['qty'];
                }

                $adjustment = Transaction::create([
                    'type' => 'stock_adjustment',
                    'business_id' => $bid,
                    'created_by' => $userId,
                    'location_id' => $st->location_id,
                    'transaction_date' => $now,
                    'adjustment_type' => 'normal',
                    'additional_notes' => 'Stocktake '.$st->ref_no,
                    'total_amount_recovered' => 0,
                    'final_total' => $shortValue,
                    'ref_no' => $refNo,
                ]);

                $adjustment->stock_adjustment_lines()->createMany($adjustmentLines);

                $business = [
                    'id' => $bid,
                    'accounting_method' => $request->session()->get('business.accounting_method'),
                    'location_id' => $st->location_id,
                ];
                $this->transactionUtil->mapPurchaseSell($business, $adjustment->stock_adjustment_lines, 'stock_adjustment');

                event(new StockAdjustmentCreatedOrModified($adjustment, 'added'));

                $this->transactionUtil->activityLog($adjustment, 'added', null, [], false);
            }

            // Overages: same steps as Opening Stock for a new entry.
            $overQty = 0;
            $overValue = 0;
            $overBooked = 0;

            if ($bookOverages) {
                foreach ($overages as $o) {
                    $l = $o['line'];
                    $qty = $o['qty'];
                    $v = $variations[$l->variation_id] ?? null;

                    $exTax = (float) ($v->default_purchase_price ?? 0);
                    $incTax = (float) ($v->dpp_inc_tax ?? 0);

                    if ($incTax <= 0) {
                        $incTax = $exTax;
                    }

                    $this->productUtil->updateProductQuantity($st->location_id, $l->product_id, $l->variation_id, $qty, 0, null, false);

                    $purchaseLine = new PurchaseLine();
                    $purchaseLine->product_id = $l->product_id;
                    $purchaseLine->variation_id = $l->variation_id;
                    $purchaseLine->item_tax = max(0, $incTax - $exTax);
                    $purchaseLine->tax_id = $productTax[$l->product_id] ?? null;
                    $purchaseLine->quantity = $qty;
                    $purchaseLine->pp_without_discount = $exTax;
                    $purchaseLine->purchase_price = $exTax;
                    $purchaseLine->purchase_price_inc_tax = $incTax;
                    $purchaseLine->exp_date = null;
                    $purchaseLine->lot_number = null;
                    $purchaseLine->secondary_unit_quantity = 0;

                    $openingStock = Transaction::create([
                        'type' => 'opening_stock',
                        'opening_stock_product_id' => $l->product_id,
                        'status' => 'received',
                        'business_id' => $bid,
                        'transaction_date' => $now,
                        'additional_notes' => 'Stocktake '.$st->ref_no,
                        'total_before_tax' => $incTax,
                        'location_id' => $st->location_id,
                        'final_total' => $incTax * $qty,
                        'payment_status' => 'paid',
                        'created_by' => $userId,
                    ]);

                    $openingStock->purchase_lines()->saveMany([$purchaseLine]);

                    $this->productUtil->adjustStockOverSelling($openingStock);

                    $overQty += $qty;
                    $overValue += $incTax * $qty;
                    $overBooked++;
                }
            }

            foreach ($variances as $lineId => $diff) {
                DB::table('stocktake_lines')->where('id', $lineId)->update(['variance' => $diff, 'updated_at' => now()]);
            }

            DB::table('stocktakes')->where('id', $st->id)->update([
                'status' => 'applied',
                'applied_by' => $userId,
                'applied_at' => now(),
                'stock_adjustment_id' => $adjustment ? $adjustment->id : null,
                'summary' => json_encode([
                    'short_items' => count($shortages),
                    'short_qty' => $shortQty,
                    'short_value' => round($shortValue, 2),
                    'adjustment_ref' => $adjustment ? $adjustment->ref_no : null,
                    'over_items_found' => count($overages),
                    'over_items_booked' => $overBooked,
                    'over_qty' => $overQty,
                    'over_value' => round($overValue, 2),
                    'over_booking_requested' => $bookOverages,
                ]),
                'updated_at' => now(),
            ]);

            DB::commit();
        } catch (\App\Exceptions\PurchaseSellMismatch $e) {
            DB::rollBack();

            return $this->fail($e->getMessage());
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::emergency('Stocktake apply failed. File:'.$e->getFile().' Line:'.$e->getLine().' Message:'.$e->getMessage());

            return $this->fail('Could not apply the stocktake. Nothing was changed. ('.$e->getMessage().')');
        }

        return redirect('stocktakes/'.$st->id)->with('status', ['success' => 1, 'msg' => 'Stocktake applied. Stock now matches your counts.']);
    }

    private function linesWithCurrentStock($st)
    {
        return DB::table('stocktake_lines as l')
            ->leftJoin('variation_location_details as vld', function ($join) use ($st) {
                $join->on('vld.variation_id', '=', 'l.variation_id')
                    ->where('vld.location_id', '=', $st->location_id);
            })
            ->where('l.stocktake_id', $st->id)
            ->orderBy('l.name')
            ->get(['l.*', 'vld.qty_available as current_qty']);
    }

    private function qty($n): string
    {
        return rtrim(rtrim(number_format((float) $n, 4, '.', ''), '0'), '.') ?: '0';
    }
}
