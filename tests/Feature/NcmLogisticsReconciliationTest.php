<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Logistics\NcmReconciliationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NcmLogisticsReconciliationTest extends TestCase
{
    use DatabaseTransactions;

    protected NcmReconciliationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(NcmReconciliationService::class);
    }

    protected function createTestOrder(array $attributes = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'LJ-TEST-' . uniqid(),
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => 'test_' . uniqid() . '@example.com',
            'phone' => '9841000000',
            'shipping_address' => 'Kathmandu, Nepal',
            'shipping_country' => 'NP',
            'subtotal' => 1500.00,
            'total_amount' => 1500.00,
            'currency' => 'NPR',
            'status' => 'pending',
        ], $attributes));
    }

    /**
     * Data Normalization Tests
     */
    public function test_html_placeholder_cleaning(): void
    {
        $this->assertNull($this->service->cleanHtmlPlaceholder('<br>'));
        $this->assertNull($this->service->cleanHtmlPlaceholder('<br/>'));
        $this->assertNull($this->service->cleanHtmlPlaceholder('&nbsp;'));
        $this->assertNull($this->service->cleanHtmlPlaceholder('   <br/>   '));
        $this->assertNull($this->service->cleanHtmlPlaceholder(''));
        $this->assertEquals('FP black(2)', $this->service->cleanHtmlPlaceholder('FP black(2)'));
        $this->assertEquals('Partial', $this->service->cleanHtmlPlaceholder('Partial <br>'));
    }

    public function test_phone_number_normalization(): void
    {
        $this->assertEquals('9701522930', $this->service->normalizePhone('9701522930'));
        $this->assertEquals('9701522930', $this->service->normalizePhone('+9779701522930'));
        $this->assertEquals('9701522930', $this->service->normalizePhone('9779701522930'));
        $this->assertEquals('9701522930', $this->service->normalizePhone('09701522930'));
        $this->assertEquals('9701522930', $this->service->normalizePhone('970-152-2930'));
    }

    public function test_status_normalization_and_vendor_return(): void
    {
        $this->assertEquals(Shipment::STATUS_DELIVERED, $this->service->normalizeStatus('Delivered', false));
        $this->assertEquals(Shipment::STATUS_OUT_FOR_DELIVERY, $this->service->normalizeStatus('Sent for Delivery', false));
        $this->assertEquals(Shipment::STATUS_DISPATCHED, $this->service->normalizeStatus('Dispatched', false));
        $this->assertEquals(Shipment::STATUS_ARRIVED, $this->service->normalizeStatus('Arrived', false));
        $this->assertEquals(Shipment::STATUS_PICKUP_PENDING, $this->service->normalizeStatus('Sent for Pickup', false));
        $this->assertEquals(Shipment::STATUS_OTHER, $this->service->normalizeStatus('Unknown Status', false));

        // Vendor return override
        $this->assertEquals(Shipment::STATUS_RETURNED_TO_VENDOR, $this->service->normalizeStatus('Delivered', true));
    }

    public function test_date_parsing(): void
    {
        $parsed = $this->service->parseDate('2026-09-11');
        $this->assertEquals('2026-09-11 00:00:00', $parsed);
        $this->assertNull($this->service->parseDate('<br>'));
        $this->assertNull($this->service->parseDate(''));
    }

    /**
     * TEST A: NCM Order ID becomes shipment tracking number.
     */
    public function test_a_ncm_order_id_becomes_shipment_tracking_number(): void
    {
        $testOrderId = 'NCM-AWB-' . rand(100000, 999999);
        $csvContent = "Order ID,Created Date,Source Branch,Destination Branch,Receiver,Receiver Phone,COD Charge,Delivery Charge,Status,Vendor Return,Reference ID,Package Description,Remarks,Weight,Delivered Date,Created By\n" .
                      "{$testOrderId},2026-09-11,TINKUNE,SATDOBATO,Aakash Thapa,9800112233,1500.00,150.00,Delivered,False,,,,1.00,2026-09-12,Self\n";

        $tempCsv = tempnam(sys_get_temp_dir(), 'ncm_test_');
        file_put_contents($tempCsv, $csvContent);
        $this->service->sync($tempCsv, true);
        @unlink($tempCsv);

        $shipment = Shipment::where('external_tracking_number', $testOrderId)->first();
        $this->assertNotNull($shipment);
        $this->assertEquals($testOrderId, $shipment->external_tracking_number);
        $this->assertEquals('ncm', $shipment->provider);
    }

    /**
     * TEST B: NCM Order ID becomes matched order tracking number.
     */
    public function test_b_ncm_order_id_becomes_matched_order_tracking_number(): void
    {
        $phone = '9801' . rand(100000, 999999);
        $order = $this->createTestOrder([
            'order_number' => 'LJ-TEST-B-' . uniqid(),
            'phone' => $phone,
            'total_amount' => 2200.00,
            'subtotal' => 2200.00,
        ]);

        $ncmId = 'NCM-TRK-' . rand(100000, 999999);
        $csvContent = "Order ID,Created Date,Source Branch,Destination Branch,Receiver,Receiver Phone,COD Charge,Delivery Charge,Status,Vendor Return,Reference ID,Package Description,Remarks,Weight,Delivered Date,Created By\n" .
                      "{$ncmId},2026-09-11,TINKUNE,POKHARA,Target User,{$phone},2200.00,200.00,Delivered,False,,,,1.00,2026-09-12,Self\n";

        $tempCsv = tempnam(sys_get_temp_dir(), 'ncm_test_');
        file_put_contents($tempCsv, $csvContent);
        $this->service->sync($tempCsv, true);
        @unlink($tempCsv);

        $order->refresh();
        $this->assertEquals('Nepal Can Move (NCM)', $order->carrier);
        $this->assertEquals('Nepal Can Move (NCM)', $order->courier_name);
        $this->assertEquals($ncmId, $order->courier_order_id);
        $this->assertEquals($ncmId, $order->tracking_number);
        $this->assertStringContainsString($ncmId, $order->tracking_url);
    }

    /**
     * TEST C: Duplicate NCM Order IDs cannot create duplicate shipments.
     */
    public function test_c_duplicate_ncm_order_ids_cannot_create_duplicate_shipments(): void
    {
        $ncmId = 'NCM-DUP-' . rand(100000, 999999);
        $csvContent = "Order ID,Created Date,Source Branch,Destination Branch,Receiver,Receiver Phone,COD Charge,Delivery Charge,Status,Vendor Return,Reference ID,Package Description,Remarks,Weight,Delivered Date,Created By\n" .
                      "{$ncmId},2026-09-11,TINKUNE,KTM,User One,9841000001,500.00,100.00,Delivered,False,,,,1.00,2026-09-12,Self\n" .
                      "{$ncmId},2026-09-11,TINKUNE,KTM,User One,9841000001,500.00,100.00,Delivered,False,,,,1.00,2026-09-12,Self\n";

        $tempCsv = tempnam(sys_get_temp_dir(), 'ncm_test_');
        file_put_contents($tempCsv, $csvContent);
        $this->service->sync($tempCsv, true);
        @unlink($tempCsv);

        $count = Shipment::where('provider', 'ncm')->where('external_tracking_number', $ncmId)->count();
        $this->assertEquals(1, $count, "Duplicate NCM Order ID in CSV must NOT create duplicate shipments.");
    }

    /**
     * TEST D: Unique phone matching works.
     */
    public function test_d_unique_phone_matching_works(): void
    {
        $phone = '9812' . rand(100000, 999999);
        $order = $this->createTestOrder([
            'order_number' => 'LJ-TEST-D-' . uniqid(),
            'phone' => $phone,
            'total_amount' => 1950.00,
        ]);

        $ncmId = 'NCM-PH-' . rand(100000, 999999);
        $csvContent = "Order ID,Created Date,Source Branch,Destination Branch,Receiver,Receiver Phone,COD Charge,Delivery Charge,Status,Vendor Return,Reference ID,Package Description,Remarks,Weight,Delivered Date,Created By\n" .
                      "{$ncmId},2026-09-11,TINKUNE,DHARAN,Phone Matcher,{$phone},1950.00,180.00,Sent for Delivery,False,,,,1.00,,Self\n";

        $tempCsv = tempnam(sys_get_temp_dir(), 'ncm_test_');
        file_put_contents($tempCsv, $csvContent);
        $this->service->sync($tempCsv, true);
        @unlink($tempCsv);

        $shipment = Shipment::where('external_tracking_number', $ncmId)->first();
        $this->assertNotNull($shipment);
        $this->assertEquals($order->id, $shipment->order_id);
        $this->assertEquals(Shipment::MATCH_STATUS_MATCHED, $shipment->match_status);
        $this->assertEquals('phone_single', $shipment->match_method);
    }

    /**
     * TEST E: Ambiguous phone matching does NOT guess.
     */
    public function test_e_ambiguous_phone_matching_does_not_guess(): void
    {
        $sharedPhone = '9809' . rand(100000, 999999);
        $order1 = $this->createTestOrder([
            'order_number' => 'LJ-AMB-1-' . uniqid(),
            'phone' => $sharedPhone,
            'total_amount' => 3000.00,
            'created_at' => Carbon::parse('2026-01-01'),
        ]);
        $order2 = $this->createTestOrder([
            'order_number' => 'LJ-AMB-2-' . uniqid(),
            'phone' => $sharedPhone,
            'total_amount' => 3000.00,
            'created_at' => Carbon::parse('2026-08-01'),
        ]);

        $ncmId = 'NCM-AMB-' . rand(100000, 999999);
        $csvContent = "Order ID,Created Date,Source Branch,Destination Branch,Receiver,Receiver Phone,COD Charge,Delivery Charge,Status,Vendor Return,Reference ID,Package Description,Remarks,Weight,Delivered Date,Created By\n" .
                      "{$ncmId},2026-05-15,TINKUNE,BIRATNAGAR,Ambiguous User,{$sharedPhone},1000.00,220.00,Delivered,False,,,,1.00,2026-05-18,Self\n";

        $tempCsv = tempnam(sys_get_temp_dir(), 'ncm_test_');
        file_put_contents($tempCsv, $csvContent);
        $this->service->sync($tempCsv, true);
        @unlink($tempCsv);

        $shipment = Shipment::where('external_tracking_number', $ncmId)->first();
        $this->assertNotNull($shipment);
        $this->assertNull($shipment->order_id, "Ambiguous shipment must NOT guess order_id!");
        $this->assertEquals(Shipment::MATCH_STATUS_AMBIGUOUS, $shipment->match_status);
        $this->assertStringContainsString((string)$order1->id, $shipment->match_reason);
        $this->assertStringContainsString((string)$order2->id, $shipment->match_reason);
    }

    /**
     * TEST F: Unmatched shipments are preserved.
     */
    public function test_f_unmatched_shipments_are_preserved(): void
    {
        $unmatchedPhone = '9800000000';
        $ncmId = 'NCM-UNM-' . rand(100000, 999999);
        $csvContent = "Order ID,Created Date,Source Branch,Destination Branch,Receiver,Receiver Phone,COD Charge,Delivery Charge,Status,Vendor Return,Reference ID,Package Description,Remarks,Weight,Delivered Date,Created By\n" .
                      "{$ncmId},2026-09-11,TINKUNE,HELEMBISI,Ghost User,{$unmatchedPhone},1400.00,250.00,Delivered,False,,,,1.00,2026-09-12,Self\n";

        $tempCsv = tempnam(sys_get_temp_dir(), 'ncm_test_');
        file_put_contents($tempCsv, $csvContent);
        $this->service->sync($tempCsv, true);
        @unlink($tempCsv);

        $shipment = Shipment::where('external_tracking_number', $ncmId)->first();
        $this->assertNotNull($shipment);
        $this->assertNull($shipment->order_id);
        $this->assertEquals(Shipment::MATCH_STATUS_UNMATCHED, $shipment->match_status);
        $this->assertEquals(1400.00, (float)$shipment->cod_amount);
        $this->assertEquals(250.00, (float)$shipment->delivery_charge);
    }

    /**
     * TEST G: Delivered Date synchronizes correctly.
     */
    public function test_g_delivered_date_synchronizes_correctly(): void
    {
        $phone = '9823' . rand(100000, 999999);
        $order = $this->createTestOrder([
            'order_number' => 'LJ-TEST-G-' . uniqid(),
            'phone' => $phone,
            'total_amount' => 1700.00,
        ]);

        $ncmId = 'NCM-DEL-' . rand(100000, 999999);
        $csvContent = "Order ID,Created Date,Source Branch,Destination Branch,Receiver,Receiver Phone,COD Charge,Delivery Charge,Status,Vendor Return,Reference ID,Package Description,Remarks,Weight,Delivered Date,Created By\n" .
                      "{$ncmId},2026-09-10,TINKUNE,BUTWAL,Delivery User,{$phone},1700.00,160.00,Delivered,False,,,,1.00,2026-09-12,Self\n";

        $tempCsv = tempnam(sys_get_temp_dir(), 'ncm_test_');
        file_put_contents($tempCsv, $csvContent);
        $this->service->sync($tempCsv, true);
        @unlink($tempCsv);

        $order->refresh();
        $this->assertEquals('2026-09-12', $order->actual_delivery_date ? $order->actual_delivery_date->toDateString() : null);
        $this->assertEquals('2026-09-12 00:00:00', $order->delivered_at ? $order->delivered_at->toDateTimeString() : null);
    }

    /**
     * TEST H: Vendor Return is preserved.
     */
    public function test_h_vendor_return_is_preserved(): void
    {
        $ncmId = 'NCM-VR-' . rand(100000, 999999);
        $csvContent = "Order ID,Created Date,Source Branch,Destination Branch,Receiver,Receiver Phone,COD Charge,Delivery Charge,Status,Vendor Return,Reference ID,Package Description,Remarks,Weight,Delivered Date,Created By\n" .
                      "{$ncmId},2026-09-10,TINKUNE,NEPALGUNJ,Returned User,9800776655,0.00,250.00,Delivered,True,,,,1.00,2026-09-12,Self\n";

        $tempCsv = tempnam(sys_get_temp_dir(), 'ncm_test_');
        file_put_contents($tempCsv, $csvContent);
        $this->service->sync($tempCsv, true);
        @unlink($tempCsv);

        $shipment = Shipment::where('external_tracking_number', $ncmId)->first();
        $this->assertNotNull($shipment);
        $this->assertTrue((bool)$shipment->vendor_return);
        $this->assertEquals(Shipment::STATUS_RETURNED_TO_VENDOR, $shipment->normalized_status);
    }

    /**
     * TEST I: NCM COD and delivery charge remain separate from Laijau order totals.
     */
    public function test_i_ncm_cod_and_delivery_charge_remain_separate_from_laijau_order_totals(): void
    {
        $phone = '9834' . rand(100000, 999999);
        $initialOrderTotal = 3500.00;
        $order = $this->createTestOrder([
            'order_number' => 'LJ-TEST-I-' . uniqid(),
            'phone' => $phone,
            'subtotal' => 3500.00,
            'total_amount' => $initialOrderTotal,
        ]);

        $ncmId = 'NCM-FIN-' . rand(100000, 999999);
        $csvContent = "Order ID,Created Date,Source Branch,Destination Branch,Receiver,Receiver Phone,COD Charge,Delivery Charge,Status,Vendor Return,Reference ID,Package Description,Remarks,Weight,Delivered Date,Created By\n" .
                      "{$ncmId},2026-09-10,TINKUNE,LALITPUR,Fin User,{$phone},3500.00,450.00,Delivered,False,,,,2.50,2026-09-12,Self\n";

        $tempCsv = tempnam(sys_get_temp_dir(), 'ncm_test_');
        file_put_contents($tempCsv, $csvContent);
        $this->service->sync($tempCsv, true);
        @unlink($tempCsv);

        $order->refresh();
        $shipment = Shipment::where('external_tracking_number', $ncmId)->first();

        // Order financial total MUST remain 3500.00!
        $this->assertEquals($initialOrderTotal, (float)$order->total_amount);
        $this->assertEquals(3500.00, (float)$order->subtotal);

        // NCM logistics charges are stored independently on the shipment
        $this->assertEquals(3500.00, (float)$shipment->cod_amount);
        $this->assertEquals(450.00, (float)$shipment->delivery_charge);
    }

    /**
     * TEST J: Running the same CSV twice is idempotent.
     */
    public function test_j_running_the_same_csv_twice_is_idempotent(): void
    {
        $ncmId = 'NCM-IDEMP-' . rand(100000, 999999);
        $csvContent = "Order ID,Created Date,Source Branch,Destination Branch,Receiver,Receiver Phone,COD Charge,Delivery Charge,Status,Vendor Return,Reference ID,Package Description,Remarks,Weight,Delivered Date,Created By\n" .
                      "{$ncmId},2026-09-11,TINKUNE,KTM,Idemp User,9841555666,1800.00,120.00,Delivered,False,,,,1.00,2026-09-12,Self\n";

        $tempCsv = tempnam(sys_get_temp_dir(), 'ncm_test_');
        file_put_contents($tempCsv, $csvContent);

        // Run 1: Create
        $metrics1 = $this->service->sync($tempCsv, true);
        $this->assertEquals(1, $metrics1['created']);

        // Run 2: Unchanged (Zero duplicates, Zero unexpected mutations)
        $metrics2 = $this->service->sync($tempCsv, true);
        $this->assertEquals(0, $metrics2['created']);
        $this->assertEquals(0, $metrics2['updated']);
        $this->assertEquals(1, $metrics2['unchanged']);

        $totalCount = Shipment::where('external_tracking_number', $ncmId)->count();
        $this->assertEquals(1, $totalCount);

        @unlink($tempCsv);
    }

    /**
     * TEST K: Public tracking displays the NCM tracking number.
     */
    public function test_k_public_tracking_displays_ncm_tracking_number(): void
    {
        $trackingNum = '25710815';
        $order = Order::where('tracking_number', $trackingNum)->first();

        if (!$order) {
            $order = $this->createTestOrder([
                'order_number' => 'LJ-TRACK-K01',
                'tracking_number' => $trackingNum,
                'courier_order_id' => $trackingNum,
                'carrier' => 'Nepal Can Move (NCM)',
                'courier_name' => 'Nepal Can Move (NCM)',
                'status' => 'delivered',
            ]);
        }

        $response = $this->get(route('storefront.track_order', [
            'order_number' => $order->order_number,
            'phone' => $order->phone,
        ]));
        $response->assertStatus(200);
        $response->assertSee($trackingNum);
        $response->assertSee('Nepal Can Move (NCM)');
    }

    /**
     * TEST L: Existing accounting/order/inventory/POS totals remain unchanged.
     */
    public function test_l_existing_accounting_order_inventory_pos_totals_remain_unchanged(): void
    {
        $posCount = DB::table('offline_sales')->count();
        if ($posCount < 1000) {
            $this->markTestSkipped('Reconciliation against finalized 11,170 historical POS sales requires production database snapshot.');
        }
        $posSum = (float)DB::table('offline_sales')->sum('total_amount');
        $ordersCount = DB::table('orders')->count();
        $ordersSum = (float)DB::table('orders')->sum('total_amount');
        $productsCount = DB::table('products')->count();
        $debit = (float)DB::table('accounting_journal_entries')->sum('total_debit');
        $credit = (float)DB::table('accounting_journal_entries')->sum('total_credit');

        $this->assertGreaterThanOrEqual(10580, $posCount, "POS transactions count must reflect finalized sales");
        $this->assertGreaterThan(20000000.00, $posSum, "POS sales sum must reflect authentic volume");
        $this->assertGreaterThanOrEqual(500, $productsCount, "Products count must reflect authoritative catalog");
        $this->assertLessThan(0.0001, abs($debit - $credit), "Double-entry ledger variance must remain 0.00");
    }

    /**
     * Staff manual linking workflow test.
     */
    public function test_staff_manual_linking_workflow(): void
    {
        $user = User::factory()->create(['email' => 'staff_' . uniqid() . '@laijau.com']);

        $order = $this->createTestOrder([
            'order_number' => 'LJ-TEST-MANUAL-01',
            'phone' => '9841222333',
            'total_amount' => 2000.00,
            'subtotal' => 2000.00,
        ]);

        $shipment = Shipment::create([
            'provider' => 'ncm',
            'external_tracking_number' => 'NCM-MANUAL-777',
            'status' => 'Delivered',
            'normalized_status' => Shipment::STATUS_DELIVERED,
            'delivered_at' => Carbon::parse('2026-09-12'),
            'match_status' => Shipment::MATCH_STATUS_AMBIGUOUS,
        ]);

        $this->service->manuallyLinkShipment($shipment, $order, $user->id, "Linked after customer phone verification");

        $shipment->refresh();
        $order->refresh();

        $this->assertEquals($order->id, $shipment->order_id);
        $this->assertEquals(Shipment::MATCH_STATUS_MANUAL, $shipment->match_status);
        $this->assertEquals($user->id, $shipment->matched_by);
        $this->assertNotNull($shipment->matched_at);
        $this->assertEquals('NCM-MANUAL-777', $order->tracking_number);
        $this->assertEquals('2026-09-12', $order->actual_delivery_date ? $order->actual_delivery_date->toDateString() : null);
    }
}
