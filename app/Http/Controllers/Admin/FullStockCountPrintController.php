<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Inventory\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FullStockCountPrintController extends Controller
{
    /**
     * Render the printable stock count sheet for warehouse auditing.
     */
    public function print(Request $request): View
    {
        $warehouseId = (int) $request->query('warehouse_id', 1);
        $warehouse = Warehouse::find($warehouseId) ?: Warehouse::where('is_default', true)->firstOrFail();

        $categoryId = $request->query('category_id');
        $brand = $request->query('brand');
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status', 'all');

        // 1. Non-variant products subquery
        $q1 = DB::table('products as p')
            ->where('p.is_active', true)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('product_variants as pv')
                    ->whereColumn('pv.product_id', 'p.id')
                    ->where('pv.is_active', true);
            })
            ->leftJoin('inventory_stock_levels as sl', function ($join) use ($warehouse) {
                $join->on('sl.product_id', '=', 'p.id')
                    ->whereNull('sl.variant_id')
                    ->where('sl.warehouse_id', '=', $warehouse->id);
            })
            ->select([
                DB::raw("'product' as item_type"),
                DB::raw("CONCAT('p_', p.id) as item_key"),
                'p.id as product_id',
                DB::raw('NULL as variant_id'),
                'p.name as product_name',
                'p.sku as sku',
                'p.barcode as barcode',
                DB::raw("'' as size"),
                DB::raw("'' as color"),
                'p.brand as brand',
                'p.cost_price as unit_cost',
                DB::raw('COALESCE(sl.quantity_on_hand, 0) as system_qty'),
            ]);

        // 2. Variant products subquery
        $q2 = DB::table('product_variants as pv')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->where('pv.is_active', true)
            ->where('p.is_active', true)
            ->leftJoin('inventory_stock_levels as sl', function ($join) use ($warehouse) {
                $join->on('sl.product_id', '=', 'p.id')
                    ->on('sl.variant_id', '=', 'pv.id')
                    ->where('sl.warehouse_id', '=', $warehouse->id);
            })
            ->select([
                DB::raw("'variant' as item_type"),
                DB::raw("CONCAT('v_', pv.id) as item_key"),
                'p.id as product_id',
                'pv.id as variant_id',
                'p.name as product_name',
                DB::raw('COALESCE(pv.sku, p.sku) as sku'),
                DB::raw('COALESCE(pv.barcode, p.barcode) as barcode'),
                DB::raw("COALESCE(pv.size, '') as size"),
                DB::raw("COALESCE(pv.color, '') as color"),
                'p.brand as brand',
                DB::raw('COALESCE(pv.cost_price, p.cost_price, 0) as unit_cost'),
                DB::raw('COALESCE(sl.quantity_on_hand, 0) as system_qty'),
            ]);

        // Apply Category filter
        if (!empty($categoryId)) {
            $q1->whereExists(function ($q) use ($categoryId) {
                $q->select(DB::raw(1))
                    ->from('category_product')
                    ->whereColumn('category_product.product_id', 'p.id')
                    ->where('category_product.category_id', (int) $categoryId);
            });
            $q2->whereExists(function ($q) use ($categoryId) {
                $q->select(DB::raw(1))
                    ->from('category_product')
                    ->whereColumn('category_product.product_id', 'p.id')
                    ->where('category_product.category_id', (int) $categoryId);
            });
        }

        // Apply Brand filter
        if (!empty($brand)) {
            $q1->where('p.brand', $brand);
            $q2->where('p.brand', $brand);
        }

        // Union query
        $union = $q1->unionAll($q2);
        $outer = DB::table($union, 'items');

        // Apply Search filter
        if ($search !== '') {
            $tokens = array_filter(explode(' ', $search));
            foreach ($tokens as $token) {
                $outer->where(function ($sub) use ($token) {
                    $sub->where('product_name', 'like', "%{$token}%")
                        ->orWhere('sku', 'like', "%{$token}%")
                        ->orWhere('barcode', 'like', "%{$token}%")
                        ->orWhere('size', 'like', "%{$token}%")
                        ->orWhere('color', 'like', "%{$token}%");
                });
            }
        }

        // Apply Stock Status filter
        if ($status === 'zero_stock') {
            $outer->where('system_qty', 0);
        } elseif ($status === 'negative_stock') {
            $outer->where('system_qty', '<', 0);
        }

        $items = $outer->orderBy('product_name', 'asc')->orderBy('sku', 'asc')->get();

        $filterParts = [];
        if (!empty($categoryId)) {
            $cat = Category::find($categoryId);
            if ($cat) {
                $filterParts[] = "Category: {$cat->name}";
            }
        }
        if (!empty($brand)) {
            $filterParts[] = "Brand: {$brand}";
        }
        if (!empty($search)) {
            $filterParts[] = "Search: '{$search}'";
        }
        if ($status !== 'all') {
            $filterParts[] = "Status: " . ucfirst(str_replace('_', ' ', $status));
        }
        $filterLabel = implode(' | ', $filterParts);

        return view('print.stock-count-sheet', [
            'warehouse' => $warehouse,
            'items' => $items,
            'filterLabel' => $filterLabel,
        ]);
    }

    /**
     * Export the stock count sheet as an Excel/CSV spreadsheet for offline counting & direct re-import.
     */
    public function export(Request $request)
    {
        $warehouseId = (int) $request->query('warehouse_id', 1);
        $warehouse = Warehouse::find($warehouseId) ?: Warehouse::where('is_default', true)->firstOrFail();

        $categoryId = $request->query('category_id');
        $brand = $request->query('brand');
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status', 'all');

        // 1. Non-variant products subquery
        $q1 = DB::table('products as p')
            ->where('p.is_active', true)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('product_variants as pv')
                    ->whereColumn('pv.product_id', 'p.id')
                    ->where('pv.is_active', true);
            })
            ->leftJoin('inventory_stock_levels as sl', function ($join) use ($warehouse) {
                $join->on('sl.product_id', '=', 'p.id')
                    ->whereNull('sl.variant_id')
                    ->where('sl.warehouse_id', '=', $warehouse->id);
            })
            ->select([
                DB::raw("'product' as item_type"),
                DB::raw("CONCAT('p_', p.id) as item_key"),
                'p.id as product_id',
                DB::raw('NULL as variant_id'),
                'p.name as product_name',
                'p.sku as sku',
                'p.barcode as barcode',
                DB::raw("'' as size"),
                DB::raw("'' as color"),
                'p.brand as brand',
                'p.cost_price as unit_cost',
                DB::raw('COALESCE(sl.quantity_on_hand, 0) as system_qty'),
            ]);

        // 2. Variant products subquery
        $q2 = DB::table('product_variants as pv')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->where('pv.is_active', true)
            ->where('p.is_active', true)
            ->leftJoin('inventory_stock_levels as sl', function ($join) use ($warehouse) {
                $join->on('sl.product_id', '=', 'p.id')
                    ->on('sl.variant_id', '=', 'pv.id')
                    ->where('sl.warehouse_id', '=', $warehouse->id);
            })
            ->select([
                DB::raw("'variant' as item_type"),
                DB::raw("CONCAT('v_', pv.id) as item_key"),
                'p.id as product_id',
                'pv.id as variant_id',
                'p.name as product_name',
                DB::raw('COALESCE(pv.sku, p.sku) as sku'),
                DB::raw('COALESCE(pv.barcode, p.barcode) as barcode'),
                DB::raw("COALESCE(pv.size, '') as size"),
                DB::raw("COALESCE(pv.color, '') as color"),
                'p.brand as brand',
                DB::raw('COALESCE(pv.cost_price, p.cost_price, 0) as unit_cost'),
                DB::raw('COALESCE(sl.quantity_on_hand, 0) as system_qty'),
            ]);

        if (!empty($categoryId)) {
            $q1->whereExists(function ($q) use ($categoryId) {
                $q->select(DB::raw(1))
                    ->from('category_product')
                    ->whereColumn('category_product.product_id', 'p.id')
                    ->where('category_product.category_id', (int) $categoryId);
            });
            $q2->whereExists(function ($q) use ($categoryId) {
                $q->select(DB::raw(1))
                    ->from('category_product')
                    ->whereColumn('category_product.product_id', 'p.id')
                    ->where('category_product.category_id', (int) $categoryId);
            });
        }

        if (!empty($brand)) {
            $q1->where('p.brand', $brand);
            $q2->where('p.brand', $brand);
        }

        $union = $q1->unionAll($q2);
        $outer = DB::table($union, 'items');

        if ($search !== '') {
            $tokens = array_filter(explode(' ', $search));
            foreach ($tokens as $token) {
                $outer->where(function ($sub) use ($token) {
                    $sub->where('product_name', 'like', "%{$token}%")
                        ->orWhere('sku', 'like', "%{$token}%")
                        ->orWhere('barcode', 'like', "%{$token}%")
                        ->orWhere('size', 'like', "%{$token}%")
                        ->orWhere('color', 'like', "%{$token}%");
                });
            }
        }

        if ($status === 'zero_stock') {
            $outer->where('system_qty', 0);
        } elseif ($status === 'negative_stock') {
            $outer->where('system_qty', '<', 0);
        }

        $items = $outer->orderBy('product_name', 'asc')->orderBy('sku', 'asc')->get();

        $cleanWhCode = preg_replace('/[^A-Za-z0-9_-]/', '', $warehouse->code ?: 'WH');
        $filename = "stock-count-sheet-{$cleanWhCode}-" . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($items) {
            $output = fopen('php://output', 'w');
            // Write UTF-8 BOM so Excel opens with native UTF-8 encoding
            fputs($output, "\xEF\xBB\xBF");

            // CSV Column Headers matching Laijau format
            fputcsv($output, [
                '#',
                'Item Key',
                'Product Description',
                'SKU',
                'Barcode',
                'Size',
                'Color',
                'Brand',
                'System Qty',
                'Physical Qty',
                'Unit Cost',
            ]);

            foreach ($items as $index => $item) {
                fputcsv($output, [
                    $index + 1,
                    $item->item_key,
                    $item->product_name,
                    $item->sku,
                    $item->barcode ?: '',
                    $item->size ?: '',
                    $item->color ?: '',
                    $item->brand ?: '',
                    (int) $item->system_qty,
                    '', // Blank Physical Qty column for the auditor to fill in
                    (float) $item->unit_cost,
                ]);
            }

            fclose($output);
        }, 200, $headers);
    }
}
