<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MailSettingsService
{
    /**
     * Supported transactional email events and their setting keys.
     */
    public const TRANSACTIONAL_EVENTS = [
        'customer_registration' => 'mail_event_customer_registration',
        'email_verification' => 'mail_event_email_verification',
        'password_reset' => 'mail_event_password_reset',
        'order_confirmation' => 'mail_event_order_confirmation',
        'payment_confirmation' => 'mail_event_payment_confirmation',
        'order_processing' => 'mail_event_order_processing',
        'order_shipped' => 'mail_event_order_shipped',
        'order_delivered' => 'mail_event_order_delivered',
        'order_cancelled' => 'mail_event_order_cancelled',
        'refund_confirmation' => 'mail_event_refund_confirmation',
        'contact_notification' => 'mail_event_contact_notification',
        'admin_order_notification' => 'mail_event_admin_order_notification',
    ];

    /**
     * Configure runtime mail transport using database-backed SMTP settings.
     */
    public function configureRuntimeTransport(): void
    {
        $driver = Setting::get('mail_driver') ?: config('mail.default', env('MAIL_MAILER', 'smtp'));
        if (app()->environment('production') && $driver === 'log' && !empty(env('MAIL_HOST'))) {
            $driver = 'smtp';
        }
        if (!empty($driver)) {
            Config::set('mail.default', $driver);
        }

        $host = Setting::get('smtp_host') ?: config('mail.mailers.smtp.host', env('MAIL_HOST', 'mail.laijau.com'));
        $port = (int) (Setting::get('smtp_port') ?: config('mail.mailers.smtp.port', env('MAIL_PORT', 465)));
        $encryption = Setting::get('smtp_encryption') ?: config('mail.mailers.smtp.encryption', env('MAIL_ENCRYPTION', 'ssl'));
        $username = Setting::get('smtp_username') ?: config('mail.mailers.smtp.username', env('MAIL_USERNAME', 'support@laijau.com'));
        
        $password = Setting::getSecret('smtp_password');
        if (empty($password) || $password === 'MySuperSecretSmtpPass123!' || str_contains($password, 'placeholder')) {
            $password = config('mail.mailers.smtp.password', env('MAIL_PASSWORD'));
        }

        $fromAddress = Setting::get('smtp_from_address') ?: config('mail.from.address', env('MAIL_FROM_ADDRESS', 'support@laijau.com'));
        $fromName = Setting::get('smtp_from_name') ?: config('mail.from.name', env('MAIL_FROM_NAME', 'Laijau'));

        if (!empty($host)) {
            Config::set('mail.mailers.smtp.host', $host);
            Config::set('mail.mailers.smtp.port', $port);
            Config::set('mail.mailers.smtp.encryption', $encryption === 'none' ? null : $encryption);
            Config::set('mail.mailers.smtp.scheme', ($port === 465 || $encryption === 'ssl') ? 'smtps' : null);
            Config::set('mail.mailers.smtp.username', $username);
            Config::set('mail.mailers.smtp.password', $password);
        }

        if (!empty($fromAddress)) {
            Config::set('mail.from.address', $fromAddress);
            Config::set('mail.from.name', $fromName);
        }

        if (class_exists(Mail::class)) {
            Mail::purge('smtp');
        }
    }

    /**
     * Check whether a specific transactional email notification is enabled.
     */
    public function isEventEnabled(string $event): bool
    {
        $settingKey = self::TRANSACTIONAL_EVENTS[$event] ?? null;

        if (!$settingKey) {
            return true;
        }

        // Default to enabled (true) if setting is unset
        return (bool) Setting::get($settingKey, true);
    }

    /**
     * Send a sanitized test verification email to an administrator.
     * Prevents any credential leaks in logs or exceptions.
     */
    public function sendTestEmail(string $recipient): array
    {
        $recipient = trim($recipient);
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message' => 'Invalid test recipient email address.',
            ];
        }

        $host = Setting::get('smtp_host', config('mail.mailers.smtp.host'));
        $port = Setting::get('smtp_port', config('mail.mailers.smtp.port'));
        $encryption = Setting::get('smtp_encryption', config('mail.mailers.smtp.encryption', 'ssl'));
        $fromAddress = Setting::get('smtp_from_address', config('mail.from.address', 'support@laijau.com'));
        $fromName = Setting::get('smtp_from_name', config('mail.from.name', 'Laijau'));
        $replyTo = Setting::get('smtp_reply_to', 'support@laijau.com');

        if (empty($host)) {
            return [
                'success' => false,
                'message' => 'SMTP Host is not configured. Please enter a valid SMTP server address.',
            ];
        }

        $this->configureRuntimeTransport();

        try {
            Mail::raw(
                "🛍️ LAIJAU — TRANSACTIONAL TRANSPORT VERIFICATION 🛍️\n\n" .
                "This automated verification confirms that your transactional mail transport is operational.\n\n" .
                "• Host: {$host}\n" .
                "• Port: {$port} (" . strtoupper($encryption ?: 'NONE') . ")\n" .
                "• Sender: {$fromName} <{$fromAddress}>\n" .
                "• Reply-To: {$replyTo}\n" .
                "• Recipient: {$recipient}\n" .
                "• Dispatched: " . now()->toRfc2822String() . "\n\n" .
                "Laijau Retail Operations & Support (Kathmandu, Nepal)",
                function ($message) use ($recipient, $fromAddress, $fromName, $replyTo) {
                    $message->to($recipient)
                        ->from($fromAddress, $fromName)
                        ->replyTo($replyTo)
                        ->subject('Laijau — SMTP Verification Dispatch');
                }
            );

            return [
                'success' => true,
                'message' => "Verification email successfully dispatched to {$recipient}.",
            ];
        } catch (\Throwable $e) {
            $rawError = $e->getMessage();

            // Sanitize error string: never leak SMTP password or credentials
            $secret = Setting::getSecret('smtp_password');
            if (!empty($secret)) {
                $rawError = str_replace($secret, '••••••••', $rawError);
            }

            Log::error('SMTP Test Dispatch Failed', [
                'recipient' => $recipient,
                'host' => $host,
                'port' => $port,
                'error' => $rawError,
            ]);

            return [
                'success' => false,
                'message' => 'SMTP transport delivery failed: ' . $rawError,
            ];
        }
    }
}
