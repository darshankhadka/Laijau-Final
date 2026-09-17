<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ShipmentNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public Order $order;

    public function __construct(Order $order)
    {
        $this->order = $order->load('items.product');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your Laijau Order #{$this->order->order_number} Has Shipped",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.shipment-notification',
        );
    }
}
