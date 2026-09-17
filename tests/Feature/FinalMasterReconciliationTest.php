<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Operational\CatalogSyncService;
use App\Services\Operational\CategoryReconciliationService;
use App\Services\Security\ImageUploadSecurityService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinalMasterReconciliationTest extends TestCase
{
    use DatabaseTransactions;

    protected CatalogSyncService $syncService;
    protected CategoryReconciliationService $catService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncService = app(CatalogSyncService::class);
        $this->catService = app(CategoryReconciliationService::class);
    }

    /**
     * 1. Brand Safety & Title Normalization
     */
    public function test_brand_safety_removes_infringing_trademarks_and_marketing_claims(): void
    {
        // 1. Trademark stripping & neutral conversion
        $nike = $this->syncService->cleanCustomerFacingTitle('Nike Dunk Low Retro Panda - 42 Black/White');
        $this->assertStringNotContainsStringIgnoringCase('Nike', $nike);
        $this->assertStringNotContainsStringIgnoringCase('Dunk Low', $nike);
        $this->assertStringContainsString('42 Black/White', $nike);

        $jordan = $this->syncService->cleanCustomerFacingTitle('Air Jordan 1 High OG Chicago - Red 43');
        $this->assertStringNotContainsStringIgnoringCase('Jordan', $jordan);
        $this->assertStringContainsString('Red 43', $jordan);

        $timberland = $this->syncService->cleanCustomerFacingTitle('Timberland 6-Inch Premium Waterproof Boot Wheat 41');
        $this->assertStringNotContainsStringIgnoringCase('Timberland', $timberland);
        $this->assertStringNotContainsStringIgnoringCase('Waterproof', $timberland);
        $this->assertStringContainsString('Wheat 41', $timberland);

        $drmartens = $this->syncService->cleanCustomerFacingTitle('Dr. Martens 1460 8-Eye Leather Boot Cherry');
        $this->assertStringNotContainsStringIgnoringCase('Dr. Martens', $drmartens);
        $this->assertStringNotContainsStringIgnoringCase('Dr Martens', $drmartens);
        $this->assertStringContainsString('Cherry', $drmartens);

        $samba = $this->syncService->cleanCustomerFacingTitle('Adidas Samba OG Classic White 40');
        $this->assertStringNotContainsStringIgnoringCase('Adidas', $samba);
        $this->assertStringContainsString('White 40', $samba);

        // 2. Unsupported marketing claims removal
        $claims = $this->syncService->cleanCustomerFacingTitle('Citizen Shoes 100% Genuine Leather Handmade Orthopedic - 9999 Black');
        $this->assertStringNotContainsStringIgnoringCase('genuine leather', $claims);
        $this->assertStringNotContainsStringIgnoringCase('handmade', $claims);
        $this->assertStringNotContainsStringIgnoringCase('orthopedic', $claims);
        $this->assertStringNotContainsString('100%', $claims);
        $this->assertStringContainsString('Citizen Shoes', $claims);
        $this->assertStringContainsString('9999 Black', $claims);
    }

    /**
     * 2. Factual Description Generation
     */
    public function test_factual_description_generation_is_structured_and_safe(): void
    {
        $product = Product::create([
            'sku' => 'TEST-FACT-01',
            'name' => 'Citizen Shoes – 9999 Black',
            'type' => 'footwear',
            'price' => 2500.00,
            'is_active' => true,
        ]);

        $cat = Category::firstOrCreate(['name' => 'Boots', 'slug' => 'boots-' . uniqid()]);
        $product->categories()->sync([$cat->id]);

        ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'TEST-FACT-01-41',
            'size' => '41',
            'color' => 'Black',
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        $description = $this->syncService->generateFactualDescription($product);

        $this->assertNotEmpty($description);
        $this->assertStringContainsString('Overview', $description);
        $this->assertStringContainsString('Details', $description);
        $this->assertStringContainsString('Model Code', $description);
        $this->assertStringContainsString('TEST-FACT-01', $description);
        $this->assertStringContainsString('Color', $description);
        $this->assertStringContainsString('Black', $description);
        $this->assertStringContainsString('Available Sizes', $description);
        $this->assertStringContainsString('41', $description);

        // Must not contain unverified marketing buzzwords
        $this->assertStringNotContainsStringIgnoringCase('100% authentic', $description);
        $this->assertStringNotContainsStringIgnoringCase('genuine leather', $description);
        $this->assertStringNotContainsStringIgnoringCase('orthopedic', $description);
        $this->assertStringNotContainsStringIgnoringCase('waterproof gore-tex', $description);
    }

    /**
     * 3. Photo Gate & Publication Eligibility
     */
    public function test_photo_gate_publication_eligibility_logic(): void
    {
        // 1. Product without photo cannot be published
        $noPhoto = Product::create([
            'sku' => 'GATE-NO-PHOTO',
            'name' => 'No Photo Shoe',
            'price' => 2000,
            'is_active' => true,
            'is_published' => false,
            'featured_image' => null,
        ]);
        $cat = Category::firstOrCreate(['name' => 'Casual Shoes', 'slug' => 'casual-shoes-' . uniqid()]);
        $noPhoto->categories()->sync([$cat->id]);

        $res1 = $this->syncService->validatePublicationEligibility($noPhoto);
        $this->assertFalse($res1['eligible']);
        $this->assertContains('Product photo (physical file verified on disk)', $res1['missing']);

        // 2. Staging payment test product is never published
        $staging = new Product([
            'sku' => 'COD-KTM-01',
            'name' => 'Payment Test',
            'price' => 100,
            'is_active' => true,
            'is_published' => false,
        ]);
        $res2 = $this->syncService->validatePublicationEligibility($staging);
        $this->assertFalse($res2['eligible']);
        $this->assertContains('Staging payment test product cannot be published', $res2['missing']);

        // 3. Product with verified real file on disk is eligible
        $imgName = 'test_gate_' . uniqid() . '.jpg';
        $storageDir = storage_path('app/public');
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }
        $finalPath = $storageDir . '/' . $imgName;
        file_put_contents($finalPath, 'fake-image-bytes-for-gate-test');

        $withPhoto = Product::create([
            'sku' => 'PRD-PHOTO-OK-' . uniqid(),
            'name' => 'With Photo Shoe',
            'price' => 2500,
            'is_active' => true,
            'is_published' => false,
            'featured_image' => $imgName,
        ]);
        $withPhoto->categories()->sync([$cat->id]);

        $res3 = $this->syncService->validatePublicationEligibility($withPhoto);
        $this->assertTrue($res3['eligible']);

        // Cleanup
        if (file_exists($finalPath)) {
            @unlink($finalPath);
        }
    }

    /**
     * 4. Image Upload Security Service
     */
    public function test_image_upload_security_service_enforcement(): void
    {
        // 1. Valid image passes and creates safe filename
        $validImage = UploadedFile::fake()->image('test_product.jpg', 800, 800);
        ImageUploadSecurityService::validateImageFile($validImage);
        $safeName = ImageUploadSecurityService::generateSafeFilename($validImage);
        $this->assertMatchesRegularExpression('/^img_\d{8}_\d{6}_[a-zA-Z0-9]{24}\.jpg$/', $safeName);

        // 2. Disallowed executable script fails
        $tempFile = tempnam(sys_get_temp_dir(), 'mal');
        file_put_contents($tempFile, 'plain text');
        $fakeUpload = new UploadedFile($tempFile, 'script.php', 'application/x-php', null, true);

        $this->expectException(\InvalidArgumentException::class);
        ImageUploadSecurityService::validateImageFile($fakeUpload);
    }

    public function test_image_upload_security_blocks_spoofed_image_files(): void
    {
        // PHP script disguised as .jpg fails binary inspection
        $phpContent = "<?php echo 'malicious code'; ?>";
        $tempFile = tempnam(sys_get_temp_dir(), 'mal');
        file_put_contents($tempFile, $phpContent);
        $fakeUpload = new UploadedFile($tempFile, 'shell.php.jpg', 'image/jpeg', null, true);

        $this->expectException(\InvalidArgumentException::class);
        ImageUploadSecurityService::validateImageFile($fakeUpload);
    }

    /**
     * 5. Checkout Idempotency
     */
    public function test_checkout_idempotency_prevents_duplicate_orders(): void
    {
        $cat = Category::firstOrCreate(['name' => 'Sneakers', 'slug' => 'sneakers-' . uniqid()]);
        $product = Product::create([
            'sku' => 'IDEMP-PROD-' . uniqid(),
            'name' => 'Idempotency Product',
            'price' => 1500.00,
            'quantity' => 10,
            'track_quantity' => true,
            'is_active' => true,
            'is_published' => true,
        ]);
        $product->categories()->sync([$cat->id]);

        $payload = [
            'customer' => [
                'first_name' => 'Idempotent',
                'last_name' => 'Tester',
                'phone' => '9841999888',
                'email' => 'idempotent@example.com',
                'province' => 'Bagmati Province',
                'district' => 'Kathmandu',
                'municipality' => 'Kathmandu Metropolitan',
                'ward' => '3',
                'tole' => 'Lazimpat',
            ],
            'payment_method' => 'cod',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
            'idempotency_key' => 'test-idemp-key-' . uniqid(),
        ];

        // Initial submission
        $res1 = $this->postJson('/api/checkout/process', $payload);
        $res1->assertStatus(200);
        $orderNumber1 = $res1->json('order_number');
        $this->assertNotEmpty($orderNumber1);

        $orderCountAfterFirst = Order::where('order_number', $orderNumber1)->count();
        $this->assertEquals(1, $orderCountAfterFirst);

        // Immediate duplicate submission with exact same key
        $res2 = $this->postJson('/api/checkout/process', $payload);
        $res2->assertStatus(200);
        $orderNumber2 = $res2->json('order_number');

        // Must return the identical order number without creating a second record
        $this->assertEquals($orderNumber1, $orderNumber2);
        $totalOrdersWithNumber = Order::where('order_number', $orderNumber1)->count();
        $this->assertEquals(1, $totalOrdersWithNumber, "Duplicate submission must NOT create a new order record.");
    }

    /**
     * 6. Live Production MySQL Database Invariants
     * (Runs against the live MySQL LAIJAU database)
     */
    public function test_live_production_catalog_and_accounting_invariants(): void
    {
        try {
            config(['database.connections.mysql.database' => 'LAIJAU']);
            DB::purge('mysql');
            $pdo = DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL connection error: ' . $e->getMessage());
            return;
        }

        $mysql = DB::connection('mysql');

        // 1. Total master products (1,325 authentic products after purging 16 test products)
        $totalProducts = $mysql->table('products')->count();
        $this->assertGreaterThanOrEqual(1325, $totalProducts, "Total master products must remain at least 1,325.");

        // 2. Photo Gate counts
        $published = $mysql->table('products')->where('is_published', 1)->count();
        $unpublished = $mysql->table('products')->where('is_published', 0)->count();
        $this->assertGreaterThanOrEqual(532, $published, "At least 532 products with physically verified photos must be published.");
        $this->assertGreaterThanOrEqual(790, $unpublished, "At least 790 products without photos must be unpublished.");

        // 3. Category coverage (zero uncategorized products)
        $uncategorized = $mysql->table('products')
            ->leftJoin('category_product', 'products.id', '=', 'category_product.product_id')
            ->whereNull('category_product.category_id')
            ->count();
        $this->assertEquals(0, $uncategorized, "Zero products should remain uncategorized.");

        // 4. Double-Entry Accounting Invariant (Debits == Credits)
        $entryDebit = (float) $mysql->table('accounting_journal_entries')->sum('total_debit');
        $entryCredit = (float) $mysql->table('accounting_journal_entries')->sum('total_credit');
        $this->assertEqualsWithDelta($entryDebit, $entryCredit, 0.001, "Journal entries total debit must equal total credit.");

        $lineDebit = (float) $mysql->table('accounting_journal_entry_lines')->sum('debit');
        $lineCredit = (float) $mysql->table('accounting_journal_entry_lines')->sum('credit');
        $this->assertEqualsWithDelta($lineDebit, $lineCredit, 0.001, "Journal entry lines total debit must equal total credit.");

        // 5. Historical Business Data Intact
        $posCount = $mysql->table('offline_sales')->count();
        $posSum = (float) $mysql->table('offline_sales')->sum('total_amount');
        $this->assertGreaterThanOrEqual(11170, $posCount, "Historical POS offline sales count must be at least 11,170.");
        $this->assertGreaterThanOrEqual(33266992.00, $posSum, "Historical POS offline sales total must be at least 33,266,992.00 NPR.");

        $orderCount = $mysql->table('orders')->count();
        $this->assertGreaterThanOrEqual(1950, $orderCount, "Historical online orders count must be at least 1,950.");
    }
}
