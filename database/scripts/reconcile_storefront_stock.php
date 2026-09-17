<?php

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Inventory\StockLevel;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;

echo "=======================================================\n";
echo "LAIJAU STOREFRONT INVENTORY RECONCILIATION SCRIPT\n";
echo "=======================================================\n\n";

// 1. Pre-condition Assertions
$initialTotalStock = (int)StockLevel::sum('quantity_on_hand');
echo "Initial total physical stock: {$initialTotalStock}\n";
if ($initialTotalStock !== 2721) {
    throw new \RuntimeException("ABORT: Initial stock is {$initialTotalStock}, expected exactly 2721.");
}

DB::beginTransaction();

try {
    // 2. Reconcile Product 366 (Brown Microfiber Boots)
    // Row 0 of shoes_stock.json: code 1001.0, size 40.0, qty 6 -> Variant 5087 (Size 40)
    $stock366 = StockLevel::where('product_id', 366)->whereNull('variant_id')->first();
    $var5087 = ProductVariant::where('product_id', 366)->where('size', '40')->first();
    if ($stock366 && $var5087) {
        echo "Linking StockLevel ID {$stock366->id} (qty {$stock366->quantity_on_hand}) to Variant ID {$var5087->id} (Size 40)...\n";
        $stock366->variant_id = $var5087->id;
        $stock366->save();
    }

    // 3. Reconcile Product 364 (Inspired Black Microfiber Boots)
    // Row 45 of shoes_stock.json: code 350V2, size 39.0, qty 4 -> Variant 5076 (Size 39)
    $stock364 = StockLevel::where('product_id', 364)->whereNull('variant_id')->first();
    $var5076 = ProductVariant::where('product_id', 364)->where('size', '39')->first();
    if ($stock364 && $var5076) {
        echo "Linking StockLevel ID {$stock364->id} (qty {$stock364->quantity_on_hand}) to Variant ID {$var5076->id} (Size 39)...\n";
        $stock364->variant_id = $var5076->id;
        $stock364->save();
    }

    // 4. Reconcile Product 132 (Basketball Style Sneakers)
    // Row 33 of shoes_stock.json: code 855.0, size 44.0, qty 2
    $var44 = ProductVariant::firstOrCreate([
        'product_id' => 132,
        'size' => '44',
    ], [
        'sku' => 'JBaW|2BB-44',
        'color' => 'Black',
        'price' => 3000.00,
        'price_npr' => 3000.00,
        'cost_price' => 1200.00,
        'is_active' => true,
        'stock_quantity' => 0,
    ]);

    $stock132 = StockLevel::where('product_id', 132)->whereNull('variant_id')->first();
    if ($stock132) {
        echo "Linking StockLevel ID {$stock132->id} (qty {$stock132->quantity_on_hand}) to Variant ID {$var44->id} (Size 44)...\n";
        $stock132->variant_id = $var44->id;
        $stock132->save();
    }

    // 5. Reassign Uncataloged Shoe Fallback Records (717 units)
    // These 69 records were dumped into Product 130 because of $matched['product_id'] ?? 130 fallback.
    // Reassign them to an unpublished holding container: LAI-FTW-UNMATCHED.
    $holdingProduct = Product::firstOrCreate([
        'sku' => 'LAI-FTW-UNMATCHED',
    ], [
        'name' => 'Uncataloged Footwear Warehouse Inventory',
        'slug' => 'uncataloged-footwear-warehouse-inventory',
        'type' => 'footwear',
        'is_published' => false,
        'is_active' => true,
        'price' => 0,
        'cost_price' => 500,
        'quantity' => 0,
        'description' => 'Authoritative physical inventory of uncataloged footwear items extracted from warehouse physical stock records.',
    ]);

    $reassignedCount = StockLevel::where('product_id', 130)
        ->whereNull('variant_id')
        ->update(['product_id' => $holdingProduct->id]);
    echo "Reassigned {$reassignedCount} uncataloged footwear records from Product 130 to holding container (ID {$holdingProduct->id}, SKU LAI-FTW-UNMATCHED).\n";

    // 6. Run Synchronization Engine
    $invService = app(InventoryService::class);
    $productsToSync = [366, 364, 132, 130, $holdingProduct->id];
    foreach ($productsToSync as $pid) {
        $invService->syncLegacyStockAttributes($pid);
    }

    // Also sync all other published products to ensure perfect consistency
    $allPublished = Product::where('is_published', true)->pluck('id');
    foreach ($allPublished as $pubPid) {
        $invService->syncLegacyStockAttributes($pubPid);
    }

    // 7. Post-condition Invariants Assertion
    $finalTotalStock = (int)StockLevel::sum('quantity_on_hand');
    echo "\nPost-reconciliation physical stock total: {$finalTotalStock}\n";
    if ($finalTotalStock !== 2721) {
        throw new \RuntimeException("ABORT: Post-reconciliation stock is {$finalTotalStock}, expected exactly 2721.");
    }

    DB::commit();
    echo "\n=== RECONCILIATION COMMITTED SUCCESSFULLY ===\n\n";

    // Display summary
    $p366 = Product::find(366);
    $p364 = Product::find(364);
    $p132 = Product::find(132);
    $p130 = Product::find(130);
    $pHold = Product::find($holdingProduct->id);

    echo "Product 366 ({$p366->name}): quantity = {$p366->quantity}\n";
    foreach ($p366->variants as $v) {
        if ($v->stock_quantity > 0) echo "   -> Variant {$v->sku} (Size {$v->size}): {$v->stock_quantity} units\n";
    }

    echo "Product 364 ({$p364->name}): quantity = {$p364->quantity}\n";
    foreach ($p364->variants as $v) {
        if ($v->stock_quantity > 0) echo "   -> Variant {$v->sku} (Size {$v->size}): {$v->stock_quantity} units\n";
    }

    echo "Product 132 ({$p132->name}): quantity = {$p132->quantity}\n";
    foreach ($p132->variants as $v) {
        if ($v->stock_quantity > 0) echo "   -> Variant {$v->sku} (Size {$v->size}): {$v->stock_quantity} units\n";
    }

    echo "Product 130 ({$p130->name}): quantity = {$p130->quantity}\n";
    echo "Holding Product ({$pHold->name}): quantity = {$pHold->quantity}\n";
} catch (\Throwable $e) {
    DB::rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
    throw $e;
}
