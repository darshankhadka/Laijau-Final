<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public string $token;
    public string $resetUrl;

    public function __construct(User $user, string $token)
    {
        $this->user = $user;
        $this->token = $token;
        $baseUrl = rtrim(url('/'), '/');
        if (empty($baseUrl) || $baseUrl === 'http://localhost') {
            $baseUrl = rtrim(config('app.url', env('APP_URL', 'https://laijau.com')), '/');
        }
        $this->resetUrl = "{$baseUrl}/account?reset_token={$token}&email=" . urlencode($user->email);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reset Your Laijau Account Password',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset',
        );
    }
}
