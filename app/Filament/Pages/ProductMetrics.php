<?php

namespace App\Filament\Pages;

use App\Filament\Pages\OfflineSales\Concerns\InteractsWithPosSession;
use App\Models\Setting;
use App\Services\OfflineSaleService;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;

class ProductMetrics extends Page
{
    use InteractsWithPosSession;

    protected string $view = 'filament.pages.offline-sales.metrics-page';
    protected Width | string | null $maxWidth = 'full';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static string | \UnitEnum | null $navigationGroup = 'Sales';
    protected static ?string $navigationLabel = 'Product Metrics';
    protected static ?int $navigationSort = 13;
    protected static ?string $title = 'Product Metrics';
    protected static ?string $slug = 'offline-sales/metrics';

    public static function getRelativeRouteName(\Filament\Panel $panel): string
    {
        return 'offline-sales.metrics';
    }

    public function getHeading(): string | Htmlable
    {
        return '';
    }

    public string $activeTab = 'performance';
    public string $currency = 'NPR';

    public function mount(): void
    {
        $this->currency = Setting::get('default_currency', 'NPR');
        $this->initPosSession();
    }

    public function getProductPerformanceDataProperty(): array
    {
        return app(OfflineSaleService::class)->getProductPerformance();
    }

    public function closeModal(): void
    {
        $this->activeModal = null;
    }
}
