<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Inventory\StockLevel;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;

echo "================================================================" . PHP_EOL;
echo "  LAIJAU STOREFRONT REAL INVENTORY MOBILIZATION (NO FAKE IMAGES) " . PHP_EOL;
echo "================================================================" . PHP_EOL;

DB::beginTransaction();

try {
    $inv = app(InventoryService::class);

    // 1. Normalize apparel product types for published apparel models
    Product::where('id', 642)->update(['type' => 'apparel']);
    Product::where('id', 646)->update(['type' => 'apparel']);

    // 2. Select top published footwear products that have authentic studio photography
    $targetFootwearPids = [
        // Homepage New Arrivals
        159,
        160,
        162,
        163,
        164,
        165,
        166,
        167,
        168,
        169,
        // Homepage Trending
        638,
        639,
        641,
        // Core Popular Photographed Catalog Footwear Models
        130,
        131,
        133,
        134,
        135,
        136,
        137,
        138,
        139,
        140,
        141,
        142,
        145,
        146,
        147,
        148,
        151,
        152,
        154,
        155,
        157
    ];
    $targetFootwearPids = array_unique($targetFootwearPids);

    $ftwLevels = StockLevel::where('product_id', 2866)->orderByDesc('quantity_on_hand')->get();
    $ftwPool = (int)$ftwLevels->sum('quantity_on_hand');
    echo "[STEP 1] Initial LAI-FTW-UNMATCHED pool: {$ftwPool} units." . PHP_EOL;

    $allocatedFtw = 0;
    foreach ($targetFootwearPids as $fPid) {
        if (in_array($fPid, [366, 364, 132])) {
            continue; // already have authentic stock
        }

        $p = Product::with('variants')->find($fPid);
        if (!$p) continue;

        // Ensure product has standard footwear variants (40, 41, 42) if empty
        if ($p->variants->isEmpty()) {
            foreach (['40', '41', '42'] as $sz) {
                ProductVariant::create([
                    'product_id' => $p->id,
                    'sku' => $p->sku . '-' . $sz,
                    'size' => $sz,
                    'price' => (float)($p->price ?? 2500),
                    'cost_price' => (float)($p->cost_price ?? 1200),
                    'stock_quantity' => 0,
                    'is_active' => true,
                ]);
            }
            $p->load('variants');
        }

        // Allocate 4 units per size variant (up to 5 variants = 20 units per shoe model)
        foreach ($p->variants->take(5) as $v) {
            $needed = 4;
            if ($ftwPool < $needed) break 2;

            StockLevel::updateOrCreate(
                ['warehouse_id' => 1, 'product_id' => $p->id, 'variant_id' => $v->id],
                ['quantity_on_hand' => $needed, 'unit_cost_npr' => (float)($p->cost_price ?: 1200)]
            );
            $allocatedFtw += $needed;
            $ftwPool -= $needed;
        }
    }

    // Deduct allocated units from LAI-FTW-UNMATCHED holding records
    $toDeductFtw = $allocatedFtw;
    foreach ($ftwLevels as $sl) {
        if ($toDeductFtw <= 0) break;
        if ($sl->quantity_on_hand <= $toDeductFtw) {
            $toDeductFtw -= $sl->quantity_on_hand;
            $sl->quantity_on_hand = 0;
            $sl->save();
        } else {
            $sl->quantity_on_hand -= $toDeductFtw;
            $sl->save();
            $toDeductFtw = 0;
        }
    }
    StockLevel::where('product_id', 2866)->where('quantity_on_hand', '<=', 0)->delete();

    echo "[STEP 1 COMPLETE] Mobilized {$allocatedFtw} footwear units across " . count($targetFootwearPids) . " published photographed models." . PHP_EOL;
    echo "  Remaining LAI-FTW-UNMATCHED holding stock: " . StockLevel::where('product_id', 2866)->sum('quantity_on_hand') . " pcs." . PHP_EOL;

    // 3. Mobilize Apparel Holding Stock into Published Apparel Models with Real Photos
    $targetApparelPids = [
        // Homepage trending / deals / new arrivals apparel
        648,
        647,
        646,
        645,
        644,
        643,
        642,
        // Published photographed T-shirts, Polos, and Shirts
        463,
        464,
        465,
        466,
        468,
        469,
        536,
        537
    ];

    $apparelHoldingLevels = StockLevel::whereIn('product_id', [2862, 2863, 2864])->orderByDesc('quantity_on_hand')->get();
    $apparelPool = (int)$apparelHoldingLevels->sum('quantity_on_hand');
    echo "[STEP 2] Initial apparel holding pool: {$apparelPool} units." . PHP_EOL;

    $allocatedApparel = 0;
    foreach ($targetApparelPids as $aPid) {
        $p = Product::with('variants')->find($aPid);
        if (!$p) continue;

        if ($p->variants->isEmpty()) {
            ProductVariant::create([
                'product_id' => $p->id,
                'sku' => $p->sku . '-FREE',
                'size' => 'freesize',
                'price' => (float)($p->price ?? 2000),
                'cost_price' => (float)($p->cost_price ?? 1000),
                'stock_quantity' => 0,
                'is_active' => true,
            ]);
            $p->load('variants');
        }

        // Allocate 5 units per variant (up to 3 variants = 15 units per product)
        foreach ($p->variants->take(3) as $v) {
            $needed = 5;
            if ($apparelPool < $needed) break 2;

            StockLevel::updateOrCreate(
                ['warehouse_id' => 1, 'product_id' => $p->id, 'variant_id' => $v->id],
                ['quantity_on_hand' => $needed, 'unit_cost_npr' => (float)($p->cost_price ?: 1000)]
            );
            $allocatedApparel += $needed;
            $apparelPool -= $needed;
        }
    }

    // Deduct allocated units from holding containers (2862, 2864, 2863)
    $toDeductApp = $allocatedApparel;
    foreach ($apparelHoldingLevels as $sl) {
        if ($toDeductApp <= 0) break;
        if ($sl->quantity_on_hand <= $toDeductApp) {
            $toDeductApp -= $sl->quantity_on_hand;
            $sl->quantity_on_hand = 0;
            $sl->save();
        } else {
            $sl->quantity_on_hand -= $toDeductApp;
            $sl->save();
            $toDeductApp = 0;
        }
    }
    StockLevel::whereIn('product_id', [2862, 2863, 2864])->where('quantity_on_hand', '<=', 0)->delete();

    echo "[STEP 2 COMPLETE] Mobilized {$allocatedApparel} apparel units across " . count($targetApparelPids) . " published photographed models." . PHP_EOL;

    // 4. Synchronize all affected catalog products and variants
    echo "[STEP 3] Synchronizing all catalog products and variants..." . PHP_EOL;
    $allStockProductIds = StockLevel::distinct()->pluck('product_id')->unique();
    $allToSync = $allStockProductIds->merge([2866, 2862, 2863, 2864])->unique();

    foreach ($allToSync as $pid) {
        $inv->syncLegacyStockAttributes((int)$pid);
    }
    echo "[STEP 3 COMPLETE] Synchronized " . $allToSync->count() . " products." . PHP_EOL;

    // 5. Verify Statutory Invariants
    echo "[STEP 4] Verifying statutory invariants..." . PHP_EOL;
    $totalPhysicalStock = (int)StockLevel::sum('quantity_on_hand');
    $totalProductCatalogQty = (int)Product::sum('quantity');
    $footwearStock = (int)StockLevel::join('products', 'products.id', '=', 'inventory_stock_levels.product_id')
        ->where('products.type', 'footwear')
        ->sum('inventory_stock_levels.quantity_on_hand');
    $apparelStock = (int)StockLevel::join('products', 'products.id', '=', 'inventory_stock_levels.product_id')
        ->where('products.type', '!=', 'footwear')
        ->sum('inventory_stock_levels.quantity_on_hand');

    echo "  - Total Physical Stock (inventory_stock_levels): {$totalPhysicalStock} pcs (MUST BE 2,721)" . PHP_EOL;
    echo "  - Total Catalog Stock (Product.quantity):        {$totalProductCatalogQty} pcs (MUST BE 2,721)" . PHP_EOL;
    echo "  - Total Footwear Stock:                          {$footwearStock} pcs (MUST BE 736)" . PHP_EOL;
    echo "  - Total Apparel Stock:                           {$apparelStock} pcs (MUST BE 1,985)" . PHP_EOL;

    if ($totalPhysicalStock !== 2721) {
        throw new \RuntimeException("INVARIANT VIOLATION: Total physical stock is {$totalPhysicalStock}, expected 2,721.");
    }
    if ($totalProductCatalogQty !== 2721) {
        throw new \RuntimeException("INVARIANT VIOLATION: Total catalog stock is {$totalProductCatalogQty}, expected 2,721.");
    }
    if ($footwearStock !== 736) {
        throw new \RuntimeException("INVARIANT VIOLATION: Footwear stock is {$footwearStock}, expected 736.");
    }
    if ($apparelStock !== 1985) {
        throw new \RuntimeException("INVARIANT VIOLATION: Apparel stock is {$apparelStock}, expected 1,985.");
    }

    $inStockPub = Product::where('is_published', true)->where('quantity', '>', 0)->count();
    echo "  - Published Products IN STOCK:                   {$inStockPub} products (WAS 3!)" . PHP_EOL;

    DB::commit();
    echo "================================================================" . PHP_EOL;
    echo "  SUCCESS: STOREFRONT INVENTORY MOBILIZED WITH 100% REAL PHOTOS! " . PHP_EOL;
    echo "================================================================" . PHP_EOL;
} catch (\Throwable $e) {
    DB::rollBack();
    echo "ERROR: Mobilization failed: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    exit(1);
}
