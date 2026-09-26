<?php

namespace App\Services\Pos;

use App\Models\Hardware\PosStation;
use App\Models\Inventory\Warehouse;
use App\Models\OfflineSale;
use App\Models\OfflineSaleItem;
use App\Models\Pos\PosSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class PosSessionService
{
    /**
     * Get current business date in Nepal Time (Asia/Kathmandu).
     */
    public function getNepalToday(): string
    {
        return Carbon::now('Asia/Kathmandu')->toDateString();
    }

    /**
     * Get current Carbon instance in Nepal Time (Asia/Kathmandu).
     */
    public function getNepalNow(): Carbon
    {
        return Carbon::now('Asia/Kathmandu');
    }

    /**
     * Get all active operational terminals.
     */
    public function getTerminals(): Collection
    {
        return PosStation::with('warehouse')
            ->where('is_active', true)
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Get terminal by ID.
     */
    public function getTerminal(int $posStationId): ?PosStation
    {
        return PosStation::with('warehouse')->find($posStationId);
    }

    /**
     * Get the active (open or closing_required) session for a terminal on a specific business date (defaults to today).
     */
    public function getActiveSession(int $posStationId, ?string $businessDate = null): ?PosSession
    {
        $date = $businessDate ?: $this->getNepalToday();

        return PosSession::where('pos_station_id', $posStationId)
            ->where('business_date', $date)
            ->whereIn('status', ['open', 'closing_required'])
            ->latest('id')
            ->first();
    }

    /**
     * Get the latest session for a terminal on a specific business date regardless of status.
     */
    public function getLatestSession(int $posStationId, ?string $businessDate = null): ?PosSession
    {
        $date = $businessDate ?: $this->getNepalToday();

        return PosSession::where('pos_station_id', $posStationId)
            ->where('business_date', $date)
            ->latest('id')
            ->first();
    }

    /**
     * Detect if there is any unclosed session from yesterday or an earlier date.
     * Automatically sets status to 'closing_required' if found open.
     */
    public function getOverdueUnclosedSession(int $posStationId, ?string $currentBusinessDate = null): ?PosSession
    {
        $today = $currentBusinessDate ?: $this->getNepalToday();

        $overdue = PosSession::where('pos_station_id', $posStationId)
            ->where('business_date', '<', $today)
            ->where('status', '!=', 'closed')
            ->orderBy('business_date', 'asc')
            ->first();

        if ($overdue && $overdue->status === 'open') {
            $overdue->update(['status' => 'closing_required']);
        }

        return $overdue;
    }

    /**
     * Get the complete operational state for a terminal.
     */
    public function getTerminalSessionState(int $posStationId): array
    {
        $terminal = $this->getTerminal($posStationId);
        if (!$terminal) {
            $today = $this->getNepalToday();
            return [
                'terminal' => null,
                'state' => 'terminal_not_found',
                'can_sell' => false,
                'message' => 'POS Terminal not found or inactive.',
                'today' => $today,
                'business_date' => is_string($today) ? $today : $today->toDateString(),
                'session' => null,
                'overdue_session' => null,
                'today_session' => null,
            ];
        }

        $today = $this->getNepalToday();
        $todayStr = is_string($today) ? $today : $today->toDateString();
        $overdue = $this->getOverdueUnclosedSession($posStationId, $todayStr);
        $activeSession = $this->getActiveSession($posStationId, $todayStr);
        $latestSession = $this->getLatestSession($posStationId, $todayStr);

        if ($overdue) {
            return [
                'terminal' => $terminal,
                'state' => 'closing_required',
                'can_sell' => false,
                'message' => "Unclosed session detected for {$overdue->business_date->format('M d, Y')}. You must finalize closing before opening a new session.",
                'today' => $today,
                'business_date' => $todayStr,
                'session' => $overdue,
                'overdue_session' => $overdue,
                'today_session' => $activeSession ?? $latestSession,
            ];
        }

        if ($activeSession) {
            return [
                'terminal' => $terminal,
                'state' => 'active',
                'can_sell' => true,
                'message' => 'POS Session active and ready for retail checkout.',
                'today' => $today,
                'business_date' => $todayStr,
                'session' => $activeSession,
                'overdue_session' => null,
                'today_session' => $activeSession,
            ];
        }

        return [
            'terminal' => $terminal,
            'state' => 'opening_required',
            'can_sell' => false,
            'message' => $latestSession ? 'Previous shift has closed. Open a new shift to begin sales.' : 'No active session for today. Cashier must enter opening balance to begin sales.',
            'today' => $today,
            'business_date' => $todayStr,
            'session' => $latestSession,
            'overdue_session' => null,
            'today_session' => $latestSession,
        ];
    }

    /**
     * Open a new daily POS session with mandatory opening cash balance.
     */
    public function openSession(int $posStationId, float $openingBalance, User $user, ?string $openingNotes = null): PosSession
    {
        $terminal = $this->getTerminal($posStationId);
        if (!$terminal) {
            throw new InvalidArgumentException("Invalid POS Station ID: {$posStationId}");
        }

        $today = $this->getNepalToday();

        // 1. Safety Check: Verify no unclosed overdue session exists
        $overdue = $this->getOverdueUnclosedSession($posStationId, $today);
        if ($overdue) {
            throw new RuntimeException("Cannot open today's session: Terminal has an unclosed session from {$overdue->business_date->format('M d, Y')} that requires closing first.");
        }

        // 2. Safety Check: Prevent duplicate active sessions for same terminal & date
        $existing = $this->getActiveSession($posStationId, $today);
        if ($existing) {
            throw new RuntimeException("An active session is already open for {$terminal->name} on {$today}. Please close the active session first.");
        }

        if ($openingBalance < 0) {
            throw new InvalidArgumentException("Opening balance cannot be negative.");
        }

        $showroomName = $terminal->location ?: ($terminal->warehouse ? $terminal->warehouse->name : 'Laijau Showroom');

        return PosSession::create([
            'pos_station_id' => $terminal->id,
            'warehouse_id' => $terminal->warehouse_id,
            'terminal_code' => $terminal->code,
            'terminal_name' => $terminal->name,
            'showroom_name' => $showroomName,
            'business_date' => $today,
            'status' => 'open',
            'opening_balance' => round($openingBalance, 2),
            'opening_notes' => $openingNotes,
            'opened_by_user_id' => $user->id,
            'opened_by_name' => $user->name,
            'opened_at' => $this->getNepalNow(),
            'expected_cash' => round($openingBalance, 2),
        ]);
    }

    /**
     * Calculate financial summary and cash drawer expectations for a session.
     */
    public function calculateSessionFinancials(PosSession $session): array
    {
        $sessionDateStr = $session->business_date instanceof \Carbon\CarbonInterface
            ? $session->business_date->toDateString()
            : (string)($session->business_date ?? Carbon::now('Asia/Kathmandu')->toDateString());

        $salesQuery = OfflineSale::where('status', 'completed')
            ->where(function ($q) use ($session, $sessionDateStr) {
                $q->where('pos_session_id', $session->id)
                    ->orWhere(function ($sub) use ($session, $sessionDateStr) {
                        $sub->whereNull('pos_session_id')
                            ->where('business_date', $sessionDateStr)
                            ->where(function ($st) use ($session) {
                                $st->where('pos_station_id', $session->pos_station_id)
                                    ->orWhere(function ($legacy) use ($session) {
                                        $legacy->whereNull('pos_station_id')
                                            ->where('warehouse_id', $session->warehouse_id);
                                    });
                            });
                    });
            });

        $completedSales = (clone $salesQuery)->with('items')->get();
        $saleIds = $completedSales->pluck('id');

        $totalSalesAmount = (float)$completedSales->sum('total_amount');
        $totalSalesCount = $completedSales->count();
        $totalUnitsSold = (int)OfflineSaleItem::whereIn('offline_sale_id', $saleIds)->sum('quantity');
        $totalDiscountAmount = (float)$completedSales->sum('discount_amount');

        // Cash collection calculations (Pure Cash + Split cash portion)
        $pureCashSales = $completedSales->where('payment_method', 'cash');
        $pureCashTotal = (float)$pureCashSales->sum('total_amount');

        $splitSales = $completedSales->where('payment_method', 'split');
        $splitCashTotal = (float)$splitSales->sum('cash_received');
        $splitDigitalTotal = (float)$splitSales->sum(fn($s) => max(0.0, (float)$s->total_amount - (float)$s->cash_received));

        $netCashCollected = round($pureCashTotal + $splitCashTotal, 2);
        $expectedCash = round($session->opening_balance + $netCashCollected, 2);

        $cashTendered = (float)$completedSales->sum('cash_received');
        $changeReturned = (float)$completedSales->sum('change_given');

        // Voids in session
        $voidsQuery = OfflineSale::where('status', 'voided')
            ->where(function ($q) use ($session, $sessionDateStr) {
                $q->where('pos_session_id', $session->id)
                    ->orWhere(function ($sub) use ($session, $sessionDateStr) {
                        $sub->where('business_date', $sessionDateStr)
                            ->where(function ($st) use ($session) {
                                $st->where('pos_station_id', $session->pos_station_id)
                                    ->orWhere(function ($legacy) use ($session) {
                                        $legacy->whereNull('pos_station_id')
                                            ->where('warehouse_id', $session->warehouse_id);
                                    });
                            });
                    });
            });
        $voidSales = $voidsQuery->get();
        $totalVoidAmount = (float)$voidSales->sum('total_amount');
        $totalVoidCount = $voidSales->count();

        // Payment method breakdown
        $paymentMethods = [
            'cash' => ['label' => 'Cash', 'count' => $pureCashSales->count(), 'amount' => $pureCashTotal],
            'fonepay' => ['label' => 'Fonepay QR', 'count' => $completedSales->where('payment_method', 'fonepay')->count(), 'amount' => (float)$completedSales->where('payment_method', 'fonepay')->sum('total_amount')],
            'split' => ['label' => 'Split (Cash + QR)', 'count' => $splitSales->count(), 'amount' => (float)$splitSales->sum('total_amount'), 'cash_portion' => $splitCashTotal, 'digital_portion' => $splitDigitalTotal],
            'esewa' => ['label' => 'eSewa QR', 'count' => $completedSales->where('payment_method', 'esewa')->count(), 'amount' => (float)$completedSales->where('payment_method', 'esewa')->sum('total_amount')],
            'khalti' => ['label' => 'Khalti QR', 'count' => $completedSales->where('payment_method', 'khalti')->count(), 'amount' => (float)$completedSales->where('payment_method', 'khalti')->sum('total_amount')],
            'card' => ['label' => 'Card Terminal', 'count' => $completedSales->where('payment_method', 'card')->count(), 'amount' => (float)$completedSales->where('payment_method', 'card')->sum('total_amount')],
            'bank_transfer' => ['label' => 'Bank Wire', 'count' => $completedSales->where('payment_method', 'bank_transfer')->count(), 'amount' => (float)$completedSales->where('payment_method', 'bank_transfer')->sum('total_amount')],
        ];

        $digitalSales = (float)$completedSales->sum(function ($s) {
            if ($s->payment_method === 'cash') return 0.0;
            if ($s->payment_method === 'split') {
                return max(0.0, (float)$s->total_amount - (float)$s->cash_received);
            }
            return (float)$s->total_amount;
        });

        $cashSales = (float)$completedSales->sum(function ($s) {
            if ($s->payment_method === 'cash') return (float)$s->total_amount;
            if ($s->payment_method === 'split') return (float)$s->cash_received;
            return 0.0;
        });

        return [
            'business_date' => $sessionDateStr,
            'opening_balance' => (float)$session->opening_balance,
            'total_sales_amount' => $totalSalesAmount,
            'total_sales_count' => $totalSalesCount,
            'total_units_sold' => $totalUnitsSold,
            'total_discount_amount' => $totalDiscountAmount,
            'total_void_amount' => $totalVoidAmount,
            'total_void_count' => $totalVoidCount,
            'cash_sales' => $cashSales,
            'digital_sales' => $digitalSales,
            'net_cash_collected' => $netCashCollected,
            'cash_tendered' => $cashTendered,
            'change_returned' => $changeReturned,
            'gross_sales_amount' => round($totalSalesAmount + $totalDiscountAmount, 2),
            'expected_cash' => $expectedCash,
            'payment_breakdown' => $paymentMethods,
            'sales_list' => $completedSales,
            'voids_list' => $voidSales,
            'discounted_sales' => $completedSales->filter(fn($s) => (float)$s->discount_amount > 0)->values(),
        ];
    }

    /**
     * Close a POS session with physical cash count, denominations, variance calculation, shortage reasons, and dual sign-off.
     */
    public function closeSession(
        PosSession $session,
        float $closingCashCounted,
        User $user,
        ?string $closingNotes = null,
        ?string $managerName = null,
        ?array $denominations = null,
        ?string $varianceReasonCode = null,
        ?string $varianceReasonText = null
    ): PosSession {
        if ($session->isClosed()) {
            throw new RuntimeException("Session #{$session->id} is already closed.");
        }

        if ($closingCashCounted < 0) {
            throw new InvalidArgumentException("Closing cash count cannot be negative.");
        }

        $financials = $this->calculateSessionFinancials($session);
        $expectedCash = $financials['expected_cash'];
        $cashVariance = round($closingCashCounted - $expectedCash, 2);

        // Validation for Shortage (< 0)
        if ($cashVariance < 0) {
            if (empty($varianceReasonCode)) {
                throw new InvalidArgumentException("A valid reason for cash shortage is required before closing.");
            }
            if ($varianceReasonCode === 'other' && (empty($varianceReasonText) || strlen(trim($varianceReasonText)) < 5)) {
                throw new InvalidArgumentException("Please provide a detailed explanation when selecting 'Other' for cash shortage.");
            }
        }

        // Validation for Overage (> 0)
        if ($cashVariance > 0) {
            if (empty($varianceReasonCode) && empty($varianceReasonText) && empty($closingNotes)) {
                throw new InvalidArgumentException("Please provide an explanation for the cash overage before closing.");
            }
        }

        $session->update([
            'status' => 'closed',
            'expected_cash' => $expectedCash,
            'closing_cash_counted' => round($closingCashCounted, 2),
            'denominations' => $denominations,
            'cash_variance' => $cashVariance,
            'variance_reason_code' => $varianceReasonCode,
            'variance_reason_text' => $varianceReasonText,
            'cash_sales' => $financials['cash_sales'],
            'digital_sales' => $financials['digital_sales'],
            'change_given' => $financials['change_returned'],
            'total_sales_amount' => $financials['total_sales_amount'],
            'total_sales_count' => $financials['total_sales_count'],
            'total_units_sold' => $financials['total_units_sold'],
            'total_discount_amount' => $financials['total_discount_amount'],
            'total_void_amount' => $financials['total_void_amount'],
            'total_void_count' => $financials['total_void_count'],
            'payment_breakdown' => $financials['payment_breakdown'],
            'closed_by_user_id' => $user->id,
            'closed_by_name' => $user->name,
            'closed_at' => $this->getNepalNow(),
            'closing_notes' => $closingNotes,
            'manager_name' => $managerName ?: $user->name,
            'manager_signed_at' => $this->getNepalNow(),
        ]);

        return $session->fresh();
    }
}
