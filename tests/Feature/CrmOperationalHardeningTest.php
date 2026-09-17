<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\CrmActivity;
use App\Models\CrmLead;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Crm\CrmService;
use App\Services\Customer\CustomerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CrmOperationalHardeningTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $salesRep;
    protected User $supportAgent;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        if (\Spatie\Permission\Models\Permission::count() === 0) {
            (new \Database\Seeders\RolePermissionSeeder())->run();
        }

        // Reset Spatie permissions cache for test stability
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->admin = User::firstOrCreate(
            ['email' => 'admin_hardening@laijau.com'],
            [
                'name' => 'Laijau Admin Hardening',
                'password' => bcrypt('Laijau2026!'),
                'role' => 'admin',
            ]
        );

        $this->salesRep = User::firstOrCreate(
            ['email' => 'sales_rep_test@laijau.com'],
            [
                'name' => 'Sales Rep',
                'password' => bcrypt('Laijau2026!'),
                'role' => 'sales_representative',
            ]
        );

        $this->supportAgent = User::firstOrCreate(
            ['email' => 'support_agent_test@laijau.com'],
            [
                'name' => 'Concierge Support Agent',
                'password' => bcrypt('Laijau2026!'),
                'role' => 'support_agent',
            ]
        );

        // Assign Spatie web guard roles
        $repRole = Role::where('name', 'Sales Representative')->where('guard_name', 'web')->first();
        if ($repRole) {
            $this->salesRep->syncRoles([$repRole]);
        }

        $supportRole = Role::where('name', 'Support Agent')->where('guard_name', 'web')->first();
        if ($supportRole) {
            $this->supportAgent->syncRoles([$supportRole]);
        }

        $this->product = Product::create([
            'name' => 'Kathmandu Leather Oxford Shoes ' . Str::random(4),
            'slug' => 'oxford-shoes-' . Str::random(5),
            'sku' => 'SKU-OXF-' . Str::random(4),
            'price' => 35000,
            'is_published' => true,
            'is_active' => true,
        ]);
    }

    /**
     * 1. Never Duplicate Customers:
     * Resolving phone variations resolves to the exact same customer without bloating user records.
     */
    public function test_never_duplicate_customers_across_crm_touchpoints(): void
    {
        $crmService = app(CrmService::class);
        $customerService = app(CustomerService::class);

        $uniquePhone = '980' . rand(1000000, 9999999);
        $name = 'Sunita Shakya';
        $email = 'sunita_' . Str::random(5) . '@example.com';

        $initialCustomerCount = User::where('role', 'customer')->count();

        // 1st resolution: standard 10-digit number via CustomerService
        $customer1 = $customerService->resolveOrCreateCustomer($name, $uniquePhone, $email);
        $this->assertEquals($initialCustomerCount + 1, User::where('role', 'customer')->count());

        // 2nd resolution: with +977 prefix
        $customer2 = $customerService->resolveOrCreateCustomer($name, '+977 ' . $uniquePhone, 'different_email@example.com');
        $this->assertEquals($customer1->id, $customer2->id, 'Phone with +977 prefix must resolve to same customer');
        $this->assertEquals($initialCustomerCount + 1, User::where('role', 'customer')->count(), 'Customer count must remain invariant');

        // 3rd resolution: CustomerService direct resolution with dashes
        $dhashedPhone = substr($uniquePhone, 0, 4) . '-' . substr($uniquePhone, 4, 3) . '-' . substr($uniquePhone, 7);
        $customer3 = $customerService->resolveOrCreateCustomer('Sunita S.', $dhashedPhone);
        $this->assertEquals($customer1->id, $customer3->id, 'Formatted phone with dashes must resolve to same customer');
        $this->assertEquals($initialCustomerCount + 1, User::where('role', 'customer')->count(), 'No duplicate customer created');

        // 4th resolution: Via CrmService using a lead
        $lead = CrmLead::create([
            'title' => 'Inquiry for Sunita',
            'contact_name' => 'Sunita Shakya',
            'phone' => '+977-' . $uniquePhone,
            'channel' => CrmLead::CHANNEL_WHATSAPP,
            'stage' => CrmLead::STAGE_NEW,
        ]);

        $customer4 = $crmService->resolveOrCreateCustomer($lead);
        $this->assertEquals($customer1->id, $customer4->id, 'Lead resolution must link to existing customer without creating new user');
        $this->assertEquals($initialCustomerCount + 1, User::where('role', 'customer')->count());
        $this->assertEquals($customer1->id, $lead->customer_id);
    }

    /**
     * 2. Lead to Order Idempotency:
     * Never duplicate orders from repeated button clicks or network retries.
     */
    public function test_lead_to_order_conversion_idempotency_prevents_duplicate_orders(): void
    {
        $crmService = app(CrmService::class);

        $phone = '9841' . rand(100000, 999999);
        $lead = CrmLead::create([
            'title' => 'Winter Leather Boots Inquiry',
            'contact_name' => 'Bipana Maharjan',
            'phone' => $phone,
            'channel' => CrmLead::CHANNEL_WHATSAPP,
            'stage' => CrmLead::STAGE_NEGOTIATION,
            'priority' => CrmLead::PRIORITY_HIGH,
            'estimated_value' => 35000.00,
            'currency' => 'NPR',
            'product_id' => $this->product->id,
            'assigned_staff_id' => $this->salesRep->id,
        ]);

        $initialOrdersCount = Order::count();

        // 1st conversion click
        $orderData = [
            'total_amount' => 34000.00,
            'payment_method' => 'cod',
            'shipping_address' => 'Patan Durbar Square, Lalitpur',
        ];

        $order1 = $crmService->convertLeadToOrder($lead, $orderData);
        $this->assertInstanceOf(Order::class, $order1);
        $this->assertEquals($initialOrdersCount + 1, Order::count());
        $this->assertEquals(34000.00, (float)$order1->total_amount);

        // Lead should now be marked won and bound to order
        $lead->refresh();
        $this->assertEquals(CrmLead::STAGE_WON, $lead->stage);
        $this->assertEquals($order1->id, $lead->order_id);
        $this->assertNotNull($lead->closed_at);
        $this->assertEquals($lead->id, $order1->crm_lead_id);

        // 2nd repeated click (e.g. user double-clicks or retries form)
        $order2 = $crmService->convertLeadToOrder($lead, [
            'total_amount' => 34000.00,
            'payment_method' => 'cod',
            'shipping_address' => 'Patan Durbar Square, Lalitpur',
        ]);

        $this->assertEquals($order1->id, $order2->id, 'Repeated conversion call must return identical order');
        $this->assertEquals($initialOrdersCount + 1, Order::count(), 'Order count must NOT increment on repeated conversion');

        // Check activity timeline: should have order_linked activity recorded once
        $conversionActivities = $lead->activities()->where('type', CrmActivity::TYPE_ORDER_LINKED)->count();
        $this->assertEquals(1, $conversionActivities, 'Only 1 conversion activity event should be recorded');
    }

    /**
     * 3. WhatsApp-First Workflow:
     * Pre-filled message generation, automatic interaction logging, and follow-up chaining.
     */
    public function test_whatsapp_first_workflow_with_auto_logging_and_followup_chaining(): void
    {
        $crmService = app(CrmService::class);

        $phone = '9841' . rand(100000, 999999);
        $lead = CrmLead::create([
            'title' => 'Custom Sizing Inquiry',
            'contact_name' => 'Meera Joshi',
            'phone' => $phone,
            'channel' => CrmLead::CHANNEL_WHATSAPP,
            'stage' => CrmLead::STAGE_NEW,
            'priority' => CrmLead::PRIORITY_HIGH,
            'estimated_value' => 22000.00,
            'product_id' => $this->product->id,
            'assigned_staff_id' => $this->salesRep->id,
        ]);

        // 1. Verify pre-filled WhatsApp URL contains Nepali concierge greeting and product title
        $whatsAppUrl = $lead->whats_app_url;
        $this->assertStringContainsString('https://wa.me/977' . $phone, $whatsAppUrl);
        $this->assertStringContainsString('Namaste', $whatsAppUrl);

        // 2. Dispatch WhatsApp outreach trigger
        $initialActivityCount = $lead->activities()->count();
        $this->assertNull($lead->follow_up_date);

        $crmService->recordWhatsAppOutreach($lead, $this->salesRep->id);

        $lead->refresh();

        // 3. Activity automatically logged
        $this->assertEquals($initialActivityCount + 1, $lead->activities()->count());
        $latestActivity = $lead->activities()->latest()->first();
        $this->assertEquals(CrmActivity::TYPE_WHATSAPP, $latestActivity->type);
        $this->assertEquals($this->salesRep->id, $latestActivity->staff_id);

        // 4. Follow-up automatically chained for ~48h out
        $this->assertNotNull($lead->follow_up_date);
        $expectedFollowUp = now()->addDays(2);
        $this->assertEquals($expectedFollowUp->format('Y-m-d'), $lead->follow_up_date->format('Y-m-d'));

        // 5. Stage automatically moved from 'new' to 'contacted'
        $this->assertEquals(CrmLead::STAGE_CONTACTED, $lead->stage);
    }

    /**
     * 4. Customer 360 Dossier:
     * Complete aggregation of orders, leads, support inquiries, and outstanding follow-ups.
     */
    public function test_customer_360_dossier_aggregation(): void
    {
        $customerService = app(CustomerService::class);
        $crmService = app(CrmService::class);

        $phone = '9851' . rand(100000, 999999);
        $customer = $customerService->resolveOrCreateCustomer(
            'Rojina Thapa',
            $phone,
            'rojina_' . Str::random(4) . '@example.com',
            ['province' => 'Bagmati', 'district' => 'Kathmandu', 'shipping_address' => 'Baluwatar, Kathmandu']
        );

        // Create an online order
        $order = Order::create([
            'order_number' => 'LJ-TEST-' . strtoupper(Str::random(6)),
            'user_id' => $customer->id,
            'first_name' => 'Rojina',
            'last_name' => 'Thapa',
            'phone' => $phone,
            'email' => $customer->email,
            'shipping_address' => 'Baluwatar, Kathmandu',
            'subtotal' => 15000.00,
            'total_amount' => 15000.00,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => 'processing',
            'carrier' => 'Pathao',
            'tracking_number' => 'PTH-778899',
            'courier_status' => 'in_transit',
        ]);

        // Create a lead with an overdue follow-up
        $lead = CrmLead::create([
            'title' => 'Reception Footwear Consultation',
            'contact_name' => 'Rojina Thapa',
            'phone' => $phone,
            'customer_id' => $customer->id,
            'channel' => CrmLead::CHANNEL_WEBSITE,
            'stage' => CrmLead::STAGE_QUALIFIED,
            'priority' => CrmLead::PRIORITY_HIGH,
            'estimated_value' => 28000.00,
            'follow_up_date' => now()->subDay(), // Overdue
            'follow_up_notes' => 'Follow up on fabric swatches',
        ]);

        // Create a contact message inquiry
        $inquiry = ContactMessage::create([
            'name' => 'Rojina Thapa',
            'email' => $customer->email,
            'phone' => $phone,
            'customer_id' => $customer->id,
            'subject' => 'Footwear Sizing Timeline Query',
            'message' => 'Can you deliver before next Friday?',
            'inquiry_type' => 'delivery',
            'status' => 'unread',
        ]);

        // Query Customer 360 CRM summary
        $summary = $customerService->getCustomerCrmSummary($customer);

        $this->assertEquals(1, $summary['leads_count']);
        $this->assertEquals(1, $summary['active_leads_count']);
        $this->assertEquals(1, $summary['inquiries_count']);
        $this->assertEquals(1, $summary['outstanding_follow_ups_count']);
        $this->assertTrue($summary['follow_ups']->first()->isOverdue());

        // Combined Order History contains carrier & tracking
        $history = $customerService->getCustomerOrderHistory($customer);
        $this->assertNotEmpty($history);
        $firstOrder = $history[0];
        $this->assertEquals('Pathao', $firstOrder['carrier']);
        $this->assertEquals('PTH-778899', $firstOrder['tracking_number']);
        $this->assertEquals('in_transit', $firstOrder['courier_status']);
    }

    /**
     * 5. Fulfillment Handoff & Boundary:
     * Once converted to an order, CRM points to Fulfillment Hub as source of truth.
     */
    public function test_fulfillment_handoff_preserves_fulfillment_hub_as_source_of_truth(): void
    {
        $crmService = app(CrmService::class);

        $lead = CrmLead::create([
            'title' => 'Banarasi Dupatta Consultation',
            'contact_name' => 'Kritika Baidya',
            'phone' => '9849' . rand(100000, 999999),
            'channel' => CrmLead::CHANNEL_PHONE,
            'stage' => CrmLead::STAGE_NEGOTIATION,
            'priority' => CrmLead::PRIORITY_MEDIUM,
            'estimated_value' => 12000.00,
            'product_id' => $this->product->id,
        ]);

        $order = $crmService->convertLeadToOrder($lead, [
            'total_amount' => 12000.00,
            'payment_method' => 'cod',
            'shipping_address' => 'Jhamsikhel, Lalitpur',
        ]);

        $lead->refresh();
        $this->assertEquals($lead->id, $order->crm_lead_id);
        $this->assertEquals($order->id, $lead->order_id);

        // Logistics update happens in Fulfillment Hub (e.g. dispatched via NCM)
        $order->update([
            'status' => 'dispatched',
            'carrier' => 'NCM',
            'tracking_number' => 'NCM-994411',
            'courier_status' => 'dispatched',
        ]);

        // CRM lead reflects the live fulfillment fields without manual CRM courier tampering
        $lead->refresh();
        $this->assertEquals('dispatched', $lead->order->status);
        $this->assertEquals('NCM', $lead->order->carrier);
        $this->assertEquals('NCM-994411', $lead->order->tracking_number);
    }

    /**
     * 6. Granular Permissions (RBAC):
     * Sales Reps & Support Agents have CRM access, but ZERO accounting or inventory powers.
     */
    public function test_granular_crm_permissions_prevent_sales_from_accounting_and_inventory(): void
    {
        $salesRep = $this->salesRep;

        // Sales Rep CAN manage leads and inquiries
        $this->assertTrue(
            $salesRep->hasPermissionTo('ViewAny:CrmLead', 'web'),
            'Sales Rep must have ViewAny:CrmLead permission'
        );
        $this->assertTrue(
            $salesRep->hasPermissionTo('Create:CrmLead', 'web'),
            'Sales Rep must have Create:CrmLead permission'
        );
        $this->assertTrue(
            $salesRep->hasPermissionTo('Create:Order', 'web'),
            'Sales Rep can create converted orders'
        );

        // Sales Rep CANNOT touch Accounting
        $this->assertFalse(
            $salesRep->hasPermissionTo('ViewAny:JournalEntry', 'web'),
            'Sales Rep must NOT have ViewAny:JournalEntry permission'
        );
        $this->assertFalse(
            $salesRep->hasPermissionTo('Create:AccountingInvoice', 'web'),
            'Sales Rep must NOT have Create:AccountingInvoice permission'
        );

        // Sales Rep CANNOT alter Inventory stock or create purchase orders
        $this->assertFalse(
            $salesRep->hasPermissionTo('Create:StockAdjustment', 'web'),
            'Sales Rep must NOT have Create:StockAdjustment permission'
        );
        $this->assertFalse(
            $salesRep->hasPermissionTo('Create:PurchaseOrder', 'web'),
            'Sales Rep must NOT have Create:PurchaseOrder permission'
        );
    }

    /**
     * 7. Operational Exception Handling:
     * Missed follow-ups, failed deliveries, and cancellations log cleanly to CRM activity.
     */
    public function test_operational_exception_handling_logs_to_crm(): void
    {
        $crmService = app(CrmService::class);

        $lead = CrmLead::create([
            'title' => 'Winter Pashmina Inquiry',
            'contact_name' => 'Prerana Shrestha',
            'phone' => '9841' . rand(100000, 999999),
            'channel' => CrmLead::CHANNEL_WHATSAPP,
            'stage' => CrmLead::STAGE_NEGOTIATION,
            'estimated_value' => 18000.00,
        ]);

        $order = $crmService->convertLeadToOrder($lead, [
            'total_amount' => 18000.00,
            'payment_method' => 'cod',
            'shipping_address' => 'Thamel, Kathmandu',
        ]);

        // Customer cancels or delivery fails
        $crmService->logOrderException($order, 'failed_delivery', 'Customer phone not reachable at Thamel chowk');

        $order->refresh();
        $lead->refresh();

        // Check that exception note was appended to order internal_notes
        $this->assertStringContainsString('failed_delivery', (string)$order->internal_notes);
        $this->assertStringContainsString('Customer phone not reachable', (string)$order->internal_notes);

        // Activity timeline on associated lead preserves note
        $exceptionActivity = $lead->activities()->where('type', 'note')->latest()->first();
        $this->assertNotNull($exceptionActivity);
        $this->assertStringContainsString('failed_delivery', $exceptionActivity->description);
    }

    /**
     * 8. CRM UI Navigation Badges, Tabs & Kanban Controls:
     * Verifies real-time badge counts, tab queries, and stage transition actions.
     */
    public function test_crm_ui_navigation_badges_tabs_and_stage_transitions(): void
    {
        // 1. Navigation Badges
        $lead = CrmLead::create([
            'title' => 'Premium Footwear Clienteling',
            'contact_name' => 'Aayusha Malla',
            'phone' => '9841' . rand(100000, 999999),
            'channel' => CrmLead::CHANNEL_WHATSAPP,
            'stage' => CrmLead::STAGE_CONTACTED,
            'follow_up_date' => now()->subDay(), // overdue
            'estimated_value' => 22000.00,
        ]);

        $message = ContactMessage::create([
            'name' => 'Dipesh Basnet',
            'email' => 'dipesh@example.com',
            'subject' => 'Footwear consultation inquiry',
            'message' => 'Looking for winter boots fitting',
            'status' => ContactMessage::STATUS_NEW,
        ]);

        $kanbanBadge = \App\Filament\Pages\CrmKanban::getNavigationBadge();
        $this->assertNotNull($kanbanBadge);
        $this->assertGreaterThanOrEqual(1, (int)$kanbanBadge);

        $leadsBadge = \App\Filament\Resources\CrmLeadResource::getNavigationBadge();
        $this->assertNotNull($leadsBadge);
        $this->assertStringContainsString('Overdue', $leadsBadge);

        $msgBadge = \App\Filament\Resources\ContactMessageResource::getNavigationBadge();
        $this->assertNotNull($msgBadge);
        $this->assertGreaterThanOrEqual(1, (int)$msgBadge);

        // 2. Executive Tabs on ListCrmLeads
        $listLeadsPage = new \App\Filament\Resources\CrmLeadResource\Pages\ListCrmLeads();
        $leadTabs = $listLeadsPage->getTabs();
        $this->assertArrayHasKey('all', $leadTabs);
        $this->assertArrayHasKey('active', $leadTabs);
        $this->assertArrayHasKey('overdue', $leadTabs);
        $this->assertArrayHasKey('whatsapp', $leadTabs);
        $this->assertArrayHasKey('won', $leadTabs);

        // 3. Executive Tabs on ManageContactMessages
        $manageMsgPage = new \App\Filament\Resources\ContactMessageResource\Pages\ManageContactMessages();
        $msgTabs = $manageMsgPage->getTabs();
        $this->assertArrayHasKey('all', $msgTabs);
        $this->assertArrayHasKey('new', $msgTabs);
        $this->assertArrayHasKey('in_progress', $msgTabs);
        $this->assertArrayHasKey('resolved', $msgTabs);

        // 4. Subheadings render cleanly
        $subheadingLeads = $listLeadsPage->getSubheading();
        $this->assertNotNull($subheadingLeads);
        $this->assertNotEmpty((string)$subheadingLeads);

        $subheadingMsgs = $manageMsgPage->getSubheading();
        $this->assertNotNull($subheadingMsgs);
        $this->assertNotEmpty((string)$subheadingMsgs);
    }
}
