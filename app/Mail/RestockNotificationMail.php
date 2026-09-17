<?php

namespace App\Mail;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RestockNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public Product $product;

    /**
     * Create a new message instance.
     */
    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject("Now Available: {$this->product->name} — Laijau")
            ->view('emails.restock-notification');
    }
}
