<?php

namespace Tests\Feature;

use App\Filament\Pages\OfflineSales;
use App\Models\OfflineSale;
use App\Models\OfflineSaleItem;
use App\Models\PosPrintJob;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\PrintAgent\EscPosReceiptBuilder;
use App\Services\PrintAgent\PrintAgentService;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class LocalPrintAgentTest extends TestCase
{
    protected string $validToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validToken = (string) config('services.print_agent.token', 'laijau_showroom_counter_1_secure_key');
        PosPrintJob::where('station_id', 'showroom_counter_1')->whereIn('status', ['queued', 'printing'])->delete();
    }

    protected function createSampleOfflineSale(): OfflineSale
    {
        $cashier = User::firstOrCreate(
            ['email' => 'admin@laijau.com'],
            ['name' => 'Admin Cashier', 'password' => bcrypt('Laijau2026!'), 'role' => 'admin']
        );

        $product = Product::firstOrCreate(
            ['sku' => 'PRINT-TEST-01'],
            ['name' => 'Thermal Test Jacket', 'price' => 3500, 'is_active' => true, 'is_published' => false]
        );

        $variant = ProductVariant::firstOrCreate(
            ['sku' => 'PRINT-TEST-01-BLK'],
            ['product_id' => $product->id, 'price' => 3500, 'stock_quantity' => 20, 'color' => 'Black', 'size' => 'L', 'is_active' => true]
        );

        $sale = OfflineSale::create([
            'sale_number' => 'TEST-PRN-' . strtoupper(Str::random(6)),
            'user_id' => $cashier->id,
            'customer_name' => 'Suman Shrestha',
            'customer_phone' => '9841234567',
            'total_amount' => 3500.00,
            'subtotal' => 3500.00,
            'discount_amount' => 0.00,
            'shipping_amount' => 0.00,
            'payment_method' => 'cash',
            'cash_received' => 4000.00,
            'change_given' => 500.00,
            'sales_channel' => 'showroom',
            'staff_name' => $cashier->name,
            'sold_at' => now(),
        ]);

        OfflineSaleItem::create([
            'offline_sale_id' => $sale->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'product_name' => $product->name,
            'sku' => $variant->sku,
            'color' => $variant->color,
            'size' => $variant->size,
            'quantity' => 1,
            'unit_price' => 3500.00,
            'total_price' => 3500.00,
        ]);

        return $sale;
    }

    public function test_print_agent_api_rejects_unauthorized_requests(): void
    {
        // 1. Missing token
        $response = $this->getJson('/api/v1/print-agent/health');
        $response->assertStatus(401)
            ->assertJsonStructure(['error']);

        // 2. Invalid token
        $responseBad = $this->withHeader('X-Print-Agent-Key', 'wrong-invalid-token')
            ->getJson('/api/v1/print-agent/health');
        $responseBad->assertStatus(401);
    }

    public function test_print_agent_api_health_succeeds_with_valid_token(): void
    {
        $response = $this->withHeader('X-Print-Agent-Key', $this->validToken)
            ->getJson('/api/v1/print-agent/health?station_id=showroom_counter_1');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'online',
                'station_id' => 'showroom_counter_1',
            ])
            ->assertJsonStructure([
                'status',
                'station_id',
                'server_time',
                'queue' => ['queued', 'printing', 'printed_today', 'failed_today'],
            ]);
    }

    public function test_offline_sale_creates_idempotent_print_job(): void
    {
        $sale = $this->createSampleOfflineSale();
        $service = app(PrintAgentService::class);

        // First call creates job
        $job1 = $service->queueOfflineSaleReceipt($sale, 'showroom_counter_1');
        $this->assertInstanceOf(PosPrintJob::class, $job1);
        $this->assertEquals('queued', $job1->status);
        $this->assertEquals('pos_sale_' . $sale->id . '_receipt', $job1->idempotency_key);
        $this->assertNotEmpty($job1->raw_escpos_base64);

        // Second call returns exact same job without duplicate insertion
        $job2 = $service->queueOfflineSaleReceipt($sale, 'showroom_counter_1');
        $this->assertEquals($job1->id, $job2->id);
        $this->assertEquals($job1->uuid, $job2->uuid);

        // Total records in DB for this idempotency key is exactly 1
        $count = PosPrintJob::where('idempotency_key', 'pos_sale_' . $sale->id . '_receipt')->count();
        $this->assertEquals(1, $count);
    }

    public function test_print_agent_poll_and_status_progression(): void
    {
        $sale = $this->createSampleOfflineSale();
        $service = app(PrintAgentService::class);
        $job = $service->queueOfflineSaleReceipt($sale, 'showroom_counter_1');

        // Poll endpoint claims the queued job
        $pollResponse = $this->withHeader('X-Print-Agent-Key', $this->validToken)
            ->getJson('/api/v1/print-agent/jobs/poll?station_id=showroom_counter_1');

        $pollResponse->assertStatus(200)
            ->assertJson([
                'has_job' => true,
                'job' => [
                    'uuid' => $job->uuid,
                    'station_id' => 'showroom_counter_1',
                    'idempotency_key' => $job->idempotency_key,
                ],
            ]);

        // Status transitioned to printing
        $job->refresh();
        $this->assertEquals('printing', $job->status);
        $this->assertEquals(1, $job->attempts);

        // Report status: printed
        $statusResponse = $this->withHeader('X-Print-Agent-Key', $this->validToken)
            ->postJson('/api/v1/print-agent/jobs/' . $job->uuid . '/status', [
                'status' => 'printed',
            ]);

        $statusResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'status' => 'printed',
            ]);

        $job->refresh();
        $this->assertEquals('printed', $job->status);
        $this->assertNotNull($job->printed_at);

        // Subsequent poll returns no jobs
        $emptyPoll = $this->withHeader('X-Print-Agent-Key', $this->validToken)
            ->getJson('/api/v1/print-agent/jobs/poll?station_id=showroom_counter_1');

        $emptyPoll->assertStatus(200)
            ->assertJson(['has_job' => false, 'job' => null]);
    }

    public function test_escpos_builder_contains_statutory_non_tax_notice_and_esc_commands(): void
    {
        $sale = $this->createSampleOfflineSale();
        $builder = EscPosReceiptBuilder::buildFromOfflineSale($sale);

        $binary = $builder->getBinary();

        // 1. Check ESC/POS initialization command
        $this->assertStringStartsWith(EscPosReceiptBuilder::ESC . "@", $binary);

        // 2. Check Cut command (GS V 66 0)
        $this->assertStringContainsString(EscPosReceiptBuilder::GS . "V\x42\x00", $binary);

        // 3. Verify cash drawer pulse command is NOT present (cash drawers removed)
        $this->assertStringNotContainsString(EscPosReceiptBuilder::ESC . "p", $binary, 'Must NOT contain cash drawer pulse command');

        // 4. Check Mandatory Statutory Notice
        $this->assertStringContainsString('THIS IS NOT A TAX INVOICE', $binary);
        $this->assertStringContainsString('FOR LAIJAU INTERNAL USE ONLY', $binary);
        $this->assertStringContainsString('PLEASE RETAIN YOUR TAX INVOICE FROM THE COUNTER', $binary);

        // 5. Check Brand & Store details
        $this->assertStringContainsString('LAIJAU', $binary);
        $this->assertStringContainsString($sale->sale_number, $binary);
        $this->assertStringContainsString('TOTAL PAID:', $binary);
    }

    public function test_pos_livewire_print_via_agent_queues_reprint_job(): void
    {
        $sale = $this->createSampleOfflineSale();
        $cashier = User::where('email', 'admin@laijau.com')->first();

        $beforeCount = PosPrintJob::count();

        Livewire::actingAs($cashier, 'admin')
            ->test(OfflineSales::class)
            ->call('printViaAgent', $sale->id)
            ->assertNotified('Receipt Sent to Print Agent');

        $afterCount = PosPrintJob::count();
        $this->assertGreaterThan($beforeCount, $afterCount);

        $latestJob = PosPrintJob::latest('id')->first();
        $this->assertEquals($sale->id, $latestJob->source_id);
        $this->assertEquals('queued', $latestJob->status);
        $this->assertStringContainsString('reprint', $latestJob->idempotency_key);
    }
}
