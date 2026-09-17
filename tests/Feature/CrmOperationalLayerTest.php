<?php

namespace Tests\Feature;

use App\Filament\Pages\CrmKanban;
use App\Models\ContactMessage;
use App\Models\CrmActivity;
use App\Models\CrmLead;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Crm\CrmService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CrmOperationalLayerTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $storeManager;
    protected User $viewer;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::firstOrCreate(
            ['email' => 'admin@laijau.com'],
            [
                'name' => 'Laijau Administrator',
                'password' => bcrypt('Laijau2026!'),
                'role' => 'admin',
            ]
        );

        $this->storeManager = User::firstOrCreate(
            ['email' => 'manager_test@laijau.com'],
            [
                'name' => 'Store Manager',
                'password' => bcrypt('Laijau2026!'),
                'role' => 'store_manager',
            ]
        );

        $this->viewer = User::firstOrCreate(
            ['email' => 'viewer_test@laijau.com'],
            [
                'name' => 'Store Viewer',
                'password' => bcrypt('Laijau2026!'),
                'role' => 'viewer',
            ]
        );

        $this->product = Product::create([
            'name' => 'Classic Leather Oxford Shoes ' . Str::random(4),
            'slug' => 'classic-oxford-' . Str::random(5),
            'sku' => 'SKU-SHO-' . Str::random(4),
            'price' => 18500,
            'is_published' => true,
            'is_active' => true,
        ]);
    }

    /**
     * Complete Customer Journey:
     * Inquiry → Lead → Follow-up → Customer → Order → Fulfillment → Closed/Won
     */
    public function test_complete_crm_customer_journey(): void
    {
        $crmService = app(CrmService::class);
        $phone = '9841' . rand(100000, 999999);
        $email = 'patron_' . Str::random(5) . '@example.com';

        // 1. Patron submits web inquiry
        $inquiryResponse = $this->postJson('/api/contact', [
            'name' => 'Anjali Shrestha',
            'email' => $email,
            'phone' => $phone,
            'subject' => 'Winter Footwear Sizing Inquiry',
            'inquiry_type' => 'sizing',
            'message' => 'I would like to inquire about leather boots with custom sizing.',
        ]);
        $inquiryResponse->assertStatus(201);
        $inquiryId = $inquiryResponse->json('data.id');

        $inquiry = ContactMessage::findOrFail($inquiryId);
        $this->assertEquals('sizing', $inquiry->inquiry_type);
        $this->assertNotNull($inquiry->customer_id);

        // 2. Convert inquiry into CRM sales lead
        $lead = $crmService->createLeadFromInquiry($inquiry, $this->storeManager->id);
        $this->assertInstanceOf(CrmLead::class, $lead);
        $this->assertEquals('new', $lead->stage);
        $this->assertEquals($this->storeManager->id, $lead->assigned_staff_id);
        $this->assertEquals($inquiry->customer_id, $lead->customer_id);

        // Verify conversion activity log was recorded
        $this->assertTrue(
            $lead->activities()->where('type', CrmActivity::TYPE_CONVERSION)->exists()
        );

        // 3. Staff advances stage and records follow-up
        $crmService->transitionStage($lead, CrmLead::STAGE_CONTACTED, 'Client contacted via WhatsApp. Shared swatch gallery.', $this->storeManager);
        $this->assertEquals('contacted', $lead->fresh()->stage);

        $crmService->scheduleFollowUp($lead, now()->addDays(2), 'Call for size measurements', $this->storeManager);
        $this->assertNotNull($lead->fresh()->follow_up_date);
        $this->assertTrue(
            $lead->activities()->where('type', CrmActivity::TYPE_FOLLOW_UP)->exists()
        );

        // Staff moves to Negotiation and links inquired garment
        $lead->product_id = $this->product->id;
        $lead->estimated_value = 18500.00;
        $lead->save();

        $crmService->transitionStage($lead, CrmLead::STAGE_NEGOTIATION, 'Agreed on shoes and jacket package for Rs. 18,500', $this->storeManager);
        $this->assertEquals('negotiation', $lead->fresh()->stage);

        // 4. Convert Lead into confirmed storefront Order
        $order = $crmService->convertLeadToOrder($lead, [
            'total_amount' => 18500.00,
            'payment_method' => 'cod',
            'shipping_address' => 'Baneshwor, Kathmandu',
            'channel' => 'concierge',
        ], $this->storeManager);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals('concierge', $order->channel);
        $this->assertEquals(18500.00, (float)$order->total_amount);
        $this->assertEquals($lead->customer_id, $order->user_id);
        $this->assertCount(1, $order->items);
        $this->assertEquals($this->product->id, $order->items->first()->product_id);

        // Verify lead is marked Won and closed
        $freshLead = $lead->fresh();
        $this->assertEquals(CrmLead::STAGE_WON, $freshLead->stage);
        $this->assertEquals($order->id, $freshLead->order_id);
        $this->assertNotNull($freshLead->closed_at);

        // Verify order-linked activity log exists
        $this->assertTrue(
            $freshLead->activities()->where('type', CrmActivity::TYPE_ORDER_LINKED)->exists()
        );

        // 5. Order is Queued for Fulfillment Hub processing
        $this->assertEquals(Order::STATUS_PROCESSING, $order->status);
        $this->assertNotEmpty($order->order_number);
        $this->assertTrue(str_starts_with($order->order_number, 'LJ-') || str_starts_with($order->order_number, 'NA-') || str_starts_with($order->order_number, 'ORD-'));
    }

    /**
     * Duplicate Customer Prevention:
     * Converting multiple inquiries from the same phone/email MUST resolve to a single unified User customer.
     */
    public function test_duplicate_customer_prevention(): void
    {
        $crmService = app(CrmService::class);
        $sharedPhone = '98510' . rand(10000, 99999);
        $sharedEmail = 'patron_unified_' . Str::random(4) . '@example.com';

        // Inquiry 1
        $inquiry1 = ContactMessage::create([
            'name' => 'Suman Thapa',
            'email' => $sharedEmail,
            'phone' => $sharedPhone,
            'subject' => 'First Inquiry',
            'message' => 'Looking for leather boots',
            'status' => 'new',
        ]);
        $lead1 = $crmService->createLeadFromInquiry($inquiry1);

        // Inquiry 2 from same patron with slight name variation
        $inquiry2 = ContactMessage::create([
            'name' => 'Suman K. Thapa',
            'email' => $sharedEmail,
            'phone' => $sharedPhone,
            'subject' => 'Second Inquiry',
            'message' => 'Also inquiring about matching dupatta',
            'status' => 'new',
        ]);
        $lead2 = $crmService->createLeadFromInquiry($inquiry2);

        // Both leads must reference the same customer ID
        $this->assertNotNull($lead1->customer_id);
        $this->assertNotNull($lead2->customer_id);
        $this->assertEquals($lead1->customer_id, $lead2->customer_id);

        // Verify only 1 customer user row exists for this phone/email
        $customerCount = User::where('role', 'customer')
            ->where(function ($q) use ($sharedPhone, $sharedEmail) {
                $q->where('email', $sharedEmail)->orWhere('phone', $sharedPhone);
            })
            ->count();

        $this->assertEquals(1, $customerCount);
    }

    /**
     * Overdue Follow-up Detection & Scopes
     */
    public function test_overdue_follow_up_scopes(): void
    {
        $overdueLead = CrmLead::create([
            'title' => 'Overdue Lead',
            'contact_name' => 'Rina KC',
            'phone' => '9841000001',
            'stage' => CrmLead::STAGE_CONTACTED,
            'priority' => CrmLead::PRIORITY_HIGH,
            'follow_up_date' => now()->subDay(),
        ]);

        $futureLead = CrmLead::create([
            'title' => 'Future Lead',
            'contact_name' => 'Binod Basnet',
            'phone' => '9841000002',
            'stage' => CrmLead::STAGE_CONTACTED,
            'priority' => CrmLead::PRIORITY_MEDIUM,
            'follow_up_date' => now()->addDays(3),
        ]);

        $wonOverdueLead = CrmLead::create([
            'title' => 'Won Lead with past date',
            'contact_name' => 'Sunita Rai',
            'phone' => '9841000003',
            'stage' => CrmLead::STAGE_WON,
            'priority' => CrmLead::PRIORITY_LOW,
            'follow_up_date' => now()->subDays(5),
        ]);

        $this->assertTrue($overdueLead->isOverdue());
        $this->assertFalse($futureLead->isOverdue());
        $this->assertFalse($wonOverdueLead->isOverdue()); // Won leads are never overdue

        $overdueIds = CrmLead::overdueFollowUps()->pluck('id')->toArray();
        $this->assertContains($overdueLead->id, $overdueIds);
        $this->assertNotContains($futureLead->id, $overdueIds);
        $this->assertNotContains($wonOverdueLead->id, $overdueIds);
    }

    /**
     * Visual Pipeline Kanban: Livewire Component Operations
     */
    public function test_crm_kanban_livewire_pipeline_and_kpis(): void
    {
        $lead = CrmLead::create([
            'title' => 'Winter Leather Boots Inquiry',
            'contact_name' => 'Prerana Malla',
            'phone' => '9849112233',
            'channel' => CrmLead::CHANNEL_WHATSAPP,
            'stage' => CrmLead::STAGE_NEW,
            'estimated_value' => 14000.00,
            'currency' => 'NPR',
            'priority' => CrmLead::PRIORITY_URGENT,
            'assigned_staff_id' => $this->storeManager->id,
            'follow_up_date' => now()->subHours(2), // Overdue
        ]);

        Livewire::actingAs($this->admin, 'admin')
            ->test(CrmKanban::class)
            ->assertSuccessful()
            // Verify KPI metrics computation
            ->assertSee('Winter Leather Boots Inquiry')
            ->assertSee('Prerana Malla')
            // Advance stage via Livewire
            ->call('moveStage', $lead->id, CrmLead::STAGE_QUALIFIED)
            ->assertHasNoErrors();

        $this->assertEquals(CrmLead::STAGE_QUALIFIED, $lead->fresh()->stage);

        // Verify activity log recorded stage change
        $this->assertTrue(
            $lead->activities()->where('type', CrmActivity::TYPE_STAGE_CHANGE)->exists()
        );

        // Test Overdue filter toggle and mobile stage switcher
        Livewire::actingAs($this->admin, 'admin')
            ->test(CrmKanban::class)
            ->call('toggleOverdueFilter')
            ->assertSet('filterOverdueOnly', true)
            ->call('setMobileActiveStage', 'quotation')
            ->assertSet('mobileActiveStage', 'quotation')
            ->call('openLeadDetail', $lead->id)
            ->call('setQuickFollowUpDays', 2, 'WhatsApp clienteling follow-up SLA')
            ->call('updateLeadFollowUp')
            ->assertHasNoErrors();

        $this->assertNotNull($lead->fresh()->follow_up_date);
    }

    /**
     * RBAC and Policy Authorization
     */
    public function test_crm_policy_authorization(): void
    {
        $lead = CrmLead::create([
            'title' => 'Policy Test Lead',
            'contact_name' => 'Niraj Shrestha',
            'phone' => '9800001122',
            'stage' => CrmLead::STAGE_NEW,
        ]);

        $message = ContactMessage::create([
            'name' => 'Inquiry User',
            'email' => 'inquiry@example.com',
            'subject' => 'Test Subject',
            'message' => 'Testing message body',
            'status' => 'new',
        ]);

        // Admin has full CRUD
        $this->assertTrue($this->admin->can('viewAny', CrmLead::class));
        $this->assertTrue($this->admin->can('create', CrmLead::class));
        $this->assertTrue($this->admin->can('update', $lead));
        $this->assertTrue($this->admin->can('delete', $lead));

        // Store Manager has access
        $this->assertTrue($this->storeManager->can('viewAny', CrmLead::class));
        $this->assertTrue($this->storeManager->can('create', CrmLead::class));
        $this->assertTrue($this->storeManager->can('update', $lead));

        // Viewer is read-only
        $this->assertTrue($this->viewer->can('viewAny', CrmLead::class));
        $this->assertFalse($this->viewer->can('create', CrmLead::class));
        $this->assertFalse($this->viewer->can('update', $lead));
        $this->assertFalse($this->viewer->can('delete', $lead));
    }
}
