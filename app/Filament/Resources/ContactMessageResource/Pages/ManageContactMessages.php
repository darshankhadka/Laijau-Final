<?php

declare(strict_types=1);

namespace App\Filament\Resources\ContactMessageResource\Pages;

use App\Filament\Resources\ContactMessageResource;
use App\Models\ContactMessage;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ManageContactMessages extends ManageRecords
{
    protected static string $resource = ContactMessageResource::class;

    public function getSubheading(): string | Htmlable | null
    {
        return 'Customer inquiries and messages submitted via the storefront concierge.';
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
        ];
    }

    public function getTabs(): array
    {
        $newCount = ContactMessage::where('status', ContactMessage::STATUS_NEW)->count();
        $inProgressCount = ContactMessage::where('status', ContactMessage::STATUS_IN_PROGRESS)->count();
        $resolvedCount = ContactMessage::where('status', ContactMessage::STATUS_RESOLVED)->count();

        return [
            'all' => Tab::make('All Messages'),

            'new' => Tab::make('New & Unread')
                ->badge($newCount > 0 ? (string) $newCount : null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ContactMessage::STATUS_NEW)),

            'in_progress' => Tab::make('In Progress')
                ->badge($inProgressCount > 0 ? (string) $inProgressCount : null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ContactMessage::STATUS_IN_PROGRESS)),

            'resolved' => Tab::make('Resolved')
                ->badge($resolvedCount > 0 ? (string) $resolvedCount : null)
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ContactMessage::STATUS_RESOLVED)),
        ];
    }
}
