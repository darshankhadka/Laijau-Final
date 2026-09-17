<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Models\Setting;
use App\Services\ImageOptimizerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class OptimizeImagesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:optimize {--quality=82 : Compression quality between 1 and 100} {--max-width=1800 : Maximum width in pixels} {--max-height=1800 : Maximum height in pixels} {--no-webp : Keep original file extension instead of converting to WebP}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compress, resize, and optimize all uploaded storefront media assets to WebP for massive memory and bandwidth savings.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $quality = (int) $this->option('quality');
        $maxWidth = (int) $this->option('max-width');
        $maxHeight = (int) $this->option('max-height');
        $convertToWebp = !$this->option('no-webp');

        $this->info("Starting Laijau Media Asset Optimization...");
        $this->info("Settings: Max Dimensions = {$maxWidth}x{$maxHeight}px | Quality = {$quality}% | Format = " . ($convertToWebp ? 'WebP' : 'Original'));

        $publicDiskPath = Storage::disk('public')->path('');
        if (!File::exists($publicDiskPath)) {
            $this->warn("Storage directory does not exist: {$publicDiskPath}");
            return Command::SUCCESS;
        }

        $files = File::allFiles($publicDiskPath);
        $imageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        $totalOriginalBytes = 0;
        $totalOptimizedBytes = 0;
        $processedCount = 0;
        $conversionMap = [];

        $progressBar = $this->output->createProgressBar(count($files));
        $progressBar->start();

        foreach ($files as $file) {
            $ext = strtolower($file->getExtension());
            if (!in_array($ext, $imageExtensions)) {
                $progressBar->advance();
                continue;
            }

            $originalPath = $file->getRealPath();
            $originalSize = $file->getSize();
            $totalOriginalBytes += $originalSize;

            $relPath = str_replace($publicDiskPath . DIRECTORY_SEPARATOR, '', $originalPath);
            $relPath = str_replace('\\', '/', $relPath);

            $optimizedFullPath = ImageOptimizerService::optimizeFile(
                $originalPath,
                $maxWidth,
                $maxHeight,
                $quality,
                $convertToWebp
            );

            if ($optimizedFullPath && file_exists($optimizedFullPath)) {
                $optimizedSize = filesize($optimizedFullPath);
                $totalOptimizedBytes += $optimizedSize;
                $processedCount++;

                $newRelPath = str_replace($publicDiskPath . DIRECTORY_SEPARATOR, '', $optimizedFullPath);
                $newRelPath = str_replace('\\', '/', $newRelPath);

                if ($relPath !== $newRelPath) {
                    $conversionMap[$relPath] = $newRelPath;
                }
            } else {
                $totalOptimizedBytes += $originalSize;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Update database records if files changed extensions
        if (!empty($conversionMap)) {
            $this->info("Updating database references for " . count($conversionMap) . " converted media paths...");

            foreach ($conversionMap as $oldPath => $newPath) {
                // 1. Update Product featured_image
                DB::table('products')
                    ->where('featured_image', $oldPath)
                    ->update(['featured_image' => $newPath]);

                // 2. Update Product images JSON
                $productsWithImages = DB::table('products')->whereNotNull('images')->get(['id', 'images']);
                foreach ($productsWithImages as $p) {
                    $imgs = json_decode($p->images, true);
                    if (is_array($imgs) && in_array($oldPath, $imgs)) {
                        $updatedImgs = array_map(fn($img) => $img === $oldPath ? $newPath : $img, $imgs);
                        DB::table('products')->where('id', $p->id)->update(['images' => json_encode($updatedImgs)]);
                    }
                }

                // 3. Update Product Variants image
                DB::table('product_variants')
                    ->where('image', $oldPath)
                    ->update(['image' => $newPath]);

                // 4. Update Category image
                DB::table('categories')
                    ->where('image', $oldPath)
                    ->update(['image' => $newPath]);

                // 5. Update Collection image
                DB::table('collections')
                    ->where('image', $oldPath)
                    ->update(['image' => $newPath]);

                // 6. Update Settings store_logo
                DB::table('settings')
                    ->where('value', $oldPath)
                    ->update(['value' => $newPath]);
            }
        }

        $savedBytes = max(0, $totalOriginalBytes - $totalOptimizedBytes);
        $savedMB = round($savedBytes / 1024 / 1024, 2);
        $percentSaved = $totalOriginalBytes > 0 ? round(($savedBytes / $totalOriginalBytes) * 100, 1) : 0;

        $this->info("Optimization Complete!");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Images Processed', $processedCount],
                ['Original Storage Size', round($totalOriginalBytes / 1024 / 1024, 2) . ' MB'],
                ['Optimized Storage Size', round($totalOptimizedBytes / 1024 / 1024, 2) . ' MB'],
                ['Disk Memory Saved', "{$savedMB} MB ({$percentSaved}% reduction)"],
            ]
        );

        return Command::SUCCESS;
    }
}
