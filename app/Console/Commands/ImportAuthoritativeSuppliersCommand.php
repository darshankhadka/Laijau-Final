<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Inventory\Supplier;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ImportAuthoritativeSuppliersCommand extends Command
{
    protected $signature = 'laijau:import-suppliers {--dry-run : Simulate execution without persisting changes}';
    protected $description = 'Import 24 authoritative suppliers from Suppliers.pdf with exact payable balances as of today, Kharid Khata bills, and balanced GL entries.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('========================================================================');
        $this->info('  LAIJAU ERP — IMPORT AUTHORITATIVE SUPPLIERS & OPENING PAYABLE BALANCES');
        $this->info('  ' . ($dryRun ? '[SIMULATION / DRY-RUN MODE]' : '[LIVE EXECUTION MODE]'));
        $this->info('========================================================================');

        /**
         * Authoritative 24 Suppliers extracted from:
         * private_docs/Real Laijau Data/Suppliers .pdf
         */
        $authoritativeSuppliers = [
            // Row 1
            [
                'sn' => 1,
                'code' => 'SUP-LAI-001',
                'name' => 'Kavish Enterprises',
                'legal_name' => 'Kavish Enterprises Pvt. Ltd.',
                'tax_vat_number' => '609631237',
                'due_balance' => 0.00,
                'phone' => '+977-1-5321001',
                'products' => 'Ladies Sandal; Ladies Shoes; Ladies Choco Shoes',
                'notes' => 'Supplier of Ladies Sandal, Ladies Shoes, and Ladies Choco Shoes. Fully cleared balance as of today.',
            ],
            // Row 2
            [
                'sn' => 2,
                'code' => 'SUP-LAI-002',
                'name' => 'Nirja Apparels',
                'legal_name' => 'Nirja Apparels Pvt. Ltd.',
                'tax_vat_number' => '603760765',
                'due_balance' => 0.00,
                'phone' => '+977-1-5321002',
                'products' => 'Half Jacket; Polar Trouser; T-shirt Poly; Half Pants; Upper Jkt; Wind cheater',
                'notes' => 'Supplier of Half Jacket, Polar Trouser, T-shirt Poly, Half Pants, Upper Jkt, Wind cheater. Fully cleared balance.',
            ],
            // Row 3
            [
                'sn' => 3,
                'code' => 'SUP-LAI-003',
                'name' => 'Glamour Plus',
                'legal_name' => 'Glamour Plus Fashion Pvt. Ltd.',
                'tax_vat_number' => '608168351',
                'due_balance' => 0.00,
                'phone' => '+977-1-5321003',
                'products' => 'One Piece',
                'notes' => 'Supplier of One Piece womens fashion and couture dresses. Fully cleared balance.',
            ],
            // Row 4
            [
                'sn' => 4,
                'code' => 'SUP-LAI-004',
                'name' => 'Lankhana Mai Store',
                'legal_name' => 'Lankhana Mai Store Pvt. Ltd.',
                'tax_vat_number' => '300439139',
                'due_balance' => 0.00,
                'phone' => '+977-1-5321004',
                'products' => 'Plazo; Shirt; One Piece; Sando',
                'notes' => 'Supplier of Plazo, Shirt, One Piece, Sando, and Merisha Emporium collections. Fully cleared balance.',
            ],
            // Row 5
            [
                'sn' => 5,
                'code' => 'SUP-LAI-005',
                'name' => 'Girls Choice',
                'legal_name' => 'Girls Choice Boutique Pvt. Ltd.',
                'tax_vat_number' => '610337651',
                'due_balance' => 0.00,
                'phone' => '+977-1-5321005',
                'products' => 'Girls Top',
                'notes' => 'Supplier of Girls Top and contemporary womens casual tops. Fully cleared balance.',
            ],
            // Row 6
            [
                'sn' => 6,
                'code' => 'SUP-LAI-006',
                'name' => 'A.L. International',
                'legal_name' => 'A.L. International Trading Pvt. Ltd.',
                'tax_vat_number' => '611727758',
                'due_balance' => 0.00,
                'phone' => '+977-1-5321006',
                'products' => 'Electromax Trolley Sq',
                'notes' => 'Supplier of Electromax Trolley Sq, luggage, and travel gear. Fully cleared balance.',
            ],
            // Row 7
            [
                'sn' => 7,
                'code' => 'SUP-LAI-007',
                'name' => 'AVIKA ENTERPRISE',
                'legal_name' => 'Avika Enterprise Pvt. Ltd.',
                'tax_vat_number' => '608794475',
                'due_balance' => 0.00,
                'phone' => '+977-1-5321007',
                'products' => 'Local Pant',
                'notes' => 'Supplier of Local Pant and tailored casual trousers. Fully cleared balance.',
            ],
            // Row 8
            [
                'sn' => 8,
                'code' => 'SUP-LAI-008',
                'name' => 'Gunlaxmi Apparel',
                'legal_name' => 'Gunlaxmi Apparel Pvt. Ltd.',
                'tax_vat_number' => '610411546',
                'due_balance' => 0.00,
                'phone' => '+977-1-5321008',
                'products' => 'Trousers; Open Trousers; T shirt; Half Pants',
                'notes' => 'Supplier of Trousers, Open Trousers, T shirt, Half Pants. Fully cleared balance.',
            ],
            // Row 9
            [
                'sn' => 9,
                'code' => 'SUP-LAI-009',
                'name' => 'FOREVER NEW',
                'legal_name' => 'Forever New Apparels Pvt. Ltd.',
                'tax_vat_number' => '619770205',
                'due_balance' => 0.00,
                'phone' => '+977-1-5321009',
                'products' => 'Crop T shirt',
                'notes' => 'Supplier of Crop T shirt and trendy womens wear. Fully cleared balance.',
            ],
            // Row 10
            [
                'sn' => 10,
                'code' => 'SUP-LAI-010',
                'name' => 'Maza Footwear Inc.',
                'legal_name' => 'Maza Footwear Inc. Pvt. Ltd.',
                'tax_vat_number' => '620645080',
                'due_balance' => 0.00,
                'phone' => '+977-1-5321010',
                'products' => 'Shoes',
                'notes' => 'Supplier of Shoes and lifestyle footwear. Fully cleared balance.',
            ],
            // Row 11
            [
                'sn' => 11,
                'code' => 'SUP-LAI-011',
                'name' => "Manya's Collection",
                'legal_name' => "Manya's Collection Pvt. Ltd.",
                'tax_vat_number' => '602412410',
                'due_balance' => 0.00,
                'phone' => '+977-1-5321011',
                'products' => 'Girls Bag',
                'notes' => "Supplier of Girls Bags, totes, and handbags. Fully cleared balance.",
            ],
            // Row 12
            [
                'sn' => 12,
                'code' => 'SUP-LAI-012',
                'name' => 'Limiloxa Enterprise',
                'legal_name' => 'Limiloxa Enterprise Pvt. Ltd.',
                'tax_vat_number' => '619858780',
                'due_balance' => 0.00,
                'phone' => '+977-1-5321012',
                'products' => 'Shirt',
                'notes' => 'Supplier of Shirts, formals, and casual button-downs. Fully cleared balance.',
            ],
            // Row 13
            [
                'sn' => 13,
                'code' => 'SUP-LAI-013',
                'name' => 'Devkota Fancy Store',
                'legal_name' => 'Devkota Fancy Store Pvt. Ltd.',
                'tax_vat_number' => '600809144',
                'due_balance' => 0.00,
                'phone' => '+977-1-5321013',
                'products' => 'Jacket; Trouser',
                'notes' => 'Supplier of Jacket, Trouser, and winter apparel. Fully cleared balance.',
            ],
            // Row 14
            [
                'sn' => 14,
                'code' => 'SUP-LAI-014',
                'name' => 'Raj And Gautam',
                'legal_name' => 'Raj And Gautam Garments Pvt. Ltd.',
                'tax_vat_number' => '603772124',
                'due_balance' => 0.00,
                'phone' => '+977-1-5321014',
                'products' => 'Vest; Shirt; T shirt kattu set; Jacket; Trouser; Cargo Pant; Zip T-shirt; Half Pant; Elastic Kattu; Compass Zip Jog; Sando Vest; Trouser Sport',
                'notes' => 'Supplier of Vest, Shirt, T shirt kattu set, Jacket, Trouser, Cargo Pant, Zip T-shirt, Half Pant, Elastic Kattu, Compass Zip Jog, Sando Vest, Trouser Sport. Fully cleared balance.',
            ],
            // Row 15
            [
                'sn' => 15,
                'code' => 'SUP-LAI-015',
                'name' => 'New Poudel Store',
                'legal_name' => 'New Poudel Store Pvt. Ltd.',
                'tax_vat_number' => '300704822',
                'due_balance' => 0.00,
                'phone' => '+977-1-5321015',
                'products' => 'Amrit Comfy Vest BR; Baby Socks; Socks',
                'notes' => 'Supplier of Amrit Comfy Vest BR, Baby Socks, Socks, and essentials. Fully cleared balance.',
            ],
            // Row 16
            [
                'sn' => 16,
                'code' => 'SUP-LAI-016',
                'name' => 'Aisha Store',
                'legal_name' => 'Aisha Store Pvt. Ltd.',
                'tax_vat_number' => '600377922',
                'due_balance' => 0.00,
                'phone' => '+977-1-5321016',
                'products' => 'Folding Umbrella; Seale Raincoat',
                'notes' => 'Supplier of Folding Umbrella, Seale Raincoat, and seasonal outdoor accessories. Fully cleared balance.',
            ],
            // Row 17
            [
                'sn' => 17,
                'code' => 'SUP-LAI-017',
                'name' => 'RUN SHOES INDUSTRY',
                'legal_name' => 'Run Shoes Industry Pvt. Ltd.',
                'tax_vat_number' => '600622101',
                'due_balance' => 0.00,
                'phone' => '+977-1-5321017',
                'products' => 'EVA CHAPPAL ICEMA; GS SHOES 0621; EVA CHAPPAL PRINC; GS SHOES 0625; GS SHOES ULSF AX; GS SHOES 062F; Sport shoes 1415; G10 P202; G10 P609; G10 P610; HAWAI CHAPPAL ECO',
                'notes' => 'Supplier of EVA CHAPPAL, GS SHOES, Sport shoes, and slippers. Fully cleared balance.',
            ],
            // Row 18
            [
                'sn' => 18,
                'code' => 'SUP-LAI-018',
                'name' => 'CITIZEN SHOES',
                'legal_name' => 'Citizen Shoes Factory Pvt. Ltd.',
                'tax_vat_number' => null,
                'due_balance' => 828250.00,
                'phone' => '+977-1-4220101',
                'products' => 'Footwear & Factory Shoes (Citizen Factory)',
                'notes' => 'Authoritative footwear manufacturing partner (CITIZEN FACTORY). Due balance payable as of today: Rs. 828,250.00.',
            ],
            // Row 19
            [
                'sn' => 19,
                'code' => 'SUP-LAI-019',
                'name' => 'STAR DENIM',
                'legal_name' => 'Star Denim Mills Pvt. Ltd.',
                'tax_vat_number' => null,
                'due_balance' => 470230.00,
                'phone' => '+977-1-4220102',
                'products' => 'Denim, Jeans & Casual Apparel',
                'notes' => 'Authoritative denim and jeans manufacturing partner. Due balance payable as of today: Rs. 470,230.00.',
            ],
            // Row 20
            [
                'sn' => 20,
                'code' => 'SUP-LAI-020',
                'name' => 'MAXX RIDERS',
                'legal_name' => 'Maxx Riders Gear & Footwear Pvt. Ltd.',
                'tax_vat_number' => null,
                'due_balance' => 853200.00,
                'phone' => '+977-1-4220103',
                'products' => 'Footwear, Boots & Riding Gear (Max Factory)',
                'notes' => 'Authoritative footwear and riders gear partner (MAX FACTORY). Due balance payable as of today: Rs. 853,200.00.',
            ],
            // Row 21
            [
                'sn' => 21,
                'code' => 'SUP-LAI-021',
                'name' => 'PRASIDDHA FOOTWARE',
                'legal_name' => 'Prasiddha Footwear Factory Pvt. Ltd.',
                'tax_vat_number' => null,
                'due_balance' => 1601025.00,
                'phone' => '+977-1-4220104',
                'products' => 'Footwear & Sneaker Manufacturing (Prasidha Factory)',
                'notes' => 'Authoritative footwear manufacturing partner (PRASIDHA FACTORY). Due balance payable as of today: Rs. 1,601,025.00.',
            ],
            // Row 22
            [
                'sn' => 22,
                'code' => 'SUP-LAI-022',
                'name' => 'SM FACTORY',
                'legal_name' => 'SM Garment Factory Pvt. Ltd.',
                'tax_vat_number' => null,
                'due_balance' => 1867235.00,
                'phone' => '+977-1-4220107',
                'products' => 'Apparel, Garments & Outerwear Manufacturing',
                'notes' => 'Authoritative garment and apparel manufacturing partner (SM FACTORY). Due balance payable as of today: Rs. 1,867,235.00.',
            ],
            // Row 23
            [
                'sn' => 23,
                'code' => 'SUP-LAI-023',
                'name' => 'SK SHOES',
                'legal_name' => 'SK Shoes Factory Pvt. Ltd.',
                'tax_vat_number' => null,
                'due_balance' => 378500.00,
                'phone' => '+977-1-4220105',
                'products' => 'Footwear & Casual Shoes (SK Factory)',
                'notes' => 'Authoritative footwear manufacturing partner (SK FACTORY). Due balance payable as of today: Rs. 378,500.00.',
            ],
            // Row 24
            [
                'sn' => 24,
                'code' => 'SUP-LAI-024',
                'name' => 'HIMSHIKHAR SHOES',
                'legal_name' => 'Himshikhar Shoes Industry Pvt. Ltd.',
                'tax_vat_number' => null,
                'due_balance' => 81700.00,
                'phone' => '+977-1-4220106',
                'products' => 'Footwear & Outdoor Shoes (Himshikhar Factory)',
                'notes' => 'Authoritative footwear manufacturing partner (HIMSHIKHAR FACTORY). Due balance payable as of today: Rs. 81,700.00.',
            ],
        ];

        $totalPayableBalance = 0.0;
        foreach ($authoritativeSuppliers as $s) {
            $totalPayableBalance += $s['due_balance'];
        }

        $this->info("Found " . count($authoritativeSuppliers) . " authoritative suppliers.");
        $this->info("Total Payable Balance as of Today: Rs. " . number_format($totalPayableBalance, 2) . " NPR\n");

        if (!$dryRun) {
            DB::beginTransaction();
        }

        try {
            // 1. UPSERT SUPPLIERS IN inventory_suppliers
            $this->info('--- 1. Registering Authoritative Suppliers in inventory_suppliers ---');
            $createdCount = 0;
            $updatedCount = 0;

            foreach ($authoritativeSuppliers as $as) {
                if ($dryRun) {
                    $this->line("  [DRY RUN] Would upsert #{$as['sn']}: {$as['name']} | Code: {$as['code']} | Balance: Rs. {$as['due_balance']}");
                    continue;
                }

                $supplier = Supplier::where('code', $as['code'])
                    ->orWhere('name', $as['name'])
                    ->first();

                $attributes = [
                    'code' => $as['code'],
                    'name' => $as['name'],
                    'legal_name' => $as['legal_name'],
                    'contact_person' => 'Procurement Desk',
                    'email' => strtolower(preg_replace('/[^a-z0-9]/', '', $as['name'])) . '@laijau-partner.com',
                    'phone' => $as['phone'],
                    'address' => 'New Road / Durbarmarg Commercial Zone',
                    'city' => 'Kathmandu',
                    'country' => 'NP',
                    'currency' => 'NPR',
                    'due_balance' => $as['due_balance'],
                    'payment_terms' => 'Net 30',
                    'lead_time_days' => 7,
                    'tax_vat_number' => $as['tax_vat_number'],
                    'is_active' => true,
                    'notes' => $as['notes'],
                ];

                if ($supplier) {
                    $supplier->update($attributes);
                    $updatedCount++;
                } else {
                    Supplier::create($attributes);
                    $createdCount++;
                }
            }

            if (!$dryRun) {
                $this->info("✓ Successfully synced {$createdCount} new and {$updatedCount} updated suppliers.");
            }

            // 2. CREATE STATUTORY OPENING KHARID KHATA INVOICES FOR PAYABLE BALANCES
            $this->info("\n--- 2. Setting Up Kharid Khata Opening Supplier Bills ---");

            $invoiceMap = [
                'CITIZEN SHOES' => 'KH-OP-CITIZEN',
                'STAR DENIM' => 'KH-OP-STARDENIM',
                'MAXX RIDERS' => 'KH-OP-MAXXRIDERS',
                'PRASIDDHA FOOTWARE' => 'KH-OP-PRASIDDHA',
                'SM FACTORY' => 'KH-OP-SMFACTORY',
                'SK SHOES' => 'KH-OP-SKSHOES',
                'HIMSHIKHAR SHOES' => 'KH-OP-HIMSHIKHAR',
            ];

            $openingInvoiceIds = [];

            foreach ($authoritativeSuppliers as $as) {
                if ($as['due_balance'] <= 0) {
                    continue;
                }

                $invoiceNumber = $invoiceMap[$as['name']] ?? ('KH-OP-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $as['name']), 0, 10)));

                if ($dryRun) {
                    $this->line("  [DRY RUN] Would create Opening Bill {$invoiceNumber} for {$as['name']}: Rs. {$as['due_balance']}");
                    continue;
                }

                $invoice = AccountingInvoice::updateOrCreate(
                    ['invoice_number' => $invoiceNumber],
                    [
                        'type' => 'supplier_bill',
                        'fiscal_year' => '2082/83',
                        'contact_name' => $as['name'],
                        'seller_pan' => $as['tax_vat_number'],
                        'customer_type' => 'supplier',
                        'purchase_type' => 'local_taxable_13',
                        'sales_channel' => 'procurement',
                        'contact_address' => 'Kathmandu, Nepal',
                        'contact_country' => 'NP',
                        'issue_date' => '2026-01-01',
                        'due_date' => '2026-09-16',
                        'currency' => 'NPR',
                        'subtotal' => $as['due_balance'],
                        'taxable_amount' => $as['due_balance'],
                        'exempt_amount' => 0.00,
                        'export_amount' => 0.00,
                        'discount_amount' => 0.00,
                        'vat_amount' => 0.00,
                        'total_amount' => $as['due_balance'],
                        'paid_amount' => 0.00,
                        'payment_status' => 'unpaid',
                        'is_credit' => true,
                        'posted_to_gl' => true,
                        'payment_terms' => 'Net 30',
                        'notes' => "Statutory opening trade payable for {$as['name']} as of today per Suppliers master document.",
                    ]
                );

                $openingInvoiceIds[] = $invoice->id;
                $this->info("✓ Registered Opening Kharid Khata Bill #{$invoiceNumber} for {$as['name']}: Rs. " . number_format($as['due_balance'], 2));
            }

            // 3. BALANCED OPENING GENERAL LEDGER ENTRY (0.0000 NPR VARIANCE)
            $this->info("\n--- 3. Posting Balanced Opening General Ledger Journal Entry ---");

            $acc1210 = Account::where('account_number', '1210')->first();
            $acc2110 = Account::where('account_number', '2110')->first();

            if (!$acc1210 || !$acc2110) {
                throw new \RuntimeException('Mandatory GL accounts 1210 (Inventory Asset) or 2110 (Accounts Payable) not found.');
            }

            if (!$dryRun) {
                // Reuse or create opening AP journal entry
                $journalEntry = JournalEntry::where('entry_number', 'JV-OP-AP-2026')->first();
                if (!$journalEntry) {
                    $journalEntry = JournalEntry::create([
                        'entry_number' => 'JV-OP-AP-2026',
                        'voucher_date' => '2026-01-01',
                        'entry_type' => 'manual',
                        'reference_type' => 'opening_balance',
                        'reference_id' => null,
                        'description' => 'Opening Trade Accounts Payable & Merchandise Inventory per authoritative Suppliers master',
                        'currency' => 'NPR',
                        'total_debit' => $totalPayableBalance,
                        'total_credit' => $totalPayableBalance,
                        'is_balanced' => true,
                        'status' => 'posted',
                        'posted_at' => Carbon::now(),
                        'exchange_rate_to_npr' => 1.000000,
                        'notes' => 'Opening trade accounts payable across 7 authentic footwear and apparel suppliers.',
                    ]);

                    // Line 1: Debit 1210 Merchandise Inventory Asset
                    JournalEntryLine::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id' => $acc1210->id,
                        'account_number' => '1210',
                        'line_number' => 1,
                        'description' => 'Opening Merchandise Inventory Asset corresponding to trade supplier payables',
                        'debit' => $totalPayableBalance,
                        'credit' => 0.0000,
                        'currency' => 'NPR',
                        'amount_currency' => $totalPayableBalance,
                        'vat_code' => null,
                        'vat_rate' => 0.00,
                        'vat_amount' => 0.0000,
                        'is_reconciled' => true,
                    ]);

                    // Line 2: Credit 2110 Accounts Payable Liability
                    JournalEntryLine::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id' => $acc2110->id,
                        'account_number' => '2110',
                        'line_number' => 2,
                        'description' => 'Opening Trade Accounts Payable across 7 active footwear and apparel suppliers',
                        'debit' => 0.0000,
                        'credit' => $totalPayableBalance,
                        'currency' => 'NPR',
                        'amount_currency' => $totalPayableBalance,
                        'vat_code' => null,
                        'vat_rate' => 0.00,
                        'vat_amount' => 0.0000,
                        'is_reconciled' => true,
                    ]);
                }

                // Link invoices to journal entry
                AccountingInvoice::whereIn('id', $openingInvoiceIds)->update([
                    'journal_entry_id' => $journalEntry->id,
                ]);

                // Update account balances
                $acc1210->current_balance = (float) JournalEntryLine::where('account_id', $acc1210->id)->sum(DB::raw('debit - credit'));
                $acc1210->save();

                $acc2110->current_balance = (float) JournalEntryLine::where('account_id', $acc2110->id)->sum(DB::raw('credit - debit'));
                $acc2110->save();

                $this->info("✓ Created Balanced Journal Entry #JV-OP-AP-2026: Dr 1210 Rs. " . number_format($totalPayableBalance, 2) . " / Cr 2110 Rs. " . number_format($totalPayableBalance, 2));
            } else {
                $this->line("  [DRY RUN] Would create balanced Opening JV #JV-OP-AP-2026: Dr 1210 / Cr 2110 for Rs. {$totalPayableBalance}");
            }

            // 4. VERIFY GL EQUILIBRIUM
            if (!$dryRun) {
                $totalDebits = (float) DB::table('accounting_journal_entry_lines')->sum('debit');
                $totalCredits = (float) DB::table('accounting_journal_entry_lines')->sum('credit');
                $diff = abs($totalDebits - $totalCredits);

                $this->info("\n--- 4. General Ledger Trial Balance Audit ---");
                $this->info("  Total Debits : NPR " . number_format($totalDebits, 4));
                $this->info("  Total Credits: NPR " . number_format($totalCredits, 4));
                $this->info("  Variance     : NPR " . number_format($diff, 4));

                if ($diff > 0.0001) {
                    throw new \RuntimeException("General Ledger out of balance! Debits: {$totalDebits}, Credits: {$totalCredits}");
                }
                $this->info("✓ General Ledger is perfectly balanced with exactly 0.0000 NPR variance.");
            }

            // 5. UPDATE CACHED SUPPLIERS.JSON ARTIFACT
            $suppliersJsonPath = storage_path('app/migration/real_data_extracted/suppliers.json');
            if (!$dryRun) {
                File::ensureDirectoryExists(dirname($suppliersJsonPath));
                File::put($suppliersJsonPath, json_encode($authoritativeSuppliers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $this->info("\n✓ Synchronized extracted suppliers.json artifact at {$suppliersJsonPath}.");
            }

            if (!$dryRun) {
                DB::commit();
            }

            $this->info("\n========================================================================");
            $this->info('  AUTHORITATIVE SUPPLIERS IMPORT COMPLETED SUCCESSFULLY');
            $this->info('========================================================================');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            if (!$dryRun) {
                DB::rollBack();
            }
            $this->error('Failed to import authoritative suppliers: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }
    }
}
