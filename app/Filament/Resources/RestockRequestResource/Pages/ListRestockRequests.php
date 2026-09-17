<?php

namespace App\Filament\Resources\RestockRequestResource\Pages;

use App\Filament\Resources\RestockRequestResource;
use App\Mail\RestockNotificationMail;
use App\Models\Product;
use App\Models\RestockRequest;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Mail;

class ListRestockRequests extends ListRecords
{
    protected static string $resource = RestockRequestResource::class;

    protected function getHeaderActions(): array
    {
        $pendingCount = RestockRequest::where('status', 'pending')->count();

        return [
            Action::make('notify_all_pending')
                ->label("Notify All Waiting Customers ({$pendingCount})")
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Notify All Waiting Customers')
                ->modalDescription("This will send an immediate restock notification email to all {$pendingCount} customers currently on the waitlist whose requested items are in stock.")
                ->action(function () {
                    $pendingRequests = RestockRequest::where('status', 'pending')->with('product')->get();
                    $notifiedCount = 0;

                    foreach ($pendingRequests as $req) {
                        if ($req->product) {
                            try {
                                Mail::to($req->email)->send(new RestockNotificationMail($req->product));
                            } catch (\Throwable $e) {}
                        }
                        $req->update([
                            'status' => 'notified',
                            'notified_at' => now(),
                        ]);
                        $notifiedCount++;
                    }

                    Notification::make()
                        ->title('All Waiting Customers Notified')
                        ->body("Successfully sent restock emails to {$notifiedCount} customer(s).")
                        ->success()
                        ->send();
                }),
        ];
    }
}
