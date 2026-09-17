<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductImportExportService
{
    public function __construct(
        protected ProductSkuService $skuService,
        protected InventoryService $inventoryService
    ) {}

    /**
     * Export products and variants to a high-speed streaming CSV.
     */
    public function exportCsv(Builder $query): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="laijau_products_' . now()->format('Ymd_His') . '.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');
            // Write UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // CSV Header Row
            fputcsv($handle, [
                'Product ID',
                'Product Name',
                'Brand',
                'Model',
                'SKU',
                'Barcode (EAN-13)',
                'Category',
                'Retail Price (NPR)',
                'Cost Price (NPR)',
                'Wholesale Price (NPR)',
                'Total Stock',
                'Status',
                'Has Variants',
                'Variant SKU',
                'Variant Barcode',
                'Variant Color',
                'Variant Size',
                'Variant Price',
                'Variant Cost',
                'Variant Stock',
            ]);

            $query->with(['categories', 'variants'])->chunk(100, function ($products) use ($handle) {
                foreach ($products as $product) {
                    $categoryName = $product->categories->pluck('name')->join(', ');
                    $status = $product->is_published && $product->is_active
                        ? 'Active'
                        : ($product->is_active ? 'Draft' : 'Archived');

                    if ($product->variants->isNotEmpty()) {
                        foreach ($product->variants as $variant) {
                            fputcsv($handle, [
                                $product->id,
                                $product->name,
                                $product->brand ?? '',
                                $product->model ?? '',
                                $product->sku ?? '',
                                $product->barcode ?? '',
                                $categoryName,
                                number_format((float) ($product->price ?: 0), 2, '.', ''),
                                number_format((float) ($product->cost_price ?: 0), 2, '.', ''),
                                number_format((float) ($product->wholesale_price ?: 0), 2, '.', ''),
                                $product->total_stock,
                                $status,
                                'Yes',
                                $variant->sku ?? '',
                                $variant->barcode ?? '',
                                $variant->color ?? '',
                                $variant->size ?? '',
                                number_format((float) ($variant->price ?: $product->price ?: 0), 2, '.', ''),
                                number_format((float) ($variant->cost_price ?: $product->cost_price ?: 0), 2, '.', ''),
                                $variant->stock_quantity ?? 0,
                            ]);
                        }
                    } else {
                        fputcsv($handle, [
                            $product->id,
                            $product->name,
                            $product->brand ?? '',
                            $product->model ?? '',
                            $product->sku ?? '',
                            $product->barcode ?? '',
                            $categoryName,
                            number_format((float) ($product->price ?: 0), 2, '.', ''),
                            number_format((float) ($product->cost_price ?: 0), 2, '.', ''),
                            number_format((float) ($product->wholesale_price ?: 0), 2, '.', ''),
                            $product->quantity ?? 0,
                            $status,
                            'No',
                            '',
                            '',
                            '',
                            '',
                            '',
                            '',
                            '',
                        ]);
                    }
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Dry-run validation of an uploaded CSV file.
     * Returns a summary report with counts, errors, warnings, and valid parsed rows.
     */
    public function validateImportCsv(UploadedFile $file): array
    {
        $rows = [];
        $errors = [];
        $warnings = [];
        $validRows = [];
        $lineNumber = 1;

        if (($handle = fopen($file->getRealPath(), 'r')) !== false) {
            // Check for BOM
            $bom = fread($handle, 3);
            if ($bom !== chr(0xEF) . chr(0xBB) . chr(0xBF)) {
                rewind($handle);
            }

            $header = fgetcsv($handle, 4096);
            if (!$header) {
                return [
                    'valid' => false,
                    'message' => 'Empty CSV file or invalid format.',
                    'total_rows' => 0,
                    'valid_count' => 0,
                    'error_count' => 1,
                    'errors' => ['Header row missing'],
                    'warnings' => [],
                    'rows' => [],
                ];
            }

            // Normalize header names
            $normalizedHeader = array_map(fn ($col) => strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', (string) $col))), $header);

            while (($data = fgetcsv($handle, 4096)) !== false) {
                $lineNumber++;
                if (empty(array_filter($data))) {
                    continue; // Skip empty row
                }

                $row = array_combine($normalizedHeader, array_pad($data, count($normalizedHeader), ''));

                // Extract fields
                $name = trim($row['productname'] ?? $row['name'] ?? $row['title'] ?? '');
                $sku = trim($row['sku'] ?? '');
                $barcode = trim($row['barcode'] ?? $row['barcoddean13'] ?? '');
                $price = (float) ($row['retailpricenpr'] ?? $row['price'] ?? $row['retailprice'] ?? 0);
                $cost = (float) ($row['costpricenpr'] ?? $row['costprice'] ?? $row['cost'] ?? 0);
                $category = trim($row['category'] ?? '');
                $brand = trim($row['brand'] ?? '');
                $model = trim($row['model'] ?? '');
                $stock = (int) ($row['totalstock'] ?? $row['stock'] ?? $row['quantity'] ?? 0);
                $variantColor = trim($row['variantcolor'] ?? $row['color'] ?? '');
                $variantSize = trim($row['variantsize'] ?? $row['size'] ?? '');

                if (empty($name)) {
                    $errors[] = "Row #{$lineNumber}: Product Name is required.";
                    continue;
                }

                if ($price <= 0) {
                    $warnings[] = "Row #{$lineNumber} ('{$name}'): Retail price is 0 or missing.";
                }

                if (!empty($barcode) && !$this->skuService->validateEan13($barcode)) {
                    $warnings[] = "Row #{$lineNumber} ('{$name}'): Barcode '{$barcode}' is not a valid 13-digit EAN barcode; a compliant in-store barcode will be generated.";
                }

                $parsedItem = [
                    'line' => $lineNumber,
                    'name' => $name,
                    'brand' => $brand ?: null,
                    'model' => $model ?: null,
                    'sku' => $sku ?: null,
                    'barcode' => $barcode ?: null,
                    'category' => $category ?: 'General Retail',
                    'price' => $price,
                    'cost_price' => $cost > 0 ? $cost : null,
                    'stock' => max(0, $stock),
                    'variant_color' => $variantColor ?: null,
                    'variant_size' => $variantSize ?: null,
                ];

                $validRows[] = $parsedItem;
            }

            fclose($handle);
        }

        return [
            'valid' => count($errors) === 0,
            'total_rows' => $lineNumber - 1,
            'valid_count' => count($validRows),
            'error_count' => count($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'rows' => $validRows,
        ];
    }

    /**
     * Atomically commit validated rows into the production database.
     */
    public function commitImport(array $rows, int $warehouseId, ?User $user = null): array
    {
        $createdProducts = 0;
        $createdVariants = 0;
        $updatedProducts = 0;

        DB::transaction(function () use ($rows, $warehouseId, $user, &$createdProducts, &$createdVariants, &$updatedProducts) {
            foreach ($rows as $item) {
                // Find or create Category
                $cat = Category::firstOrCreate(
                    ['name' => $item['category']],
                    ['slug' => Str::slug($item['category']), 'is_active' => true]
                );

                // Check existing product by SKU or exact name
                $product = null;
                if (!empty($item['sku'])) {
                    $product = Product::where('sku', $item['sku'])->first();
                }
                if (!$product) {
                    $product = Product::where('name', $item['name'])->first();
                }

                if (!$product) {
                    $product = Product::create([
                        'name' => $item['name'],
                        'brand' => $item['brand'],
                        'model' => $item['model'],
                        'sku' => $item['sku'],
                        'barcode' => $item['barcode'],
                        'price' => $item['price'],
                        'cost_price' => $item['cost_price'],
                        'quantity' => $item['stock'],
                        'is_active' => true,
                        'is_published' => true,
                    ]);

                    $product->categories()->sync([$cat->id]);
                    $createdProducts++;

                    // Record initial opening stock in selected warehouse
                    if ($item['stock'] > 0) {
                        $this->inventoryService->recordStockMovement([
                            'warehouse_id' => $warehouseId,
                            'product_id' => $product->id,
                            'variant_id' => null,
                            'quantity' => $item['stock'],
                            'movement_type' => 'opening_stock',
                            'notes' => 'Product CSV Import',
                        ], $user);
                    }
                } else {
                    $product->update([
                        'brand' => $item['brand'] ?: $product->brand,
                        'model' => $item['model'] ?: $product->model,
                        'price' => $item['price'] > 0 ? $item['price'] : $product->price,
                        'cost_price' => $item['cost_price'] ?: $product->cost_price,
                    ]);
                    $updatedProducts++;
                }

                // Create variant if color or size specified
                if (!empty($item['variant_color']) || !empty($item['variant_size'])) {
                    $variant = ProductVariant::firstOrCreate(
                        [
                            'product_id' => $product->id,
                            'color' => $item['variant_color'] ?: 'Standard',
                            'size' => $item['variant_size'] ?: 'Standard',
                        ],
                        [
                            'price' => $item['price'],
                            'cost_price' => $item['cost_price'],
                            'stock_quantity' => $item['stock'],
                            'is_active' => true,
                        ]
                    );

                    if ($variant->wasRecentlyCreated) {
                        $createdVariants++;
                        if ($item['stock'] > 0) {
                            $this->inventoryService->recordStockMovement([
                                'warehouse_id' => $warehouseId,
                                'product_id' => $product->id,
                                'variant_id' => $variant->id,
                                'quantity' => $item['stock'],
                                'movement_type' => 'opening_stock',
                                'notes' => 'Variant CSV Import',
                            ], $user);
                        }
                    }
                }
            }
        });

        return [
            'created_products' => $createdProducts,
            'created_variants' => $createdVariants,
            'updated_products' => $updatedProducts,
        ];
    }
}
