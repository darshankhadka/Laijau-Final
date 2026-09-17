<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\AccountingInvoiceResource;
use App\Filament\Resources\BikriKhataResource;
use App\Filament\Resources\JournalEntryResource;
use App\Filament\Resources\KharidKhataResource;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\KharidKhataEntry;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountingInspectUiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql']);
        config(['database.connections.mysql.database' => 'laijau_staging']);
        config(['database.connections.mysql.host' => '127.0.0.1']);
        config(['database.connections.mysql.username' => 'darshan']);
        config(['database.connections.mysql.password' => 'Dars@@9861']);
        DB::purge('mysql');
        DB::reconnect('mysql');
    }

    /**
     * Test that sales invoice inspect modal renders all minimalist UI components:
     * Hero badges, 4-card KPI strip, customer details, line items table, and GL audit breakdown.
     */
    public function test_sales_invoice_inspect_modal_renders_correctly(): void
    {
        $invoice = AccountingInvoice::where('type', 'sales_invoice')
            ->whereHas('items')
            ->whereHas('journalEntry')
            ->first();

        $this->assertNotNull($invoice, 'A sales invoice with items and GL journal entry must exist.');

        $html = view('filament.components.accounting-inspect-modal', [
            'record' => $invoice,
            'context' => 'sales',
        ])->render();

        $this->assertStringContainsString($invoice->invoice_number, $html);
        $this->assertStringContainsString('Tax Invoice (Bikri Khata)', $html);
        $this->assertStringContainsString('Subtotal (Taxable)', $html);
        $this->assertStringContainsString('Output VAT (13%)', $html);
        $this->assertStringContainsString('Customer / Buyer Profile', $html);
        $this->assertStringContainsString('Merchandise Line Items', $html);
        $this->assertStringContainsString('Double-Entry Journal Voucher', $html);
        $this->assertStringContainsString('0.0000 NPR Variance', $html);
    }

    /**
     * Test that purchase bill inspect modal renders procurement-specific minimalist UI:
     * Supplier profile, Input VAT, Procured bill line items, and statutory IRD Annex 8 markers.
     */
    public function test_purchase_bill_inspect_modal_renders_correctly(): void
    {
        $purchaseBill = KharidKhataEntry::first();

        if (!$purchaseBill) {
            $purchaseBill = AccountingInvoice::create([
                'type' => 'supplier_bill',
                'invoice_number' => 'BILL-TEST-9901',
                'fiscal_year' => '2081/82',
                'contact_name' => 'Apex Footwear Supplies Pvt Ltd',
                'seller_pan' => '601234567',
                'issue_date' => now()->toDateString(),
                'subtotal' => 20000.00,
                'taxable_amount' => 20000.00,
                'vat_amount' => 2600.00,
                'total_amount' => 22600.00,
                'payment_status' => 'paid',
                'branch' => 'Kathmandu Central',
            ]);
        }

        $html = view('filament.components.accounting-inspect-modal', [
            'record' => $purchaseBill,
            'context' => 'purchase',
        ])->render();

        $this->assertStringContainsString($purchaseBill->invoice_number, $html);
        $this->assertStringContainsString('Supplier Bill (Kharid Khata)', $html);
        $this->assertStringContainsString('Input VAT (13%)', $html);
        $this->assertStringContainsString('Supplier / Vendor Profile', $html);
        $this->assertStringContainsString('Procured Bill Line Items', $html);
        $this->assertStringContainsString('Bill Total:', $html);
    }

    /**
     * Test that journal entry inspect modal renders equilibrium seal, hero debit/credit KPIs, and GL accounts.
     */
    public function test_journal_entry_inspect_modal_renders_correctly(): void
    {
        $entry = JournalEntry::where('status', 'posted')
            ->whereHas('lines')
            ->first();

        $this->assertNotNull($entry, 'A posted journal entry with lines must exist.');

        $html = view('filament.components.journal-entry-inspect-modal', [
            'record' => $entry,
        ])->render();

        $this->assertStringContainsString($entry->entry_number, $html);
        $this->assertStringContainsString('Balanced', $html);
        $this->assertStringContainsString('Total Debit Voucher Amount', $html);
        $this->assertStringContainsString('Total Credit Voucher Amount', $html);
        $this->assertStringContainsString('Journal Entry Lines', $html);
        $this->assertStringContainsString('Total Equilibrium:', $html);
    }

    /**
     * Test that all target accounting resources configure the bespoke 'inspect' action.
     */
    public function test_filament_resources_configure_inspect_action(): void
    {
        $livewireMock = new class extends \Livewire\Component implements \Filament\Tables\Contracts\HasTable {
            use \Filament\Tables\Concerns\InteractsWithTable;
            public function makeFilamentTranslatableContentDriver(): ?\Filament\Support\Contracts\TranslatableContentDriver { return null; }
            public function render() { return '<div></div>'; }
        };

        $bikriTable = BikriKhataResource::table(new Table($livewireMock));
        $this->assertNotNull($bikriTable->getAction('inspect'), 'BikriKhataResource must have an inspect action.');

        $kharidTable = KharidKhataResource::table(new Table($livewireMock));
        $this->assertNotNull($kharidTable->getAction('inspect'), 'KharidKhataResource must have an inspect action.');

        $invoiceTable = AccountingInvoiceResource::table(new Table($livewireMock));
        $this->assertNotNull($invoiceTable->getAction('inspect'), 'AccountingInvoiceResource must have an inspect action.');

        $journalTable = JournalEntryResource::table(new Table($livewireMock));
        $this->assertNotNull($journalTable->getAction('inspect'), 'JournalEntryResource must have an inspect action.');
    }
}
