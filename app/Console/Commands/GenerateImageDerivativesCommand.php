<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ImageOptimizerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class GenerateImageDerivativesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:generate-derivatives
                            {--force : Force regenerate all derivatives even if already existing}
                            {--disk=public : Storage disk name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely generate optimized WebP derivatives (thumbnail ~200px, card ~400px, large ~1000px) for all product and storefront images.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $disk = (string) $this->option('disk');

        $this->info('Starting Laijau Image Derivative Generation...');
        $this->info("Settings: Force = " . ($force ? 'Yes' : 'No') . " | Disk = {$disk}");
        $this->newLine();

        $processedImages = [];
        $generatedCount = 0;
        $skippedCount = 0;
        $failedCount = 0;

        $storage = Storage::disk($disk);
        $basePath = $storage->path('');

        if (!File::exists($basePath)) {
            $this->error("Storage path does not exist: {$basePath}");
            return Command::FAILURE;
        }

        // 1. Process Products (featured_image & images)
        $this->info('Phase 1: Scanning Products...');
        $productQuery = Product::query()->select(['id', 'name', 'featured_image', 'images']);
        $totalProducts = $productQuery->count();
        $bar = $this->output->createProgressBar($totalProducts);
        $bar->start();

        $productQuery->chunk(50, function ($products) use (&$processedImages, &$generatedCount, &$skippedCount, &$failedCount, $disk, $force, $bar) {
            foreach ($products as $product) {
                // Featured image
                if (!empty($product->featured_image)) {
                    $this->processPath(
                        $product->featured_image,
                        $processedImages,
                        $generatedCount,
                        $skippedCount,
                        $failedCount,
                        $disk,
                        $force
                    );
                }

                // Gallery images
                if (!empty($product->images) && is_array($product->images)) {
                    foreach ($product->images as $img) {
                        if (!empty($img) && is_string($img)) {
                            $this->processPath(
                                $img,
                                $processedImages,
                                $generatedCount,
                                $skippedCount,
                                $failedCount,
                                $disk,
                                $force
                            );
                        }
                    }
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        // 2. Process Categories & Collections
        $this->info('Phase 2: Scanning Categories & Collections...');
        $categories = Category::whereNotNull('image')->pluck('image');
        foreach ($categories as $catImg) {
            $this->processPath($catImg, $processedImages, $generatedCount, $skippedCount, $failedCount, $disk, $force);
        }

        $collections = Collection::whereNotNull('image')->pluck('image');
        foreach ($collections as $colImg) {
            $this->processPath($colImg, $processedImages, $generatedCount, $skippedCount, $failedCount, $disk, $force);
        }

        // 3. Process Product Variants
        $this->info('Phase 3: Scanning Product Variants...');
        $variants = ProductVariant::whereNotNull('image')->pluck('image');
        foreach ($variants as $varImg) {
            $this->processPath($varImg, $processedImages, $generatedCount, $skippedCount, $failedCount, $disk, $force);
        }

        // 4. Scan Raw storage files in product and products directories
        $this->info('Phase 4: Scanning remaining storage assets in product directories...');
        $scanDirs = ['product', 'products', 'category', 'categories', 'collections'];
        foreach ($scanDirs as $dir) {
            $dirPath = $basePath . DIRECTORY_SEPARATOR . $dir;
            if (File::exists($dirPath)) {
                $files = File::files($dirPath);
                foreach ($files as $file) {
                    $ext = strtolower($file->getExtension());
                    if (in_array($ext, ['webp', 'png', 'jpg', 'jpeg'])) {
                        $rel = "{$dir}/" . $file->getFilename();
                        $this->processPath($rel, $processedImages, $generatedCount, $skippedCount, $failedCount, $disk, $force);
                    }
                }
            }
        }

        $this->newLine();
        $this->info('Derivative Generation Complete!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Unique Source Images Evaluated', count($processedImages)],
                ['Derivatives Generated / Updated', $generatedCount],
                ['Derivatives Skipped (Already Optimal)', $skippedCount],
                ['Failures / Missing Files', $failedCount],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * Process an individual image path safely.
     */
    private function processPath(
        string $rawPath,
        array &$processedImages,
        int &$generatedCount,
        int &$skippedCount,
        int &$failedCount,
        string $disk,
        bool $force
    ): void {
        $clean = ImageOptimizerService::normalizePath($rawPath);
        if (empty($clean) || isset($processedImages[$clean])) {
            return;
        }

        $processedImages[$clean] = true;

        try {
            $results = ImageOptimizerService::generateDerivativesForPath($clean, $disk, $force);
            if (!empty($results)) {
                $generatedCount += count($results);
            } else {
                $skippedCount++;
            }
        } catch (\Throwable $e) {
            $failedCount++;
            $this->warn("Failed generating derivative for [{$clean}]: " . $e->getMessage());
        }
    }
}
