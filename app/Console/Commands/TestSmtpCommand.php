<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestSmtpCommand extends Command
{
    protected $signature = 'mail:test {recipient : The destination email address}';
    protected $description = 'Safely test SMTP transactional email delivery through configured mailer';

    public function handle(): int
    {
        $recipient = $this->argument('recipient');

        $this->info("========================================");
        $this->info("LAIJAU TRANSACTIONAL SMTP TEST");
        $this->info("========================================");
        $this->line("Mailer: " . config('mail.default'));
        $this->line("Host:   " . config('mail.mailers.smtp.host'));
        $this->line("Port:   " . config('mail.mailers.smtp.port'));
        $this->line("From:   " . config('mail.from.name') . " <" . config('mail.from.address') . ">");
        $this->line("To:     " . $recipient);
        $this->line("----------------------------------------");

        $this->comment("Initiating SMTP handshake and sending test message...");

        try {
            Mail::raw("This is a verified test email from Laijau confirming operational SMTP transactional email delivery.\n\nTimestamp: " . now()->toIso8601String() . "\nHost: " . config('mail.mailers.smtp.host') . "\nMailer: " . config('mail.default'), function ($message) use ($recipient) {
                $message->to($recipient)
                    ->subject("Laijau — Transactional Email Verification Ping [" . date('H:i:s') . "]");
            });

            $this->info("✓ SUCCESS: SMTP message accepted by transport for delivery to {$recipient}.");
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("✗ FAILED: SMTP delivery failed.");
            $this->error("Exception: " . get_class($e));
            $this->error("Message:   " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
