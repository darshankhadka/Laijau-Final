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
use App\Services\Operational\ForensicProductReconciliationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "================================================================" . PHP_EOL;
echo "  LAIJAU FORENSIC ACCOUNTING & CATALOG STOCK SYNCHRONIZATION    " . PHP_EOL;
echo "================================================================" . PHP_EOL;

DB::beginTransaction();

try {
    $rec = new ForensicProductReconciliationService();
    $inventoryService = app(InventoryService::class);

    $shoesPath = storage_path('app/migration/real_data_extracted/shoes_stock.json');
    $clothesPath = storage_path('app/migration/real_data_extracted/clothes_stock.json');

    if (!file_exists($shoesPath) || !file_exists($clothesPath)) {
        throw new \RuntimeException("Extracted stock files not found in {$shoesPath} or {$clothesPath}");
    }

    $shoes = json_decode(file_get_contents($shoesPath), true);
    $clothes = json_decode(file_get_contents($clothesPath), true);

    echo "Loaded " . count($shoes) . " shoe rows and " . count($clothes) . " clothes rows." . PHP_EOL;

    // 1. Fix Product 2198 (Crocodile) and 2270 apparel classification and misattributed stock records (IDs 360, 361, 362, 363)
    DB::table('products')->whereIn('id', [2198, 2270])->where('type', 'footwear')->update(['type' => 'apparel']);
    $crocUpdated = DB::table('inventory_stock_levels')
        ->whereIn('id', [360, 361, 362, 363])
        ->where('product_id', 2862)
        ->update(['product_id' => 2198]);
    if ($crocUpdated > 0) {
        echo "[STEP 1] Corrected {$crocUpdated} Crocodile stock levels (IDs 360-363) from Product 2862 to Product 2198." . PHP_EOL;
    } else {
        echo "[STEP 1] Crocodile stock levels (IDs 360-363) already correctly assigned to Product 2198." . PHP_EOL;
    }

    // 2. Identify all StockLevels with NULL variant_id for products that HAVE variants
    $nullLevels = StockLevel::whereNull('variant_id')
        ->whereIn('product_id', function ($q) {
            $q->select('product_id')->from('product_variants');
        })
        ->orderBy('id')
        ->get();

    echo "[STEP 2] Found {$nullLevels->count()} stock level records requiring variant linkage." . PHP_EOL;

    $createdVariantsCount = 0;
    $linkedLevelsCount = 0;

    foreach ($nullLevels as $sl) {
        $p = Product::find($sl->product_id);
        if (!$p) {
            echo "WARNING: Product ID {$sl->product_id} not found for StockLevel {$sl->id}" . PHP_EOL;
            continue;
        }

        $row = null;
        if ($sl->id <= 74) {
            $row = $shoes[$sl->id - 1] ?? null;
        } else {
            $row = $clothes[$sl->id - 75] ?? null;
        }

        $sizeRaw = $row['size'] ?? null;
        $normSize = $rec->normalizeSize($sizeRaw);
        if (empty($normSize)) {
            echo "WARNING: Empty size for StockLevel ID {$sl->id}, Product {$p->id} ({$p->name})" . PHP_EOL;
            continue;
        }

        // Search for existing variant with normalized size matching
        $variant = $p->variants->first(function ($v) use ($sizeRaw, $normSize, $rec) {
            $vSizeNorm = $rec->normalizeSize($v->size);
            if ($vSizeNorm === $normSize) return true;
            if (strtolower(trim((string)$v->size)) === strtolower(trim((string)$sizeRaw))) return true;
            if ($normSize === '2xl' && strtolower(trim((string)$v->size)) === 'xxl') return true;
            if ($normSize === 'xxl' && strtolower(trim((string)$v->size)) === '2xl') return true;
            return false;
        });

        // If not found, create the authentic variant for this product
        if (!$variant) {
            $varSku = $p->sku . '-' . strtoupper($normSize);
            if (ProductVariant::where('sku', $varSku)->exists()) {
                $varSku .= '-' . $p->id;
            }
            $variant = ProductVariant::create([
                'product_id' => $p->id,
                'sku' => $varSku,
                'size' => strtoupper($normSize),
                'price' => (float)($p->price ?? 0.0),
                'cost_price' => (float)($p->cost_price ?? 0.0),
                'stock_quantity' => 0,
                'is_active' => true,
            ]);
            $p->load('variants');
            $createdVariantsCount++;
        }

        // Link variant in StockLevel
        $sl->variant_id = $variant->id;
        $sl->product_id = $p->id;
        $sl->save();
        $linkedLevelsCount++;
    }

    echo "[STEP 2 COMPLETE] Created {$createdVariantsCount} new variants, linked {$linkedLevelsCount} stock levels." . PHP_EOL;

    // 3. Synchronize catalog product and variant quantities for all products with inventory
    echo "[STEP 3] Synchronizing catalog products & variants with inventory stock levels..." . PHP_EOL;
    $allStockProductIds = StockLevel::distinct()->pluck('product_id')->unique();
    // Also include holding products and any legacy products that might have had stock
    $allAffectedPids = $allStockProductIds->merge([2862, 2863, 2864, 2866, 2198, 130])->unique();

    foreach ($allAffectedPids as $pid) {
        $inventoryService->syncLegacyStockAttributes((int)$pid);
    }
    echo "[STEP 3 COMPLETE] Synchronized " . $allAffectedPids->count() . " products." . PHP_EOL;

    // 4. Verify Statutory Invariants
    echo "[STEP 4] Verifying statutory invariants..." . PHP_EOL;
    $totalPhysicalStock = (int)StockLevel::sum('quantity_on_hand');
    $totalProductCatalogQty = (int)Product::sum('quantity');
    $totalVariantStock = (int)ProductVariant::sum('stock_quantity');
    $holdingStock = (int)Product::whereIn('id', [2866, 2862, 2863, 2864])->sum('quantity');
    $unassignedStockLevels = (int)StockLevel::whereNull('variant_id')->sum('quantity_on_hand');

    echo "  - Total Physical Stock (inventory_stock_levels): {$totalPhysicalStock} pcs" . PHP_EOL;
    echo "  - Total Catalog Stock (Product.quantity):        {$totalProductCatalogQty} pcs" . PHP_EOL;
    echo "  - Total Variant Stock (ProductVariant.stock_qty): {$totalVariantStock} pcs" . PHP_EOL;
    echo "  - Total Holding Stock (Uncataloged products):   {$holdingStock} pcs" . PHP_EOL;
    echo "  - Unassigned StockLevels (variant_id IS NULL):   {$unassignedStockLevels} pcs" . PHP_EOL;

    if ($totalPhysicalStock !== 2721) {
        throw new \RuntimeException("INVARIANT VIOLATION: Total physical stock is {$totalPhysicalStock}, expected exactly 2,721.");
    }

    if ($totalProductCatalogQty !== 2721) {
        throw new \RuntimeException("INVARIANT VIOLATION: Total product catalog quantity is {$totalProductCatalogQty}, expected exactly 2,721.");
    }

    if ($totalVariantStock + $holdingStock !== 2721) {
        throw new \RuntimeException("INVARIANT VIOLATION: Variant stock ({$totalVariantStock}) + Holding stock ({$holdingStock}) != 2,721.");
    }

    if ($holdingStock !== $unassignedStockLevels) {
        throw new \RuntimeException("INVARIANT VIOLATION: Holding stock ({$holdingStock}) != Unassigned stock levels ({$unassignedStockLevels}).");
    }

    // Check for any product-level discrepancies
    $discrepancies = [];
    foreach ($allAffectedPids as $pid) {
        $p = Product::find($pid);
        $slSum = (int)StockLevel::where('product_id', $pid)->sum('quantity_on_hand');
        if ($p && (int)$p->quantity !== $slSum) {
            $discrepancies[] = "PID {$pid} ({$p->name}): StockLevel sum {$slSum} != Product.quantity {$p->quantity}";
        }
    }

    if (!empty($discrepancies)) {
        throw new \RuntimeException("INVARIANT VIOLATION: Found " . count($discrepancies) . " product discrepancies: " . implode('; ', $discrepancies));
    }

    // Check for any variant product_id mismatches
    $mismatches = StockLevel::whereNotNull('variant_id')
        ->join('product_variants', 'inventory_stock_levels.variant_id', '=', 'product_variants.id')
        ->whereColumn('inventory_stock_levels.product_id', '!=', 'product_variants.product_id')
        ->count();

    if ($mismatches > 0) {
        throw new \RuntimeException("INVARIANT VIOLATION: Found {$mismatches} StockLevel records where product_id != variant.product_id.");
    }

    // Check negative stock
    $negativeStock = StockLevel::where('quantity_on_hand', '<', 0)->count();
    if ($negativeStock > 0) {
        throw new \RuntimeException("INVARIANT VIOLATION: Found {$negativeStock} negative StockLevel records.");
    }

    DB::commit();
    echo "================================================================" . PHP_EOL;
    echo "  SUCCESS: 100% OF ACCOUNTING STOCK FULLY SYNCHRONIZED TO CATALOG! " . PHP_EOL;
    echo "================================================================" . PHP_EOL;

} catch (\Throwable $e) {
    DB::rollBack();
    echo "ERROR: Reconciliation failed: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    exit(1);
}
