<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Accounting\AccountingFiscalYear;
use App\Models\Accounting\AccountingPeriod;
use App\Models\Accounting\VatDeclaration;
use App\Services\Accounting\AccountingService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VatReportingPage extends Page
{
    protected string $view = 'filament.pages.vat-reporting-page';
    protected Width | string | null $maxWidth = 'full';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-scale';
    protected static string | \UnitEnum | null $navigationGroup = 'Finance';
    protected static ?string $navigationLabel = 'Nepal VAT Return';
    protected static ?int $navigationSort = 90;
    protected static ?string $title = 'Nepal Inland Revenue Department (IRD) Anusuchi 10 VAT Return';
    protected static ?string $slug = 'vat-reporting';

    public function getHeading(): string | Htmlable
    {
        return '';
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user() ?? auth('web')->user();
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        return $user->hasRole('Accountant', 'admin')
            || $user->hasRole('Accountant', 'web')
            || $user->hasRole('Workspace Admin', 'admin')
            || $user->hasRole('Workspace Admin', 'web')
            || $user->can('page_VatReportingPage')
            || $user->role === 'accountant';
    }

    public string $selectedFiscalYear = '2083/84';
    public string $selectedPeriod = ''; // empty string = full FY
    public string $filingStatus = 'Draft'; // 'Draft' | 'Reviewed' | 'Filed'
    public ?string $drillDownType = null; // 'sales' | 'purchases' | null

    public function mount(): void
    {
        $currentFy = AccountingFiscalYear::getCurrent();
        if ($currentFy) {
            $this->selectedFiscalYear = $currentFy->fiscal_year;
        }
    }

    public function getStatutoryReturnProperty(): array
    {
        return app(AccountingService::class)->generateStatutoryNepalVatReturn(
            $this->selectedFiscalYear,
            $this->selectedPeriod ?: null
        );
    }

    public function getAvailablePeriodsProperty()
    {
        return AccountingPeriod::where('fiscal_year', $this->selectedFiscalYear)
            ->whereNotNull('nepali_month')
            ->orderBy('nepali_month')
            ->get();
    }

    public function openDrillDown(string $type): void
    {
        $this->drillDownType = $type;
    }

    public function closeDrillDown(): void
    {
        $this->drillDownType = null;
    }

    public function markReviewed(): void
    {
        $this->filingStatus = 'Reviewed';
        Notification::make()
            ->title('VAT Return Marked as Reviewed')
            ->body("Fiscal Period {$this->selectedFiscalYear} reviewed by Internal Auditor.")
            ->success()
            ->send();
    }

    public function markFiled(): void
    {
        $this->filingStatus = 'Filed';
        $vat = $this->statutoryReturn;

        VatDeclaration::create([
            'declaration_number' => VatDeclaration::generateNextDeclarationNumber('nepal_vat', date('Y-m-d')),
            'period_name' => ($this->selectedPeriod ?: 'Full Year') . " ({$this->selectedFiscalYear})",
            'start_date' => date('Y-m-d'),
            'end_date' => date('Y-m-d'),
            'declaration_type' => 'nepal_vat',
            'output_vat_13' => $vat['sales']['output_vat'],
            'input_vat_13' => $vat['purchases']['input_vat'],
            'net_vat_position' => $vat['reconciliation']['net_vat_position'],
            'taxable_sales_13' => $vat['sales']['taxable_sales_13'],
            'taxable_purchases_13' => $vat['purchases']['taxable_purchases_13'],
            'export_sales_0' => $vat['sales']['export_sales_0'],
            'exempt_sales' => $vat['sales']['exempt_sales'],
            'status' => 'submitted',
            'submitted_at' => now(),
            'notes' => "Statutory Nepal IRD Anusuchi 10 VAT Return successfully filed for {$this->selectedFiscalYear}.",
        ]);

        Notification::make()
            ->title('Statutory VAT Return Filed')
            ->body("Official IRD Anusuchi 10 record archived and locked into audit ledger.")
            ->success()
            ->send();
    }

    public function exportAnusuchi10Csv(): StreamedResponse
    {
        $vat = $this->statutoryReturn;
        $fy = str_replace('/', '_', $this->selectedFiscalYear);
        $fileName = "Nepal_IRD_Anusuchi_10_VAT_Return_{$fy}.csv";

        return response()->streamDownload(function () use ($vat) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['नेपाल सरकार - आन्तरिक राजस्व विभाग (IRD Nepal)']);
            fputcsv($handle, ['मूल्य अभिवृद्धि कर विवरण (अनुसूची १० - नियम २३ सँग सम्बन्धित)']);
            fputcsv($handle, ['करदाताको नाम:', $vat['company_name'], 'PAN:', $vat['company_pan']]);
            fputcsv($handle, ['आर्थिक वर्ष (Fiscal Year):', $vat['fiscal_year'], 'अवधि (Period):', $vat['period_name']]);
            fputcsv($handle, []);

            fputcsv($handle, ['१. बिक्री विवरण (Sales Particulars)', 'रकम (करयोग्य)', '१३% मूल्य अभिवृद्धि कर']);
            fputcsv($handle, ['करयोग्य बिक्री (Taxable Sales 13%)', number_format($vat['sales']['taxable_sales_13'], 2), number_format($vat['sales']['output_vat'], 2)]);
            fputcsv($handle, ['निकासी बिक्री (Zero-Rated / Export Sales)', number_format($vat['sales']['export_sales_0'], 2), '0.00']);
            fputcsv($handle, ['कर छुट हुने बिक्री (Exempt Sales)', number_format($vat['sales']['exempt_sales'], 2), '0.00']);
            fputcsv($handle, ['कुल बिक्री (Gross Total Sales)', number_format($vat['sales']['total_sales'], 2), number_format($vat['sales']['output_vat'], 2)]);
            fputcsv($handle, []);

            fputcsv($handle, ['२. खरिद तथा पैठारी विवरण (Purchases & Imports)', 'रकम (करयोग्य)', '१३% कट्टी दाबी कर (Input Credit)']);
            fputcsv($handle, ['स्वदेशी करयोग्य खरिद (Local Taxable Purchases)', number_format($vat['purchases']['taxable_purchases_13'], 2), number_format($vat['purchases']['input_vat'], 2)]);
            fputcsv($handle, ['पैठारी (Taxable Imports / Customs)', number_format($vat['purchases']['taxable_imports'], 2), '0.00']);
            fputcsv($handle, ['कर छुट हुने खरिद (Exempt Purchases)', number_format($vat['purchases']['exempt_purchases'], 2), '0.00']);
            fputcsv($handle, ['कुल खरिद (Gross Total Purchases)', number_format($vat['purchases']['total_purchases'], 2), number_format($vat['purchases']['input_vat'], 2)]);
            fputcsv($handle, []);

            fputcsv($handle, ['३. कर मिलान तथा खुद तिर्नुपर्ने / फिर्ता पाउने (Reconciliation & Net VAT Position)']);
            fputcsv($handle, ['उठाएको मूल्य अभिवृद्धि कर (Output VAT Collected):', number_format($vat['reconciliation']['output_vat'], 2)]);
            fputcsv($handle, ['घटाउन पाउने कर (Deductible Input VAT Credit):', number_format($vat['reconciliation']['input_vat'], 2)]);
            fputcsv($handle, ['खुद मूल्य अभिवृद्धि कर (Net VAT Position):', number_format($vat['reconciliation']['net_vat_position'], 2)]);
            fputcsv($handle, ['राजस्व खातामा दाखिला गर्नुपर्ने (Net Payable to IRD):', number_format($vat['reconciliation']['payable_to_ird'], 2)]);
            fputcsv($handle, ['अर्को महिनामा सार्ने कर (VAT Credit Carried Forward):', number_format($vat['reconciliation']['vat_credit_carried_forward'], 2)]);

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=utf-8',
        ]);
    }
}
