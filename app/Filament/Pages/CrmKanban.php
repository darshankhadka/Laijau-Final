<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\CrmActivity;
use App\Models\CrmLead;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Crm\CrmService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;

class CrmKanban extends Page
{
    protected string $view = 'filament.pages.crm-kanban';
    protected Width | string | null $maxWidth = 'full';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-view-columns';
    protected static string | \UnitEnum | null $navigationGroup = 'CRM';
    protected static ?string $navigationLabel = 'Follow-ups & Pipeline';
    protected static ?int $navigationSort = 20;
    protected static ?string $title = 'Clienteling & CRM Pipeline';

    public static function getNavigationBadge(): ?string
    {
        $active = CrmLead::whereNotIn('stage', [CrmLead::STAGE_WON, CrmLead::STAGE_LOST])->count();
        return $active > 0 ? (string) $active : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    public function getHeading(): string | Htmlable
    {
        return 'Clienteling & Sales Pipeline';
    }

    public function getSubheading(): string | Htmlable | null
    {
        return 'Visual 6-stage sales pipeline for customer inquiries, consultations, follow-ups, and orders.';
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

        return $user->hasRole('Store Manager', 'admin')
            || $user->hasRole('Store Manager', 'web')
            || $user->hasRole('Sales Representative', 'admin')
            || $user->hasRole('Sales Representative', 'web')
            || $user->hasRole('Support Agent', 'admin')
            || $user->hasRole('Support Agent', 'web')
            || $user->hasRole('Workspace Admin', 'admin')
            || $user->hasRole('Workspace Admin', 'web')
            || $user->can('page_CrmKanban')
            || in_array($user->role, ['store_manager', 'sales_representative', 'support_agent', 'workspace_admin', 'admin', 'super_admin'], true);
    }

    // Filters
    public string $searchQuery = '';
    public string $filterChannel = 'all';
    public string $filterPriority = 'all';
    public bool $filterOverdueOnly = false;
    public string $mobileActiveStage = 'all';

    public function setMobileActiveStage(string $stage): void
    {
        $this->mobileActiveStage = $stage;
    }

    // New Lead Modal State
    public bool $isCreating = false;
    public string $newTitle = '';
    public string $newContactName = '';
    public string $newPhone = '';
    public string $newEmail = '';
    public string $newChannel = 'whatsapp';
    public string $newStage = 'new';
    public float $newEstimatedValue = 0.00;
    public string $newCurrency = 'NPR';
    public string $newPriority = 'medium';
    public ?int $newProductId = null;
    public ?int $newAssignedStaffId = null;
    public ?string $newFollowUpDate = null;
    public ?string $newEventDate = null;
    public string $newBespokeNotes = '';

    // Active Lead Detail / Activity Drawer State
    public ?int $activeLeadId = null;
    public string $quickNoteText = '';
    public ?string $quickFollowUpDate = null;
    public string $quickFollowUpNotes = '';
    public float $orderAgreedAmount = 0.00;
    public string $orderPaymentMethod = 'cod';
    public string $orderDeliveryAddress = 'Kathmandu Valley, Nepal';

    public function setQuickFollowUpDays(int $days, string $directive = ''): void
    {
        $this->quickFollowUpDate = now()->addDays($days)->format('Y-m-d\TH:i');
        if (!empty($directive)) {
            $this->quickFollowUpNotes = $directive;
        }
    }

    public function getStages(): array
    {
        return [
            CrmLead::STAGE_NEW => [
                'name' => 'New Inquiries',
                'color' => '#3B82F6',
                'icon' => '📥',
                'badge_class' => 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300',
            ],
            CrmLead::STAGE_CONTACTED => [
                'name' => 'Contacted',
                'color' => '#0EA5E9',
                'icon' => '💬',
                'badge_class' => 'bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300',
            ],
            CrmLead::STAGE_QUALIFIED => [
                'name' => 'Qualified',
                'color' => '#8B5CF6',
                'icon' => '🎯',
                'badge_class' => 'bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-300',
            ],
            CrmLead::STAGE_QUOTATION => [
                'name' => 'Quotation',
                'color' => '#C5A059',
                'icon' => '📋',
                'badge_class' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
            ],
            CrmLead::STAGE_NEGOTIATION => [
                'name' => 'Negotiation',
                'color' => '#F59E0B',
                'icon' => '🤝',
                'badge_class' => 'bg-orange-50 text-orange-700 dark:bg-orange-950/40 dark:text-orange-300',
            ],
            CrmLead::STAGE_WON => [
                'name' => 'Won / Converted',
                'color' => '#10B981',
                'icon' => '🏆',
                'badge_class' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
            ],
        ];
    }

    public function getBoardLeadsProperty(): array
    {
        $query = CrmLead::with(['customer', 'product', 'order', 'assignedStaff', 'activities.user']);

        if ($this->filterChannel !== 'all') {
            $query->where('channel', $this->filterChannel);
        }

        if ($this->filterPriority !== 'all') {
            $query->where('priority', $this->filterPriority);
        }

        if ($this->filterOverdueOnly) {
            $query->overdueFollowUps();
        }

        if (strlen(trim($this->searchQuery)) > 0) {
            $s = trim($this->searchQuery);
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', "%{$s}%")
                    ->orWhere('contact_name', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%")
                    ->orWhere('bespoke_notes', 'like', "%{$s}%")
                    ->orWhereHas('product', fn($pq) => $pq->where('name', 'like', "%{$s}%")->orWhere('sku', 'like', "%{$s}%"));
            });
        }

        $leads = $query->latest('updated_at')->get();

        $grouped = [];
        foreach (array_keys($this->getStages()) as $stageKey) {
            $grouped[$stageKey] = $leads->filter(function ($lead) use ($stageKey) {
                // Support legacy aliases
                if ($lead->stage === $stageKey) return true;
                if ($stageKey === CrmLead::STAGE_NEW && in_array($lead->stage, ['new_inquiry', 'new'])) return true;
                if ($stageKey === CrmLead::STAGE_CONTACTED && in_array($lead->stage, ['follow_up', 'contacted'])) return true;
                if ($stageKey === CrmLead::STAGE_QUOTATION && in_array($lead->stage, ['confirmed', 'quotation'])) return true;
                if ($stageKey === CrmLead::STAGE_WON && in_array($lead->stage, ['shipped', 'won'])) return true;
                return false;
            })->values();
        }

        return $grouped;
    }

    public function getPipelineStatsProperty(): array
    {
        $allLeads = CrmLead::select(['id', 'stage', 'priority', 'estimated_value', 'currency', 'follow_up_date'])->get();
        $totalActive = $allLeads->whereNotIn('stage', [CrmLead::STAGE_WON, CrmLead::STAGE_LOST]);

        $currSymbol = 'Rs. ';
        $totalValue = $totalActive->sum('estimated_value');
        $convertedValue = $allLeads->where('stage', CrmLead::STAGE_WON)->sum('estimated_value');
        $overdueCount = CrmLead::overdueFollowUps()->count();

        return [
            'active_count' => $totalActive->count(),
            'pipeline_value' => $currSymbol . number_format($totalValue, 2),
            'converted_value' => $currSymbol . number_format($convertedValue, 2),
            'overdue_count' => $overdueCount,
            'total_leads' => $allLeads->count(),
        ];
    }

    public function moveStage(int $leadId, string $newStage): void
    {
        $lead = CrmLead::find($leadId);
        if ($lead) {
            $user = auth('admin')->user() ?? auth('web')->user();
            app(CrmService::class)->transitionStage($lead, $newStage, null, $user);

            Notification::make()
                ->title('Pipeline Stage Updated')
                ->body("'{$lead->contact_name}' moved to " . ($this->getStages()[$newStage]['name'] ?? $newStage))
                ->success()
                ->duration(2000)
                ->send();
        }
    }

    public function toggleOverdueFilter(): void
    {
        $this->filterOverdueOnly = !$this->filterOverdueOnly;
    }

    public function openLeadDetail(int $leadId): void
    {
        $this->activeLeadId = $leadId;
        $this->quickNoteText = '';
        $this->quickFollowUpNotes = '';
        $lead = CrmLead::find($leadId);
        if ($lead) {
            $this->quickFollowUpDate = $lead->follow_up_date?->format('Y-m-d\TH:i');
            $this->orderAgreedAmount = $lead->estimated_value > 0 ? (float)$lead->estimated_value : 0.00;
        }
    }

    public function closeLeadDetail(): void
    {
        $this->activeLeadId = null;
    }

    public function getActiveLeadProperty(): ?CrmLead
    {
        if (!$this->activeLeadId) {
            return null;
        }

        return CrmLead::with(['customer.orders', 'product', 'order', 'assignedStaff', 'activities.user', 'activities.staff'])
            ->find($this->activeLeadId);
    }

    public function addQuickNote(): void
    {
        if (empty(trim($this->quickNoteText)) || !$this->activeLeadId) {
            return;
        }

        $lead = CrmLead::find($this->activeLeadId);
        if ($lead) {
            $user = auth('admin')->user() ?? auth('web')->user();
            app(CrmService::class)->addNote($lead, trim($this->quickNoteText), $user);
            $this->quickNoteText = '';

            Notification::make()->title('Note saved to activity timeline')->success()->send();
        }
    }

    public function updateLeadFollowUp(): void
    {
        if (!$this->activeLeadId || empty($this->quickFollowUpDate)) {
            return;
        }

        $lead = CrmLead::find($this->activeLeadId);
        if ($lead) {
            $user = auth('admin')->user() ?? auth('web')->user();
            app(CrmService::class)->scheduleFollowUp(
                $lead,
                Carbon::parse($this->quickFollowUpDate),
                trim($this->quickFollowUpNotes) ?: null,
                $user
            );

            Notification::make()->title('Follow-up schedule updated')->success()->send();
        }
    }

    public function convertActiveLeadToOrder(): void
    {
        if (!$this->activeLeadId) {
            return;
        }

        $lead = CrmLead::find($this->activeLeadId);
        if ($lead) {
            $user = auth('admin')->user() ?? auth('web')->user();
            $order = app(CrmService::class)->convertLeadToOrder($lead, [
                'total_amount' => $this->orderAgreedAmount,
                'payment_method' => $this->orderPaymentMethod,
                'shipping_address' => $this->orderDeliveryAddress,
            ], $user);

            Notification::make()
                ->title('Converted to Order!')
                ->body("Order #{$order->order_number} created and Queued for Fulfillment.")
                ->success()
                ->send();
        }
    }

    public function openNewLeadModal(): void
    {
        $this->newTitle = '';
        $this->newContactName = '';
        $this->newPhone = '';
        $this->newEmail = '';
        $this->newChannel = 'whatsapp';
        $this->newStage = 'new';
        $this->newEstimatedValue = 0.00;
        $this->newCurrency = Setting::get('default_currency', 'NPR');
        $this->newPriority = 'medium';
        $this->newProductId = null;
        $this->newAssignedStaffId = auth('admin')->id() ?? auth('web')->id();
        $this->newFollowUpDate = null;
        $this->newEventDate = null;
        $this->newBespokeNotes = '';
        $this->isCreating = true;
    }

    public function closeNewLeadModal(): void
    {
        $this->isCreating = false;
    }

    public function saveNewLead(): void
    {
        if (empty(trim($this->newTitle)) || empty(trim($this->newContactName))) {
            Notification::make()
                ->title('Title and Contact Name are required')
                ->warning()
                ->send();
            return;
        }

        $lead = CrmLead::create([
            'title' => trim($this->newTitle),
            'contact_name' => trim($this->newContactName),
            'phone' => trim($this->newPhone),
            'email' => trim($this->newEmail),
            'channel' => $this->newChannel,
            'stage' => $this->newStage,
            'estimated_value' => max(0, (float) $this->newEstimatedValue),
            'currency' => $this->newCurrency,
            'priority' => $this->newPriority,
            'product_id' => $this->newProductId,
            'assigned_staff_id' => $this->newAssignedStaffId,
            'follow_up_date' => $this->newFollowUpDate ? Carbon::parse($this->newFollowUpDate) : null,
            'event_date' => $this->newEventDate ? Carbon::parse($this->newEventDate) : null,
            'bespoke_notes' => trim($this->newBespokeNotes),
            'internal_notes' => 'Created directly from CRM Pipeline on ' . now()->format('d M Y H:i'),
        ]);

        $user = auth('admin')->user() ?? auth('web')->user();
        app(CrmService::class)->logActivity(
            $lead,
            CrmActivity::TYPE_STAGE_CHANGE,
            "Lead created in stage '{$lead->stage}'",
            [],
            $user
        );

        $this->isCreating = false;

        Notification::make()
            ->title('New CRM Lead Created!')
            ->success()
            ->send();
    }

    public function deleteLead(int $leadId): void
    {
        $lead = CrmLead::find($leadId);
        if ($lead) {
            $name = $lead->contact_name;
            $lead->delete();

            Notification::make()
                ->title('Lead Deleted')
                ->body("Inquiry for {$name} removed.")
                ->success()
                ->send();
        }
    }
}
