<?php

namespace App\Filament\Pages;

use App\Filament\Pages\OfflineSales\Concerns\InteractsWithPosSession;
use App\Models\OfflineSale;
use App\Models\Setting;
use App\Services\OfflineSaleService;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;

class PosDashboard extends Page
{
    use InteractsWithPosSession;

    protected string $view = 'filament.pages.offline-sales.dashboard-page';
    protected Width | string | null $maxWidth = 'full';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar';
    protected static string | \UnitEnum | null $navigationGroup = 'Sales';
    protected static ?string $navigationLabel = 'POS Dashboard';
    protected static ?int $navigationSort = 12;
    protected static ?string $title = 'POS Dashboard';
    protected static ?string $slug = 'offline-sales/dashboard';

    public static function getRelativeRouteName(\Filament\Panel $panel): string
    {
        return 'offline-sales.dashboard';
    }

    public function getHeading(): string | Htmlable
    {
        return '';
    }

    public string $activeTab = 'dashboard';
    public string $currency = 'NPR';

    // Upgraded POS Dashboard Analytics Filter State
    public string $dashboardPeriod = 'today'; // 'today' | 'yesterday' | 'week' | 'month' | 'last_month' | 'year' | 'all' | 'custom'
    public ?string $dashboardStartDate = null;
    public ?string $dashboardEndDate = null;
    public ?string $dashboardPaymentMethod = null;
    public ?string $dashboardStaffName = null;
    public ?int $dashboardWarehouseId = null;
    public ?int $dashboardStationId = null;

    public function mount(): void
    {
        $this->currency = Setting::get('default_currency', 'NPR');
        $this->initPosSession();
    }

    public function getDashboardDataProperty(): array
    {
        $targetStationId = $this->dashboardStationId ?? $this->selectedStationId;

        // If shift report modal is active, report specifically for the selected session/station
        $targetSessionId = null;
        if ($this->activeModal === 'shift_report') {
            $targetSessionId = $this->todaySession?->id ?? $this->closingSessionId;
        } elseif ($this->dashboardPeriod === 'today' && $targetStationId) {
            $targetSessionId = $this->todaySession?->id;
        }

        return app(OfflineSaleService::class)->getDashboardMetrics([
            'period' => $this->dashboardPeriod,
            'start_date' => $this->dashboardStartDate,
            'end_date' => $this->dashboardEndDate,
            'payment_method' => $this->dashboardPaymentMethod,
            'staff_name' => $this->dashboardStaffName,
            'warehouse_id' => $this->dashboardWarehouseId,
            'pos_station_id' => $targetStationId,
            'pos_session_id' => $targetSessionId,
        ]);
    }

    public function setDashboardPeriod(string $period): void
    {
        $this->dashboardPeriod = $period;
        if ($period !== 'custom') {
            $this->dashboardStartDate = null;
            $this->dashboardEndDate = null;
        }
    }

    public function resetDashboardFilters(): void
    {
        $this->dashboardPeriod = 'today';
        $this->dashboardStartDate = null;
        $this->dashboardEndDate = null;
        $this->dashboardPaymentMethod = null;
        $this->dashboardStaffName = null;
        $this->dashboardWarehouseId = null;
        $this->dashboardStationId = null;
    }

    public function getDashboardStaffListProperty(): array
    {
        return OfflineSale::whereNotNull('staff_name')
            ->where('staff_name', '!=', '')
            ->distinct()
            ->orderBy('staff_name')
            ->pluck('staff_name')
            ->toArray();
    }

    public function openShiftReportModal(?int $sessionId = null): void
    {
        if ($sessionId) {
            $this->closingSessionId = $sessionId;
        } else {
            $targetStationId = $this->dashboardStationId ?? $this->selectedStationId;
            $state = app(\App\Services\Pos\PosSessionService::class)->getTerminalSessionState($targetStationId);
            $this->closingSessionId = $state['session']?->id;
        }
        $this->activeModal = 'shift_report';
    }

    public function closeModal(): void
    {
        $this->activeModal = null;
    }
}
