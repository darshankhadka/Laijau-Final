<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CrmActivity;
use App\Models\CrmLead;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CrmInquiryDataIntegrityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        try {
            config(['database.default' => 'mysql']);
            config(['database.connections.mysql.database' => 'LAIJAU']);
            DB::purge('mysql');
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL production database snapshot not available for CRM inquiry test: ' . $e->getMessage());
        }
    }

    public function test_authoritative_crm_inquiries_are_fully_imported(): void
    {
        $this->assertSame(41, CrmLead::count(), 'Expected exactly 41 authoritative CRM leads');
        $this->assertGreaterThanOrEqual(41, CrmActivity::count(), 'Expected at least 41 CRM activity records');
    }

    public function test_crm_leads_have_correct_channel_and_currency(): void
    {
        $nonWhatsapp = CrmLead::where('channel', '!=', CrmLead::CHANNEL_WHATSAPP)->count();
        $this->assertSame(0, $nonWhatsapp, 'All authoritative CRM inquiries must have channel = whatsapp');

        $nonNpr = CrmLead::where('currency', '!=', 'NPR')->count();
        $this->assertSame(0, $nonNpr, 'All CRM leads must have currency = NPR');
    }

    public function test_crm_leads_have_valid_timeline_and_no_future_entries(): void
    {
        $futureLeads = CrmLead::where('event_date', '>', '2026-09-12')->count();
        $this->assertSame(0, $futureLeads, 'No CRM lead should have an event_date past the launch boundary (2026-09-12)');

        $beforeBoundary = CrmLead::where('event_date', '<', '2026-08-29')->count();
        $this->assertSame(0, $beforeBoundary, 'No CRM lead should be before 2026-08-29');
    }

    public function test_converted_crm_leads_link_to_orders(): void
    {
        $wonLeads = CrmLead::with('order')->where('stage', CrmLead::STAGE_WON)->get();
        $this->assertGreaterThanOrEqual(5, $wonLeads->count(), 'Expected at least 5 converted won leads');

        foreach ($wonLeads as $lead) {
            $this->assertNotNull($lead->order_id, "Won lead #{$lead->id} ({$lead->contact_name}) must be linked to an order");
            $this->assertNotNull($lead->order, "Order relation must exist for won lead #{$lead->id}");
        }
    }

    public function test_crm_import_strictly_preserves_users_and_statutory_invariants(): void
    {
        $this->assertSame(569, DB::table('users')->count(), 'Statutory users count must remain exactly 569');

        $shoesStock = (int) DB::table('inventory_stock_levels')->where('warehouse_id', 1)->sum('quantity_on_hand');
        $clothesStock = (int) DB::table('inventory_stock_levels')->where('warehouse_id', 2)->sum('quantity_on_hand');
        $this->assertSame(2721, $shoesStock + $clothesStock, 'Physical stock must remain exactly 2,721 pcs');

        $supplierDue = (float) DB::table('inventory_suppliers')->sum('due_balance');
        $this->assertEqualsWithDelta(4212905.00, $supplierDue, 0.01, 'Supplier balance must remain NPR 4,212,905.00');

        $debit = (float) DB::table('accounting_journal_entries')->where('status', 'posted')->sum('total_debit');
        $credit = (float) DB::table('accounting_journal_entries')->where('status', 'posted')->sum('total_credit');
        $this->assertEqualsWithDelta($debit, $credit, 0.0001, 'General Ledger must be balanced');
    }
}
