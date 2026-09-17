<?php

declare(strict_types=1);

namespace App\Filament\Resources\CrmLeadResource\Pages;

use App\Filament\Resources\CrmLeadResource;
use App\Models\CrmLead;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ListCrmLeads extends ListRecords
{
    protected static string $resource = CrmLeadResource::class;

    public function getSubheading(): string | Htmlable | null
    {
        return 'Track high-intent leads, consultations, and VIP client interactions.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('kanban_view')
                ->label('Open Kanban Pipeline')
                ->icon('heroicon-m-view-columns')
                ->url(route('filament.admin.pages.crm-kanban'))
                ->color('primary')
                ->outlined(),

            Actions\CreateAction::make()
                ->label('+ New Inquiry'),
        ];
    }

    public function getTabs(): array
    {
        $overdueCount = CrmLead::overdueFollowUps()->count();
        $activeCount = CrmLead::whereNotIn('stage', [CrmLead::STAGE_WON, CrmLead::STAGE_LOST])->count();
        $wonCount = CrmLead::where('stage', CrmLead::STAGE_WON)->count();

        return [
            'all' => Tab::make('All Inquiries'),

            'active' => Tab::make('Active Pipeline')
                ->badge($activeCount > 0 ? (string) $activeCount : null)
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotIn('stage', [CrmLead::STAGE_WON, CrmLead::STAGE_LOST])),

            'overdue' => Tab::make('Overdue Follow-ups')
                ->badge($overdueCount > 0 ? (string) $overdueCount : null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->overdueFollowUps()),

            'whatsapp' => Tab::make('WhatsApp Inquiries')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('channel', CrmLead::CHANNEL_WHATSAPP)),

            'won' => Tab::make('Won & Converted')
                ->badge($wonCount > 0 ? (string) $wonCount : null)
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('stage', CrmLead::STAGE_WON)),
        ];
    }
}
