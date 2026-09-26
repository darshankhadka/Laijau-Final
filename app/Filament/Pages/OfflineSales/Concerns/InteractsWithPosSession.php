<?php

namespace App\Filament\Pages\OfflineSales\Concerns;

use App\Models\Hardware\PosStation;
use App\Models\Inventory\Warehouse;
use App\Models\Pos\PosSession;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Pos\PosSessionService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

trait InteractsWithPosSession
{
    // POS Terminal, Showroom & Daily Shift Session State
    public int $selectedStationId = 1; // Default to POS Terminal 1 (Showroom 1)
    public ?int $selectedWarehouseId = 44; // Default to Showroom 1 (Laijau Showroom)
    public array $warehousesList = [];

    // Mandatory Daily Session Float & Closing State
    public float $openingCashInput = 0.00;
    public string $openingNotesInput = '';
    public float $closingCashInput = 0.00;
    public string $closingNotesInput = '';
    public string $closingManagerInput = '';
    public ?int $closingSessionId = null;

    // Denomination breakdown for opening count (Rs. 1000, 500, 100, 50, 20, 10, 5, coins)
    public array $openingDenominations = [
        '1000' => '',
        '500'  => '',
        '100'  => '',
        '50'   => '',
        '20'   => '',
        '10'   => '',
        '5'    => '',
        'coins' => '',
    ];

    // Denomination breakdown for closing count (Rs. 1000, 500, 100, 50, 20, 10, 5, coins)
    public array $closingDenominations = [
        '1000' => '',
        '500'  => '',
        '100'  => '',
        '50'   => '',
        '20'   => '',
        '10'   => '',
        '5'    => '',
        'coins' => '',
    ];

    // Mandatory Shortage / Discrepancy Reason State
    public string $varianceReasonCode = '';
    public string $varianceReasonText = '';

    // Active Modal state
    public ?string $activeModal = null;

    public function closeModal(): void
    {
        $this->activeModal = null;
    }

    public static function routes(\Filament\Panel $panel, ?\Filament\Pages\PageConfiguration $configuration = null): void
    {
        parent::routes($panel, $configuration);

        try {
            $routes = \Illuminate\Support\Facades\Route::getRoutes();
            $target = $routes->getByName(static::getRouteName($panel));
            if ($target) {
                $cleanName = 'intadmin.' . static::getRelativeRouteName($panel);
                $ref = new \ReflectionProperty($routes, 'nameList');
                $ref->setAccessible(true);
                $nameList = $ref->getValue($routes);
                $nameList[$cleanName] = $target;
                $ref->setValue($routes, $nameList);
            }
        } catch (\Throwable $e) {
            // Ignore reflection issues
        }
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

        return $user->hasRole('Cashier', 'admin')
            || $user->hasRole('Cashier', 'web')
            || $user->hasRole('Store Manager', 'admin')
            || $user->hasRole('Store Manager', 'web')
            || $user->hasRole('Operations Manager', 'admin')
            || $user->hasRole('Operations Manager', 'web')
            || $user->hasRole('Sales Representative', 'admin')
            || $user->hasRole('Sales Representative', 'web')
            || $user->hasRole('Executive', 'admin')
            || $user->hasRole('Executive', 'web')
            || $user->hasRole('Accountant', 'admin')
            || $user->hasRole('Accountant', 'web')
            || $user->hasRole('Workspace Admin', 'admin')
            || $user->hasRole('Workspace Admin', 'web')
            || $user->can('page_OfflineSales')
            || in_array($user->role, ['cashier', 'store_manager', 'operations_manager', 'sales_rep', 'sales', 'executive', 'accountant', 'admin'], true);
    }

    public function initPosSession(): void
    {
        $savedStationId = session('pos_selected_station_id');
        $station = null;
        if ($savedStationId) {
            $station = PosStation::where('is_active', true)->find((int)$savedStationId);
        }

        if (!$station) {
            $station = PosStation::where('is_active', true)->find(1)
                ?? PosStation::where('is_active', true)->first();
            $this->selectedStationId = $station ? $station->id : 1;
        } else {
            $this->selectedStationId = $station->id;
        }

        $this->selectedWarehouseId = $station?->warehouse_id ?? 43;

        // Check terminal session state on load
        $sessionService = app(PosSessionService::class);
        $state = $sessionService->getTerminalSessionState($this->selectedStationId);

        if ($state['state'] === 'closing_required') {
            $this->closingSessionId = $state['session']?->id;
            $this->activeModal = 'close_session';
        } elseif ($state['state'] === 'active') {
            $this->activeModal = null;
        } elseif (!$savedStationId) {
            $this->activeModal = 'select_showroom';
        } else {
            $this->activeModal = 'open_session';
        }
    }

    public function setWarehouse(int $whId): void
    {
        $wh = Warehouse::find($whId);
        if ($wh) {
            $this->selectedWarehouseId = $wh->id;

            if (property_exists($this, 'cart') && is_array($this->cart)) {
                foreach ($this->cart as $key => $item) {
                    $stock = $this->resolveAvailableStock((int)$item['product_id'], $item['variant_id'] ? (int)$item['variant_id'] : null);
                    $this->cart[$key]['stock'] = $stock;
                }
                if (method_exists($this, 'recalculateTotals')) {
                    $this->recalculateTotals();
                }
            }

            Notification::make()
                ->title('Showroom / Warehouse Selected')
                ->body("Active location: {$wh->name} ({$wh->code})")
                ->info()
                ->duration(1500)
                ->send();
        }
    }

    public function setTerminal(int $stationId): void
    {
        $station = PosStation::where('is_active', true)->find($stationId);
        if (!$station) {
            Notification::make()->title('Terminal not found or inactive')->danger()->send();
            return;
        }

        $this->selectedStationId = $station->id;
        session(['pos_selected_station_id' => $station->id]);
        if ($station->warehouse_id) {
            $this->selectedWarehouseId = $station->warehouse_id;
        }

        if (property_exists($this, 'cart') && is_array($this->cart)) {
            foreach ($this->cart as $key => $item) {
                $stock = $this->resolveAvailableStock((int)$item['product_id'], $item['variant_id'] ? (int)$item['variant_id'] : null);
                $this->cart[$key]['stock'] = $stock;
            }
            if (method_exists($this, 'recalculateTotals')) {
                $this->recalculateTotals();
            }
        }

        $sessionService = app(PosSessionService::class);
        $state = $sessionService->getTerminalSessionState($this->selectedStationId);

        if ($state['state'] === 'closing_required') {
            $this->closingSessionId = $state['session']?->id;
            $this->activeModal = 'close_session';
            Notification::make()
                ->title('Overdue Closing Required!')
                ->body("{$station->name} has an unclosed session from {$state['session']?->business_date}. Please close it to proceed.")
                ->danger()
                ->send();
        } elseif ($state['state'] === 'opening_required') {
            $this->openingCashInput = 0.00;
            $this->openingNotesInput = '';
            $this->activeModal = 'open_session';
            Notification::make()
                ->title("Switched to {$station->location} · {$station->name}")
                ->body("Opening balance required for today's session (" . $sessionService->getNepalToday() . " NPT).")
                ->warning()
                ->send();
        } else {
            $this->activeModal = null;
            Notification::make()
                ->title("Switched to {$station->location} · {$station->name}")
                ->body("Today's session is active. Opening float: Rs. " . number_format($state['session']?->opening_balance ?? 0, 2))
                ->success()
                ->send();
        }
    }

    public function openSelectShowroomModal(): void
    {
        $this->activeModal = 'select_showroom';
    }

    public function selectShowroom(string|int $stationIdOrKey): void
    {
        if ($stationIdOrKey === 'new' || $stationIdOrKey == 1) {
            $stationId = 1; // Terminal 1 -> New Showroom
        } elseif ($stationIdOrKey === 'old' || $stationIdOrKey == 151) {
            $stationId = 151; // Terminal 2 -> Old Showroom
        } else {
            $stationId = (int)$stationIdOrKey;
        }

        $this->setTerminal($stationId);
    }

    public function resolveAvailableStock(int $productId, ?int $variantId = null): int
    {
        $defaultWh = app(\App\Services\Inventory\InventoryService::class)->getDefaultWarehouse();
        $sharedWhId = $defaultWh?->id ?? 43;

        $stockLevel = \App\Models\Inventory\StockLevel::where('warehouse_id', $sharedWhId)
            ->where('product_id', $productId)
            ->when($variantId, fn($q) => $q->where('variant_id', $variantId), fn($q) => $q->whereNull('variant_id'))
            ->first();

        if ($stockLevel) {
            return (int)($stockLevel->quantity_on_hand - $stockLevel->quantity_reserved);
        }

        if ($variantId) {
            return (int)(ProductVariant::where('id', $variantId)->value('stock_quantity') ?? 0);
        }
        return (int)(Product::where('id', $productId)->value('quantity') ?? 0);
    }

    public function openSessionModal(): void
    {
        $sessionService = app(PosSessionService::class);
        $state = $sessionService->getTerminalSessionState($this->selectedStationId);
        if ($state['state'] === 'closing_required') {
            $this->closingSessionId = $state['session']?->id;
            $this->activeModal = 'close_session';
        } else {
            $this->resetOpeningDenominations();
            $this->activeModal = 'open_session';
        }
    }

    public function updatedOpeningDenominations(): void
    {
        $this->calculateOpeningCashFromDenominations();
    }

    public function calculateOpeningCashFromDenominations(): void
    {
        $total = 0.0;
        $multiplier = [
            '1000' => 1000,
            '500'  => 500,
            '100'  => 100,
            '50'   => 50,
            '20'   => 20,
            '10'   => 10,
            '5'    => 5,
        ];

        foreach ($multiplier as $note => $val) {
            $qty = (int)($this->openingDenominations[$note] ?? 0);
            if ($qty > 0) {
                $total += $qty * $val;
            }
        }

        $coins = (float)($this->openingDenominations['coins'] ?? 0);
        if ($coins > 0) {
            $total += $coins;
        }

        $this->openingCashInput = round($total, 2);
    }

    public function resetOpeningDenominations(): void
    {
        $this->openingDenominations = [
            '1000' => '',
            '500'  => '',
            '100'  => '',
            '50'   => '',
            '20'   => '',
            '10'   => '',
            '5'    => '',
            'coins' => '',
        ];
        $this->openingCashInput = 0.00;
    }

    public function openCloseSessionModal(?int $sessionId = null): void
    {
        $this->resetErrorBag();
        $sessionService = app(PosSessionService::class);
        if ($sessionId) {
            $this->closingSessionId = $sessionId;
        } else {
            $state = $sessionService->getTerminalSessionState($this->selectedStationId);
            $this->closingSessionId = $state['session']?->id
                ?? PosSession::where('pos_station_id', $this->selectedStationId)
                    ->whereIn('status', ['open', 'closing_required'])
                    ->latest('id')
                    ->value('id')
                ?? PosSession::where('pos_station_id', $this->selectedStationId)->latest('id')->value('id');
        }
        $this->resetClosingDenominations();
        $this->activeModal = 'close_session';
    }

    public function updatedClosingDenominations(): void
    {
        $this->calculateClosingCashFromDenominations();
    }

    public function calculateClosingCashFromDenominations(): void
    {
        $total = 0.0;
        $multiplier = [
            '1000' => 1000,
            '500'  => 500,
            '100'  => 100,
            '50'   => 50,
            '20'   => 20,
            '10'   => 10,
            '5'    => 5,
        ];

        foreach ($multiplier as $note => $val) {
            $qty = (int)($this->closingDenominations[$note] ?? 0);
            if ($qty > 0) {
                $total += $qty * $val;
            }
        }

        $coins = (float)($this->closingDenominations['coins'] ?? 0);
        if ($coins > 0) {
            $total += $coins;
        }

        $this->closingCashInput = round($total, 2);
    }

    public function resetClosingDenominations(): void
    {
        $this->closingDenominations = [
            '1000' => '',
            '500'  => '',
            '100'  => '',
            '50'   => '',
            '20'   => '',
            '10'   => '',
            '5'    => '',
            'coins' => '',
        ];
        $this->closingCashInput = 0.00;
        $this->varianceReasonCode = '';
        $this->varianceReasonText = '';
        $this->resetErrorBag();
    }

    public function updatedOpeningCashInput(): void
    {
        $this->openingCashInput = max(0.0, round((float)$this->openingCashInput, 2));
    }

    public function updatedClosingCashInput(): void
    {
        $this->closingCashInput = max(0.0, round((float)$this->closingCashInput, 2));
    }

    public function matchExpectedClosingCash(): void
    {
        $details = $this->closingSessionDetails;
        $expected = (float)($details['calc']['expected_cash'] ?? 0);
        $this->closingCashInput = max(0.0, round($expected, 2));
        $this->varianceReasonCode = '';
        $this->varianceReasonText = '';
        $this->resetErrorBag();
    }

    public function addOpeningCashChip(float $amount): void
    {
        $this->openingCashInput = max(0.0, round($this->openingCashInput + $amount, 2));
    }

    public function resetOpeningCash(): void
    {
        $this->openingCashInput = 0.00;
        $this->resetOpeningDenominations();
    }

    public function addClosingCashChip(float $amount): void
    {
        $this->closingCashInput = max(0.0, round($this->closingCashInput + $amount, 2));
    }

    public function resetClosingCash(): void
    {
        $this->closingCashInput = 0.00;
        $this->resetClosingDenominations();
    }

    public function openDailySession(): void
    {
        $this->resetErrorBag();
        $sessionService = app(PosSessionService::class);
        $state = $sessionService->getTerminalSessionState($this->selectedStationId);

        if ($state['state'] === 'closing_required') {
            Notification::make()
                ->title('Previous Session Unclosed!')
                ->body("You must finalize closing for yesterday's session ({$state['session']?->business_date}) before opening today.")
                ->danger()
                ->send();
            $this->closingSessionId = $state['session']?->id;
            $this->activeModal = 'close_session';
            return;
        }

        if ($this->openingCashInput < 0) {
            $this->addError('open_session_error', 'Opening cash cannot be negative.');
            Notification::make()->title('Opening cash cannot be negative')->danger()->send();
            return;
        }

        try {
            $user = Auth::user() ?? auth('admin')->user() ?? auth('web')->user() ?? User::first();
            $session = $sessionService->openSession(
                $this->selectedStationId,
                (float)$this->openingCashInput,
                $user,
                $this->openingNotesInput ?: null
            );

            $this->activeModal = null;
            $this->openingCashInput = 0.00;
            $this->openingNotesInput = '';

            Notification::make()
                ->title("Session Opened: {$session->showroom_name} · {$session->terminal_name}")
                ->body("Business Date: {$session->business_date} (NPT). Opening Float: Rs. " . number_format($session->opening_balance, 2))
                ->success()
                ->send();
        } catch (\Throwable $e) {
            $this->addError('open_session_error', $e->getMessage());
            Notification::make()->title('Failed to open session')->body($e->getMessage())->danger()->send();
        }
    }

    public function closeDailySession(): void
    {
        $this->resetErrorBag();
        $sessionService = app(PosSessionService::class);
        $sessionId = $this->closingSessionId;

        if (!$sessionId) {
            $state = $sessionService->getTerminalSessionState($this->selectedStationId);
            $sessionId = $state['session']?->id;
        }

        if (!$sessionId) {
            $sess = PosSession::where('pos_station_id', $this->selectedStationId)
                ->whereIn('status', ['open', 'closing_required'])
                ->latest('id')
                ->first();
            $sessionId = $sess?->id;
        }

        if (!$sessionId) {
            $this->addError('close_session_error', 'No active or overdue session found to close.');
            Notification::make()->title('No active or overdue session found to close')->warning()->send();
            $this->activeModal = null;
            return;
        }

        $session = PosSession::find($sessionId);
        if (!$session || $session->status === 'closed') {
            $this->addError('close_session_error', 'This shift session is already closed or was not found.');
            Notification::make()->title('Session already closed or not found')->warning()->send();
            $this->activeModal = null;
            return;
        }

        // Recalculate variance for strict validation
        $financials = $sessionService->calculateSessionFinancials($session);
        $expectedCash = (float)$financials['expected_cash'];
        $countedCash = (float)$this->closingCashInput;
        $variance = round($countedCash - $expectedCash, 2);

        // Validation for Shortage (< 0)
        if ($variance < 0) {
            if (empty($this->varianceReasonCode)) {
                $this->addError('varianceReasonCode', 'Cash counted is short by Rs. ' . number_format(abs($variance), 2) . '. You must select a valid reason before closing.');
                Notification::make()
                    ->title('Shortage Reason Required')
                    ->body('Cash counted is short by Rs. ' . number_format(abs($variance), 2) . '. You must select a valid reason before closing.')
                    ->danger()
                    ->send();
                return;
            }
            if ($this->varianceReasonCode === 'other' && (empty($this->varianceReasonText) || strlen(trim($this->varianceReasonText)) < 4)) {
                $this->addError('varianceReasonText', 'Please provide a detailed explanation (minimum 4 characters) when selecting "Other" for shortage.');
                Notification::make()
                    ->title('Explanation Required')
                    ->body('Please provide a detailed explanation when selecting "Other" for shortage.')
                    ->danger()
                    ->send();
                return;
            }
        }

        // Validation for Overage (> 0)
        if ($variance > 0) {
            if (empty($this->varianceReasonCode) && empty($this->varianceReasonText) && empty($this->closingNotesInput)) {
                $this->addError('varianceReasonCode', 'Cash counted exceeds expected cash by Rs. ' . number_format($variance, 2) . '. Please provide an explanation before closing.');
                Notification::make()
                    ->title('Overage Explanation Required')
                    ->body('Cash counted exceeds expected cash by Rs. ' . number_format($variance, 2) . '. Please provide an explanation before closing.')
                    ->warning()
                    ->send();
                return;
            }
        }

        try {
            $user = Auth::user() ?? auth('admin')->user() ?? auth('web')->user() ?? User::first();

            // Build denominations map (only nonzero / entered)
            $denomData = [];
            foreach ($this->closingDenominations as $k => $v) {
                if ($v !== '' && $v !== null && (float)$v > 0) {
                    $denomData[$k] = (float)$v;
                }
            }

            $closedSession = $sessionService->closeSession(
                $session,
                $countedCash,
                $user,
                $this->closingNotesInput ?: null,
                $this->closingManagerInput ?: null,
                $denomData,
                $this->varianceReasonCode ?: null,
                $this->varianceReasonText ?: null
            );

            $this->activeModal = null;
            $this->resetClosingDenominations();
            $this->closingNotesInput = '';
            $this->closingManagerInput = '';
            $this->closingSessionId = null;

            $var = (float)$closedSession->cash_variance;
            $varStr = $var == 0
                ? 'Balanced (Rs. 0.00)'
                : ($var > 0
                    ? '+Rs. ' . number_format($var, 2) . ' Over'
                    : '-Rs. ' . number_format(abs($var), 2) . ' Short');

            Notification::make()
                ->title("Shift Session Closed!")
                ->body("Showroom: {$closedSession->showroom_name} ({$closedSession->terminal_name}). Expected: Rs. " . number_format($closedSession->expected_cash, 2) . " | Counted: Rs. " . number_format($closedSession->closing_cash_counted, 2) . " ({$varStr})")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            $this->addError('close_session_error', $e->getMessage());
            Notification::make()->title('Failed to close session')->body($e->getMessage())->danger()->send();
        }
    }

    public function getCurrentSessionSalesTotalProperty(): float
    {
        $sessionService = app(PosSessionService::class);
        $state = $sessionService->getTerminalSessionState($this->selectedStationId);
        $session = $state['session'];
        if (!$session) {
            return 0.00;
        }

        return (float) \App\Models\OfflineSale::where('pos_session_id', $session->id)
            ->where('status', '!=', 'voided')
            ->sum('total_amount');
    }

    public function getOperationalTerminalsProperty()
    {
        return app(PosSessionService::class)->getTerminals();
    }

    public function getActiveStationProperty(): ?PosStation
    {
        return PosStation::with('warehouse')->find($this->selectedStationId)
            ?? PosStation::first();
    }

    public function getSessionStateProperty(): array
    {
        return app(PosSessionService::class)->getTerminalSessionState($this->selectedStationId);
    }

    public function getTodaySessionProperty(): ?PosSession
    {
        return app(PosSessionService::class)->getActiveSession($this->selectedStationId);
    }

    public function getOverdueSessionProperty(): ?PosSession
    {
        return app(PosSessionService::class)->getOverdueUnclosedSession($this->selectedStationId);
    }

    public function getAllTerminalsStatusProperty(): array
    {
        $terminals = app(PosSessionService::class)->getTerminals();
        $res = [];
        foreach ($terminals as $t) {
            $state = app(PosSessionService::class)->getTerminalSessionState($t->id);
            $res[] = [
                'terminal' => $t,
                'state' => $state['state'],
                'can_sell' => $state['can_sell'],
                'session' => $state['session'],
                'overdue_session' => $state['overdue_session'] ?? null,
            ];
        }
        return $res;
    }

    public function getClosingSessionDetailsProperty(): ?array
    {
        $sessionId = $this->closingSessionId;
        if (!$sessionId) {
            $stationId = (property_exists($this, 'dashboardStationId') && $this->dashboardStationId) ? $this->dashboardStationId : $this->selectedStationId;
            $state = app(PosSessionService::class)->getTerminalSessionState($stationId);
            $sessionId = $state['session']?->id
                ?? PosSession::where('pos_station_id', $stationId)->latest('id')->value('id');
        }
        if (!$sessionId) {
            return null;
        }

        $session = PosSession::with(['station', 'showroom', 'openedBy'])->find($sessionId);
        if (!$session) {
            return null;
        }

        $calc = app(PosSessionService::class)->calculateSessionFinancials($session);

        return [
            'session' => $session,
            'calc' => $calc,
        ];
    }

    public function canViewFinancialMargins(): bool
    {
        $user = auth('admin')->user() ?? auth('web')->user() ?? Auth::user();
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        return $user->hasRole('Store Manager', 'admin')
            || $user->hasRole('Store Manager', 'web')
            || $user->hasRole('Workspace Admin', 'admin')
            || $user->hasRole('Workspace Admin', 'web')
            || in_array($user->role, ['store_manager', 'admin', 'super_admin']);
    }
}
