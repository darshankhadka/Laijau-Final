<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Mail\ShipmentNotificationMail;
use App\Models\Order;
use App\Models\Inventory\StockReservation;
use App\Services\Inventory\InventoryService;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected string $view = 'filament.resources.orders.view-order';

    protected function getHeaderActions(): array
    {
        return [
            // Print Sales Receipt
            Actions\Action::make('print_receipt')
                ->label('Print Receipt')
                ->icon('heroicon-o-receipt-percent')
                ->color('primary')
                ->url(fn(Order $record) => route('order.pos_receipt', $record))
                ->openUrlInNewTab(),

            // Print Tax Invoice
            Actions\Action::make('print_invoice')
                ->label('Print Invoice')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->url(fn(Order $record) => route('admin.orders.invoice', $record))
                ->openUrlInNewTab(),

            // Print Packing Slip
            Actions\Action::make('print_packing_slip')
                ->label('Packing Slip')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('gray')
                ->url(fn(Order $record) => route('admin.orders.packing_slip', $record))
                ->openUrlInNewTab(),

            // WhatsApp Direct Action
            Actions\Action::make('whatsapp_customer')
                ->label('WhatsApp')
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->color('success')
                ->visible(fn(Order $record) => !empty($record->phone))
                ->url(function (Order $record): string {
                    $cleanPhone = preg_replace('/[^0-9]/', '', (string)$record->phone);
                    if (strlen($cleanPhone) === 10) {
                        $cleanPhone = '977' . $cleanPhone;
                    }
                    $name = $record->first_name ?: 'Customer';
                    $orderNum = $record->order_number;
                    $amount = number_format((float)$record->total_amount);

                    if (in_array($record->status, [Order::STATUS_HANDED_TO_COURIER, Order::STATUS_IN_TRANSIT])) {
                        $courier = $record->carrier ?: 'courier partner';
                        $track = $record->tracking_number ? " Tracking: {$record->tracking_number}." : '';
                        $msg = "Namaste {$name}! Your Laijau Order #{$orderNum} has been handed to {$courier}.{$track} Thank you for shopping with Laijau!";
                    } elseif ($record->payment_status === 'payment_verification_pending') {
                        $msg = "Namaste {$name}! Thank you for submitting your payment for Laijau Order #{$orderNum} (Rs. {$amount}). Our team is verifying your transaction.";
                    } elseif ($record->payment_method === 'cod') {
                        $loc = $record->municipality ? " ({$record->municipality}, {$record->district})" : '';
                        $msg = "Namaste {$name}! This is Laijau Customer Care regarding your Cash on Delivery Order #{$orderNum} for Rs. {$amount}{$loc}. Please reply to confirm your delivery address.";
                    } else {
                        $msg = "Namaste {$name}! This is Laijau Customer Support regarding your Order #{$orderNum}. How can we assist you?";
                    }

                    return "https://wa.me/{$cleanPhone}?text=" . urlencode($msg);
                })
                ->openUrlInNewTab(),

            // Customer Confirmed (CRM)
            Actions\Action::make('confirm_order')
                ->label('Customer Confirmed')
                ->icon('heroicon-m-check')
                ->color('info')
                ->visible(fn(Order $record) => in_array($record->status, [Order::STATUS_PENDING, Order::STATUS_CUSTOMER_CONTACT_REQUIRED]))
                ->requiresConfirmation()
                ->modalHeading('Confirm Customer Order')
                ->modalDescription('Confirm that the customer has verified their delivery details via WhatsApp or Phone call.')
                ->action(function (Order $record): void {
                    $record->update(['status' => Order::STATUS_CUSTOMER_CONFIRMED]);
                    Notification::make()->title('Order confirmed by customer.')->info()->send();
                }),

            // Mark Processing
            Actions\Action::make('mark_processing')
                ->label('Mark Processing')
                ->icon('heroicon-m-arrow-path')
                ->color('primary')
                ->visible(fn(Order $record) => in_array($record->status, [Order::STATUS_PENDING, Order::STATUS_CUSTOMER_CONFIRMED, Order::STATUS_PAYMENT_VERIFIED]))
                ->action(function (Order $record): void {
                    $record->update(['status' => Order::STATUS_PROCESSING]);
                    Notification::make()->title('Order moved to Processing.')->info()->send();
                }),

            // Mark Packed
            Actions\Action::make('mark_packed')
                ->label('Mark Packed')
                ->icon('heroicon-m-cube')
                ->color('primary')
                ->visible(fn(Order $record) => in_array($record->status, [Order::STATUS_PROCESSING]))
                ->action(function (Order $record): void {
                    $record->update(['status' => Order::STATUS_PACKING]);
                    Notification::make()->title('Order boxed and marked as Packed.')->info()->send();
                }),

            // Approve & Verify Payment
            Actions\Action::make('verify_payment')
                ->label('Verify Payment')
                ->icon('heroicon-m-check-badge')
                ->color('success')
                ->visible(fn(Order $record) => $record->payment_status !== 'paid' && $record->payment_method !== 'cod')
                ->requiresConfirmation()
                ->modalHeading('Verify Customer Payment')
                ->modalDescription('Confirm that the payment reference or deposit voucher has been received in the merchant account.')
                ->action(function (Order $record): void {
                    $prevStatus = $record->payment_status;
                    $record->update([
                        'payment_status' => 'paid',
                        'payment_verified_at' => now(),
                        'payment_verified_by' => Auth::id(),
                        'status' => in_array($record->status, [Order::STATUS_PENDING, 'payment_pending', 'customer_contact_required'])
                            ? Order::STATUS_PAYMENT_VERIFIED
                            : $record->status,
                    ]);

                    \App\Models\PaymentAuditLog::record(
                        $record,
                        'paid',
                        $prevStatus,
                        $record->payment_reference,
                        'admin',
                        (string)Auth::id(),
                        null,
                        'Admin manually verified and approved payment.'
                    );

                    Notification::make()->title('Payment verified successfully!')->success()->send();
                }),

            // Push to Fulfillment
            Actions\Action::make('push_to_fulfillment')
                ->label('Push to Fulfillment')
                ->icon('heroicon-m-arrow-right-circle')
                ->color('warning')
                ->visible(fn(Order $record) => in_array($record->status, [
                    Order::STATUS_PENDING,
                    Order::STATUS_CUSTOMER_CONFIRMED,
                    Order::STATUS_PAYMENT_VERIFIED,
                ]))
                ->requiresConfirmation()
                ->modalHeading('Push Order to Fulfillment Queue')
                ->modalDescription('Move this confirmed order to the fulfillment center for picking, packing, and courier dispatch.')
                ->action(function (Order $record): void {
                    $record->update([
                        'status' => Order::STATUS_PROCESSING,
                    ]);

                    \App\Models\LogisticsEvent::create([
                        'order_id' => $record->id,
                        'provider' => 'internal',
                        'external_order_id' => (string)$record->order_number,
                        'event' => 'pushed_to_fulfillment',
                        'status' => 'Processing',
                        'payload' => [
                            'pushed_by' => Auth::user()?->name ?? 'Staff',
                            'timestamp' => now()->toIso8601String(),
                        ],
                        'processing_status' => 'processed',
                        'idempotency_key' => 'push_fulfill_' . $record->id . '_' . time(),
                        'received_at' => now(),
                        'processed_at' => now(),
                    ]);

                    Notification::make()
                        ->title('Order Pushed to Fulfillment')
                        ->body("Order #{$record->order_number} is now in the fulfillment Queue ready for picking.")
                        ->success()
                        ->send();
                }),

            // Dispatch Courier
            Actions\Action::make('dispatch_courier')
                ->label('Hand to Courier')
                ->icon('heroicon-o-truck')
                ->color('info')
                ->form([
                    Select::make('carrier')
                        ->label('Courier Partner')
                        ->options([
                            'ncm' => 'Nepal Can Move (NCM - Automated API)',
                            'pathao' => 'Pathao Parcel (Automated API)',
                            'Laijau Express' => 'Laijau Express (Kathmandu Valley In-House)',
                            'Sundar Courier' => 'Sundar Courier',
                            'Other Courier' => 'Other Courier',
                        ])
                        ->default(fn(Order $record) => app(\App\Services\Logistics\LogisticsService::class)->recommendCourier($record))
                        ->required()
                        ->live(),
                    TextInput::make('tracking_number')
                        ->label('Tracking / Consignment #')
                        ->helperText(fn($get) => in_array($get('carrier'), ['pathao', 'ncm'])
                            ? 'Leave blank for automatic API booking — NCM or Pathao will generate the tracking number.'
                            : 'Enter the courier tracking or consignment number.')
                        ->required(fn($get) => !in_array($get('carrier'), ['pathao', 'ncm'])),
                    TextInput::make('tracking_url')
                        ->label('Tracking URL (Optional - auto-generated if left blank)'),
                ])
                ->action(function (array $data, Order $record): void {
                    $carrier = $data['carrier'];

                    // Automated API Courier Dispatch
                    if (in_array($carrier, ['pathao', 'ncm'], true) && empty($data['tracking_number'])) {
                        $logistics = app(\App\Services\Logistics\LogisticsService::class);
                        $result = $logistics->createShipment($record, $carrier);

                        if ($result['success']) {
                            $record->refresh();
                            $record->update([
                                'status' => Order::STATUS_HANDED_TO_COURIER,
                                'courier_pickup_date' => now(),
                            ]);

                            try {
                                if (!empty($record->email)) {
                                    Mail::to($record->email)->send(new ShipmentNotificationMail($record));
                                }
                            } catch (\Throwable $e) {
                            }

                            $trackingCode = $result['tracking_number'] ?? $record->tracking_number;
                            Notification::make()
                                ->title('Shipment Created via ' . strtoupper($carrier))
                                ->body("Tracking/Consignment # {$trackingCode} automatically assigned.")
                                ->success()
                                ->send();
                            return;
                        } else {
                            Notification::make()
                                ->title('Courier API Notice')
                                ->body($result['error'] ?? 'Could not create courier shipment via API. Please verify credentials/address or enter tracking number manually.')
                                ->danger()
                                ->persistent()
                                ->send();
                            return;
                        }
                    }

                    // Manual / In-House courier flow
                    $carrierName = match ($carrier) {
                        'pathao' => 'Pathao Parcel',
                        'ncm' => 'Nepal Can Move (NCM)',
                        'Laijau Express' => 'Laijau Express (In-House)',
                        default => $carrier,
                    };
                    $trackingNum = !empty($data['tracking_number'])
                        ? trim($data['tracking_number'])
                        : ('LJ-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $carrier), 0, 4)) . '-' . $record->order_number);
                    $trackingUrl = $data['tracking_url'] ?? Order::resolveTrackingUrl($carrierName, $trackingNum);

                    $record->update([
                        'status' => Order::STATUS_HANDED_TO_COURIER,
                        'carrier' => $carrierName,
                        'courier_name' => $carrierName,
                        'tracking_number' => $trackingNum,
                        'courier_order_id' => $trackingNum,
                        'tracking_url' => $trackingUrl,
                        'courier_status' => 'Dispatched',
                        'courier_pickup_date' => now(),
                    ]);

                    try {
                        if (!empty($record->email)) {
                            Mail::to($record->email)->send(new ShipmentNotificationMail($record));
                        }
                    } catch (\Throwable $e) {
                    }

                    Notification::make()
                        ->title('Order Dispatched to ' . $carrierName)
                        ->body("Consignment tracking # {$trackingNum} assigned.")
                        ->success()
                        ->send();
                })
                ->requiresConfirmation()
                ->visible(fn(Order $record) => in_array($record->status, [
                    Order::STATUS_PENDING,
                    Order::STATUS_CUSTOMER_CONFIRMED,
                    Order::STATUS_PAYMENT_VERIFIED,
                    Order::STATUS_PROCESSING,
                    Order::STATUS_PACKING,
                    Order::STATUS_READY_FOR_DELIVERY,
                ])),

            // Mark Delivered
            Actions\Action::make('mark_delivered')
                ->label('Mark Delivered')
                ->icon('heroicon-m-check-circle')
                ->color('success')
                ->visible(fn(Order $record) => in_array($record->status, [
                    Order::STATUS_HANDED_TO_COURIER,
                    Order::STATUS_IN_TRANSIT,
                    Order::STATUS_READY_FOR_DELIVERY,
                ]))
                ->action(function (Order $record): void {
                    $record->update([
                        'status' => Order::STATUS_DELIVERED,
                        'actual_delivery_date' => now(),
                        'delivered_at' => now(),
                        'payment_status' => $record->payment_method === 'cod' ? 'paid' : $record->payment_status,
                    ]);
                    Notification::make()->title('Order marked as Delivered.')->success()->send();
                }),

            // Cancel Order
            Actions\Action::make('cancel_order')
                ->label('Cancel Order')
                ->icon('heroicon-m-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Cancel Retail Order')
                ->modalDescription('Specify the reason for order cancellation. Any active inventory reservations will be released safely.')
                ->form([
                    Textarea::make('cancellation_reason')
                        ->label('Cancellation Reason')
                        ->placeholder('Customer request, duplicate order, out of stock, or delivery refused.')
                        ->required(),
                ])
                ->visible(fn(Order $record) => !in_array($record->status, [Order::STATUS_DELIVERED, Order::STATUS_CANCELLED]))
                ->action(function (array $data, Order $record): void {
                    $reason = $data['cancellation_reason'];
                    $record->update([
                        'status' => Order::STATUS_CANCELLED,
                        'cancelled_at' => now(),
                        'cancellation_reason' => $reason,
                    ]);

                    // Release inventory reservations safely
                    $reservations = StockReservation::where('reference_type', 'online_order')
                        ->where('reference_id', $record->id)
                        ->where('status', 'active')
                        ->get();

                    $invService = app(InventoryService::class);
                    foreach ($reservations as $r) {
                        $invService->releaseReservation($r, 'cancelled');
                    }

                    Notification::make()->title('Order cancelled and reserved stock released.')->danger()->send();
                }),

            Actions\EditAction::make(),
        ];
    }
}
