<?php

namespace App\Services;

use App\Models\Setting;

class PaymentSettingsService
{
    /**
     * Return list of active payment methods configured for Laijau Nepal.
     * PUBLIC CHECKOUT is strictly restricted to:
     * 1. Cash on Delivery (COD) - Inside Kathmandu Valley only
     * 2. ConnectIPS / NCHL Online Payment
     * 3. eSewa Mobile Wallet (Manual QR & Screenshot Upload)
     */
    public function getAvailablePaymentMethods(?string $district = null, bool $forPublicCheckout = true): array
    {
        $methods = [];

        // 1. Cash on Delivery - ONLY Inside Kathmandu Valley
        $codEnabled = (bool) Setting::get('payment_cod_enabled', true);
        $isValley = NepalLocationService::isCodAvailable($district);

        if ($codEnabled && $isValley) {
            $methods['cod'] = [
                'id' => 'cod',
                'name' => 'Cash on Delivery (COD)',
                'description' => 'Pay cash to the courier rider upon delivery at your doorstep in Kathmandu Valley (Rs. 100 delivery charge).',
                'requires_verification' => false,
                'is_cod' => true,
                'badge' => 'Kathmandu Valley Exclusive',
            ];
        }

        // 2. ConnectIPS / NCHL (Direct Interbank Online Payment Gateway)
        if ((bool) Setting::get('payment_connectips_enabled', true)) {
            $methods['connectips'] = [
                'id' => 'connectips',
                'name' => 'connectIPS / NCHL Online Payment',
                'description' => 'Pay securely directly from your bank account via connectIPS (NCHL). Instant automated server verification.',
                'requires_verification' => false,
                'badge' => 'Real-Time Interbank Transfer',
            ];
        }

        // 3. eSewa (Manual QR & Screenshot Upload)
        if ((bool) Setting::get('payment_esewa_enabled', true)) {
            $methods['esewa'] = [
                'id' => 'esewa',
                'name' => 'eSewa Mobile Wallet (QR Payment)',
                'description' => 'Scan the official Laijau eSewa QR code, enter transaction code, and upload proof screenshot for admin verification.',
                'requires_verification' => true,
                'esewa_id' => Setting::get('esewa_id', '9843512095'),
                'account_name' => Setting::get('esewa_account_name', 'Delta Nine business group'),
                'qr_code_url' => Setting::get('esewa_qr_code_url', asset('images/payments/esewa-qr.png')),
                'badge' => 'Official QR Scan & Pay',
            ];
        }

        // Internal POS / Admin Only Methods (NOT exposed on public checkout)
        if (!$forPublicCheckout) {
            if ((bool) Setting::get('payment_khalti_enabled', false)) {
                $methods['khalti'] = [
                    'id' => 'khalti',
                    'name' => 'Khalti Digital Wallet',
                    'description' => 'Internal POS/manual payment via Khalti.',
                    'requires_verification' => true,
                ];
            }

            if ((bool) Setting::get('payment_bank_transfer_enabled', true)) {
                $methods['bank_transfer'] = [
                    'id' => 'bank_transfer',
                    'name' => 'Direct Bank Transfer / Fonepay',
                    'description' => 'Internal POS/manual bank deposit or Fonepay QR.',
                    'requires_verification' => true,
                ];
            }
        }

        return $methods;
    }

    /**
     * Get human-readable payment instructions for display during checkout and order confirmation.
     */
    public function getPaymentInstructions(string $method): array
    {
        switch ($method) {
            case 'connectips':
                return [
                    'title' => 'connectIPS / NCHL Online Payment',
                    'steps' => [
                        'You will be redirected to the secure connectIPS portal.',
                        'Select your participating commercial or development bank.',
                        'Enter your connectIPS username and password.',
                        'Authorize the transaction using your OTP / security token.',
                        'Once authorized, you are instantly returned to Laijau with payment confirmation.',
                    ],
                ];

            case 'esewa':
                return [
                    'title' => 'eSewa QR Payment Instructions',
                    'id' => Setting::get('esewa_id', '9843512095'),
                    'name' => Setting::get('esewa_account_name', 'Delta Nine business group'),
                    'qr_code_url' => Setting::get('esewa_qr_code_url', asset('images/payments/esewa-qr.png')),
                    'steps' => [
                        'Open your eSewa app on your smartphone.',
                        'Scan the official Laijau eSewa QR code displayed on screen.',
                        'Enter the exact order total amount.',
                        'In the Remarks field, enter your Order Number or Mobile Number.',
                        'Complete payment in eSewa and capture a screenshot of the completed transfer.',
                        'Submit your eSewa Transaction Reference code and upload the screenshot.',
                        'Our verification team will review and approve your payment.',
                    ],
                ];

            case 'bank_transfer':
                return [
                    'title' => 'Bank Transfer / Mobile Banking Instructions',
                    'bank' => Setting::get('bank_name', 'Bank Transfer'),
                    'name' => Setting::get('bank_account_name', 'Delta Nine business group'),
                    'account' => Setting::get('bank_account_number', ''),
                    'branch' => Setting::get('bank_branch', 'Kathmandu Branch'),
                    'steps' => [
                        'Use Mobile Banking or ATM / branch transfer to Laijau\'s account.',
                        'Account Name: ' . Setting::get('bank_account_name', 'Delta Nine business group'),
                        'Account Number: ' . Setting::get('bank_account_number', 'Available upon request'),
                        'Bank: ' . Setting::get('bank_name', 'Official Bank Account') . ' (' . Setting::get('bank_branch', 'Kathmandu') . ')',
                        'Save your transfer screenshot/voucher reference number and submit it.',
                    ],
                ];

            case 'cod':
            default:
                return [
                    'title' => 'Cash on Delivery (Kathmandu Valley)',
                    'steps' => [
                        'Keep exact cash ready when the delivery rider contacts you.',
                        'Inspect your package at your doorstep upon arrival.',
                        'Delivery charge of NPR 100 applies.',
                    ],
                ];
        }
    }
}
