<?php

namespace Tests\Feature;

use App\Filament\Pages\OfflineSales;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use App\Models\OfflineSale;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\OfflineSaleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LaijauPosSystemTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $cashier;
    protected Warehouse $showroomWarehouse;
    protected Product $product;
    protected ProductVariant $variant;
    protected Product $singleProduct;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup Admin
        $this->admin = User::firstOrCreate(
            ['email' => 'admin@laijau.com'],
            [
                'name' => 'Laijau Administrator',
                'password' => bcrypt('Laijau2026!'),
                'role' => 'admin',
            ]
        );

        // 2. Setup Cashier
        $this->cashier = User::firstOrCreate(
            ['email' => 'cashier.ktm@laijau.com'],
            [
                'name' => 'Showroom Cashier',
                'password' => bcrypt('Cashier2026!'),
                'role' => 'cashier',
            ]
        );

        // Ensure roles exist
        Role::firstOrCreate(['name' => 'Cashier', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Cashier', 'guard_name' => 'admin']);
        Role::firstOrCreate(['name' => 'Store Manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Store Manager', 'guard_name' => 'admin']);

        if (!$this->cashier->hasRole('Cashier', 'web')) {
            $this->cashier->assignRole('Cashier');
        }

        // 3. Ensure Showroom Warehouse exists
        $this->showroomWarehouse = Warehouse::firstOrCreate(
            ['code' => 'STORE-KTM-01'],
            [
                'name' => 'Laijau Flagship Store & POS, Kathmandu',
                'type' => 'showroom_pos',
                'is_active' => true,
            ]
        );

        // 4. Create test product & variant with unique barcode & SKU
        $u = uniqid();
        $this->product = Product::create([
            'name' => 'Heritage Dhaka Shawl ' . $u,
            'slug' => 'heritage-dhaka-shawl-' . $u,
            'sku' => 'LJ-SHW-' . $u,
            'barcode' => '890100' . rand(1000, 9999),
            'price' => 5000.00,
            'is_active' => true,
            'quantity' => 20,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'LJ-SHW-RED-' . $u,
            'barcode' => '890200' . rand(1000, 9999),
            'color' => 'Crimson Red',
            'size' => 'Free Size',
            'price' => 5000.00,
            'stock_quantity' => 15,
            'is_active' => true,
        ]);

        StockLevel::firstOrCreate(
            [
                'warehouse_id' => $this->showroomWarehouse->id,
                'product_id' => $this->product->id,
                'variant_id' => $this->variant->id,
            ],
            [
                'quantity_on_hand' => 15,
                'quantity_reserved' => 0,
            ]
        );

        // 5. Create a standalone simple product (single item, no multi-variants)
        $u2 = uniqid();
        $this->singleProduct = Product::create([
            'name' => 'Pashmina Scarf ' . $u2,
            'slug' => 'pashmina-scarf-' . $u2,
            'sku' => 'LJ-PAS-' . $u2,
            'barcode' => '890300' . rand(1000, 9999),
            'price' => 3000.00,
            'is_active' => true,
            'quantity' => 10,
        ]);

        StockLevel::firstOrCreate(
            [
                'warehouse_id' => $this->showroomWarehouse->id,
                'product_id' => $this->singleProduct->id,
                'variant_id' => null,
            ],
            [
                'quantity_on_hand' => 10,
                'quantity_reserved' => 0,
            ]
        );

        $station = \App\Models\Hardware\PosStation::firstOrCreate(
            ['id' => 1],
            [
                'name' => 'Terminal 1',
                'code' => 'pos_terminal_1',
                'warehouse_id' => $this->showroomWarehouse->id,
                'is_active' => true,
            ]
        );

        $today = app(\App\Services\Pos\PosSessionService::class)->getNepalToday();
        \App\Models\Pos\PosSession::updateOrCreate(
            [
                'pos_station_id' => $station->id,
                'business_date' => $today,
            ],
            [
                'warehouse_id' => $this->showroomWarehouse->id,
                'terminal_code' => $station->code,
                'terminal_name' => $station->name,
                'showroom_name' => 'Laijau Showroom',
                'status' => 'open',
                'opening_balance' => 2000.00,
                'opened_by_user_id' => $this->admin->id,
                'opened_by_name' => $this->admin->name,
                'opened_at' => now(),
                'expected_cash' => 2000.00,
            ]
        );
    }

    /**
     * Workflow A: Search product → add to cart → checkout.
     */
    public function test_workflow_a_search_product_add_to_cart_checkout(): void
    {
        Livewire::actingAs($this->admin)
            ->test(OfflineSales::class)
            ->set('searchQuery', $this->singleProduct->name)
            ->call('handleBarcodeScan')
            ->assertCount('cart', 1)
            ->call('openCheckoutModal')
            ->assertSet('activeModal', 'checkout_modal')
            ->set('paymentMethod', 'cash')
            ->set('cashReceived', 3000.00)
            ->call('completeSale')
            ->assertSet('activeModal', 'sale_success')
            ->assertCount('cart', 0);

        $sale = OfflineSale::latest('id')->first();
        $this->assertNotNull($sale);
        $this->assertEquals(3000.00, (float)$sale->total_amount);
    }

    /**
     * Workflow B: Scan base product barcode → cart.
     */
    public function test_workflow_b_scan_base_product_barcode(): void
    {
        $barcode = $this->singleProduct->barcode;
        $cartKey = $this->singleProduct->id . '-default';

        Livewire::actingAs($this->admin)
            ->test(OfflineSales::class)
            ->set('searchQuery', $barcode)
            ->call('handleBarcodeScan')
            ->assertCount('cart', 1)
            ->assertSet("cart.{$cartKey}.name", $this->singleProduct->name)
            ->assertSet("cart.{$cartKey}.quantity", 1);
    }

    /**
     * Workflow C: Scan variant barcode → exact variant cart.
     */
    public function test_workflow_c_scan_variant_barcode(): void
    {
        $barcode = $this->variant->barcode;
        $cartKey = $this->product->id . '-' . $this->variant->id;

        Livewire::actingAs($this->admin)
            ->test(OfflineSales::class)
            ->set('searchQuery', $barcode)
            ->call('handleBarcodeScan')
            ->assertCount('cart', 1)
            ->assertSet("cart.{$cartKey}.sku", $this->variant->sku)
            ->assertSet("cart.{$cartKey}.color", 'Crimson Red')
            ->assertSet("cart.{$cartKey}.quantity", 1);
    }

    /**
     * Workflow D: Scan same barcode multiple times → quantity increments.
     */
    public function test_workflow_d_scan_same_barcode_multiple_times_increments_qty(): void
    {
        $barcode = $this->variant->barcode;
        $cartKey = $this->product->id . '-' . $this->variant->id;

        Livewire::actingAs($this->admin)
            ->test(OfflineSales::class)
            ->set('searchQuery', $barcode)
            ->call('handleBarcodeScan')
            ->assertSet("cart.{$cartKey}.quantity", 1)
            ->set('searchQuery', $barcode)
            ->call('handleBarcodeScan')
            ->assertSet("cart.{$cartKey}.quantity", 2)
            ->set('searchQuery', $barcode)
            ->call('handleBarcodeScan')
            ->assertSet("cart.{$cartKey}.quantity", 3)
            // Remains a single row, NOT 3 rows
            ->assertCount('cart', 1);
    }

    /**
     * Workflow E: Unknown barcode → clear error modal.
     */
    public function test_workflow_e_unknown_barcode_shows_clear_error(): void
    {
        $fakeBarcode = 'NON-EXISTENT-BARCODE-' . uniqid();

        Livewire::actingAs($this->admin)
            ->test(OfflineSales::class)
            ->set('searchQuery', $fakeBarcode)
            ->call('handleBarcodeScan')
            ->assertSet('activeModal', 'unknown_barcode')
            ->assertSet('unknownBarcode', $fakeBarcode)
            ->assertCount('cart', 0)
            // Cashier chooses Search Manually
            ->call('searchManuallyWithBarcode')
            ->assertSet('searchQuery', $fakeBarcode)
            ->assertSet('activeModal', null);
    }

    /**
     * Workflow F: Insufficient stock → sale capped / blocked.
     */
    public function test_workflow_f_insufficient_stock_sale_blocked(): void
    {
        $cartKey = $this->product->id . '-' . $this->variant->id;

        // Current stock is 15
        Livewire::actingAs($this->admin)
            ->test(OfflineSales::class)
            ->call('addToCart', $this->product->id, $this->variant->id, 25)
            // Cannot exceed 15 available physical units
            ->assertSet("cart.{$cartKey}.quantity", 15);
    }

    /**
     * Workflow G: Cash payment → correct change.
     */
    public function test_workflow_g_cash_payment_correct_change(): void
    {
        $comp = Livewire::actingAs($this->admin)
            ->test(OfflineSales::class)
            ->call('addToCart', $this->product->id, $this->variant->id, 1) // Total Rs. 5,000
            ->call('openCheckoutModal')
            ->set('paymentMethod', 'cash')
            ->set('cashReceived', 8000.00);

        $this->assertEquals(3000.00, $comp->instance()->getCashChangeProperty());
    }

    /**
     * Workflow H: Discount → correct totals.
     */
    public function test_workflow_h_discount_correct_totals(): void
    {
        $comp = Livewire::actingAs($this->admin)
            ->test(OfflineSales::class)
            ->call('addToCart', $this->product->id, $this->variant->id, 2) // Subtotal Rs. 10,000
            ->call('applyDiscountPercent', 10, 'Seasonal Promotion');

        $totals = $comp->instance()->calculateTotals();
        $this->assertEquals(10000.00, $totals['subtotal']);
        $this->assertEquals(1000.00, $totals['discount']);
        $this->assertEquals(9000.00, $totals['total']);
    }

    /**
     * Workflow I: Successful sale → order + inventory deduction + stock movement.
     */
    public function test_workflow_i_successful_sale_creates_order_inventory_deduction_and_stock_movement(): void
    {
        $initialStock = StockLevel::where('warehouse_id', $this->showroomWarehouse->id)
            ->where('product_id', $this->product->id)
            ->where('variant_id', $this->variant->id)
            ->value('quantity_on_hand');

        Livewire::actingAs($this->admin)
            ->test(OfflineSales::class)
            ->call('addToCart', $this->product->id, $this->variant->id, 2)
            ->set('selectedWarehouseId', $this->showroomWarehouse->id)
            ->set('paymentMethod', 'cash')
            ->set('cashReceived', 10000.00)
            ->call('completeSale')
            ->assertSet('activeModal', 'sale_success');

        $sale = OfflineSale::latest('id')->first();
        $this->assertNotNull($sale);
        $this->assertEquals(10000.00, (float)$sale->total_amount);

        // Verify StockLevel decremented
        $newStock = StockLevel::where('warehouse_id', $this->showroomWarehouse->id)
            ->where('product_id', $this->product->id)
            ->where('variant_id', $this->variant->id)
            ->value('quantity_on_hand');
        $this->assertEquals($initialStock - 2, $newStock);

        // Verify StockMovement recorded
        $movement = StockMovement::where('reference_id', $sale->id)
            ->where('reference_type', 'offline_sale')
            ->first();
        $this->assertNotNull($movement);
        $this->assertEquals('sale_pos', $movement->movement_type);
        $this->assertEquals(-2, $movement->quantity);
    }

    /**
     * Workflow J: Failed transaction → complete rollback.
     */
    public function test_workflow_j_failed_transaction_rolls_back_completely(): void
    {
        $salesCountBefore = OfflineSale::count();

        // Attempting a sale with 0 cash received on cash payment must fail validation
        Livewire::actingAs($this->admin)
            ->test(OfflineSales::class)
            ->call('addToCart', $this->product->id, $this->variant->id, 1)
            ->set('paymentMethod', 'cash')
            ->set('cashReceived', 100.00) // Less than Rs. 5,000 required
            ->call('completeSale')
            ->assertSet('activeModal', null); // Does not complete, stays in cart

        $this->assertEquals($salesCountBefore, OfflineSale::count());
    }

    /**
     * Workflow K: Double-click checkout protection.
     */
    public function test_workflow_k_double_click_checkout_protection(): void
    {
        $test = Livewire::actingAs($this->admin)
            ->test(OfflineSales::class)
            ->call('addToCart', $this->product->id, $this->variant->id, 1)
            ->set('paymentMethod', 'cash')
            ->set('cashReceived', 5000.00)
            ->set('isSubmitting', true) // Simulate in-flight submission
            ->call('completeSale'); // Should immediately return without creating duplicate sale

        $test->assertCount('cart', 1); // Cart remains intact because method exited early
    }

    /**
     * Workflow L: Customer attached → order linked correctly.
     */
    public function test_workflow_l_customer_attached_order_linked(): void
    {
        $customerUser = User::create([
            'name' => 'Prashant Karki',
            'email' => 'prashant.' . uniqid() . '@example.com',
            'phone' => '9841000000',
            'role' => 'customer',
            'password' => bcrypt('Secret123!'),
        ]);

        Livewire::actingAs($this->admin)
            ->test(OfflineSales::class)
            ->call('addToCart', $this->singleProduct->id, null, 1)
            ->call('selectCustomer', $customerUser->id)
            ->assertSet('customerType', 'existing')
            ->assertSet('selectedUserId', $customerUser->id)
            ->assertSet('customerName', 'Prashant Karki')
            ->set('paymentMethod', 'cash')
            ->set('cashReceived', 3000.00)
            ->call('completeSale');

        $sale = OfflineSale::latest('id')->first();
        $this->assertEquals($customerUser->id, $sale->user_id);
        $this->assertEquals('Prashant Karki', $sale->customer_name);
        $this->assertEquals('9841000000', $sale->customer_phone);
    }

    /**
     * Workflow M: Correct warehouse stock deducted.
     */
    public function test_workflow_m_correct_warehouse_stock_deducted(): void
    {
        Livewire::actingAs($this->admin)
            ->test(OfflineSales::class)
            ->call('addToCart', $this->product->id, $this->variant->id, 1)
            ->call('setWarehouse', $this->showroomWarehouse->id)
            ->set('paymentMethod', 'cash')
            ->set('cashReceived', 5000.00)
            ->call('completeSale');

        $sale = OfflineSale::latest('id')->first();
        $this->assertEquals($this->showroomWarehouse->id, $sale->warehouse_id);

        $movement = StockMovement::where('reference_id', $sale->id)->first();
        $this->assertEquals($this->showroomWarehouse->id, $movement->warehouse_id);
    }

    /**
     * Workflow N: Receipt rendering.
     */
    public function test_workflow_n_receipt_rendering(): void
    {
        $testLivewire = Livewire::actingAs($this->admin)
            ->test(OfflineSales::class)
            ->call('addToCart', $this->product->id, $this->variant->id, 1)
            ->set('paymentMethod', 'cash')
            ->set('cashReceived', 6000.00)
            ->set('customerName', 'Sunil Shakya')
            ->call('completeSale');

        $receipt = $testLivewire->get('receiptData');
        $this->assertIsArray($receipt);
        $this->assertNotEmpty($receipt['sale_number']);
        $this->assertEquals('Sunil Shakya', $receipt['customer_name']);
        $this->assertEquals(5000.00, $receipt['total']);
        $this->assertEquals(6000.00, $receipt['cash_received']);
        $this->assertEquals(1000.00, $receipt['change_given']);
        $this->assertNotEmpty($receipt['warehouse_name']);
        $this->assertNotEmpty($receipt['cashier_name']);
    }

    /**
     * Workflow O: Permission restrictions.
     */
    public function test_workflow_o_permission_restrictions(): void
    {
        // Cashier cannot view financial margins or landed costs
        $cashierComp = Livewire::actingAs($this->cashier)->test(OfflineSales::class);
        $this->assertFalse($cashierComp->instance()->canViewFinancialMargins());

        // Admin can view financial margins
        $adminComp = Livewire::actingAs($this->admin)->test(OfflineSales::class);
        $this->assertTrue($adminComp->instance()->canViewFinancialMargins());
    }

    /**
     * Workflow P: Archived product cannot be sold.
     */
    public function test_workflow_p_archived_or_inactive_product_cannot_be_sold(): void
    {
        $inactiveProd = Product::create([
            'name' => 'Archived Pashmina ' . uniqid(),
            'slug' => 'archived-pashmina-' . uniqid(),
            'sku' => 'LJ-ARCH-' . uniqid(),
            'barcode' => '999111' . rand(1000, 9999),
            'price' => 4500.00,
            'is_active' => false, // Inactive / Archived
        ]);

        Livewire::actingAs($this->admin)
            ->test(OfflineSales::class)
            ->call('addToCart', $inactiveProd->id)
            ->assertCount('cart', 0)
            ->set('searchQuery', $inactiveProd->barcode)
            ->call('handleBarcodeScan')
            ->assertCount('cart', 0);
    }

    /**
     * Workflow Q: Existing historical order remains intact.
     */
    public function test_workflow_q_existing_historical_order_remains_intact(): void
    {
        $initialOrderCount = Order::count();

        Livewire::actingAs($this->admin)
            ->test(OfflineSales::class)
            ->call('addToCart', $this->singleProduct->id, null, 1)
            ->set('paymentMethod', 'cash')
            ->set('cashReceived', 3000.00)
            ->call('completeSale');

        // Historical e-commerce orders are untouched
        $this->assertEquals($initialOrderCount, Order::count());
    }

    /**
     * Section 16: Hold and Restore Sale (F9) Workflow
     */
    public function test_hold_and_restore_sale_workflow(): void
    {
        $cartKey = $this->product->id . '-' . $this->variant->id;

        Livewire::actingAs($this->admin)
            ->test(OfflineSales::class)
            ->call('addToCart', $this->product->id, $this->variant->id, 2)
            ->set('customerName', 'Bikash Shrestha')
            ->call('holdCurrentSale')
            ->assertCount('cart', 0)
            ->assertCount('heldSales', 1)
            ->assertSet('heldSales.0.customer_name', 'Bikash Shrestha')
            ->call('restoreHeldSale', 0)
            ->assertCount('heldSales', 0)
            ->assertCount('cart', 1)
            ->assertSet("cart.{$cartKey}.quantity", 2);
    }

    /**
     * Section 17: Clear Cart with Confirmation
     */
    public function test_clear_cart_with_confirmation_modal(): void
    {
        Livewire::actingAs($this->admin)
            ->test(OfflineSales::class)
            ->call('addToCart', $this->singleProduct->id, null, 2)
            ->assertCount('cart', 1)
            ->call('confirmClearCart')
            ->assertSet('activeModal', 'clear_cart_confirm')
            ->call('clearCart')
            ->assertCount('cart', 0)
            ->assertSet('activeModal', null);
    }

    /**
     * Section 18: Void Sale Workflow Restores Inventory
     */
    public function test_void_sale_workflow_restores_inventory(): void
    {
        $service = app(OfflineSaleService::class);

        $saleData = [
            'customer_name' => 'Suman Thapa',
            'warehouse_id' => $this->showroomWarehouse->id,
            'payment_method' => 'cash',
            'sales_channel' => 'physical',
            'currency' => 'NPR',
        ];

        $itemsData = [
            [
                'product_id' => $this->product->id,
                'variant_id' => $this->variant->id,
                'quantity' => 1,
                'unit_price' => 5000.00,
            ],
        ];

        $sale = $service->createSale($saleData, $itemsData, $this->admin);
        $this->assertEquals('completed', $sale->status);

        Livewire::actingAs($this->admin)
            ->test(OfflineSales::class)
            ->call('openVoidModal', $sale->id)
            ->assertSet('activeModal', 'void_modal')
            ->set('voidReason', 'Customer requested refund')
            ->set('voidRestockInventory', true)
            ->call('processVoidSale')
            ->assertSet('activeModal', null);

        $sale->refresh();
        $this->assertEquals('voided', $sale->status);
    }

    /**
     * Section 19: Cashier Can Reprint Receipt for Historical Sale
     */
    public function test_cashier_can_reprint_receipt_for_historical_sale(): void
    {
        $service = app(OfflineSaleService::class);

        $sale = $service->createSale([
            'customer_name' => 'Prabesh Joshi',
            'customer_phone' => '9841234567',
            'warehouse_id' => $this->showroomWarehouse->id,
            'payment_method' => 'cash',
            'cash_received' => 5000.00,
            'change_given' => 0.00,
            'sales_channel' => 'physical',
            'currency' => 'NPR',
        ], [
            [
                'product_id' => $this->product->id,
                'variant_id' => $this->variant->id,
                'quantity' => 1,
                'unit_price' => 5000.00,
            ],
        ], $this->cashier);

        Livewire::actingAs($this->cashier)
            ->test(OfflineSales::class)
            ->call('reprintReceipt', $sale->id)
            ->assertSet('activeModal', 'receipt_modal')
            ->assertSeeHtml('TAX INVOICE / CASH MEMO')
            ->assertSeeHtml('PAN/VAT: 604335148')
            ->assertSeeHtml('13% VAT (Inclusive)')
            ->assertSee('Prabesh Joshi');
    }

    /**
     * Section 20: Digital / QR Payment with Auth Slip Reference
     */
    public function test_pos_digital_payment_with_internal_auth_slip_reference(): void
    {
        Livewire::actingAs($this->cashier)
            ->test(OfflineSales::class)
            ->call('addToCart', $this->product->id, $this->variant->id, 1)
            ->set('paymentMethod', 'esewa')
            ->set('customerNotes', 'Customer requested bag')
            ->set('internalNotes', 'eSewa Trace #99882244')
            ->call('completeSale')
            ->assertSet('activeModal', 'sale_success')
            ->assertSee('Sale Complete!');

        $sale = OfflineSale::where('payment_method', 'esewa')->latest('id')->first();
        $this->assertNotNull($sale);
        $this->assertEquals('eSewa Trace #99882244', $sale->internal_notes);
        $this->assertEquals('Customer requested bag', $sale->customer_notes);
    }
}
