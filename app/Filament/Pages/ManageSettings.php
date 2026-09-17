<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Models\SettingAuditLog;
use App\Services\StoreSettingsService;
use App\Services\MailSettingsService;
use App\Services\PaymentSettingsService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ManageSettings extends Page
{
    protected string $view = 'filament.pages.manage-settings';
    protected Width | string | null $maxWidth = 'full';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static string|\UnitEnum|null $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'General Settings';
    protected static ?int $navigationSort = 10;
    protected static ?string $title = 'Store & System Settings';

    public static function getAuthenticatedUser()
    {
        return \Filament\Facades\Filament::auth()->user()
            ?? \Illuminate\Support\Facades\Auth::guard('admin')->user()
            ?? \Illuminate\Support\Facades\Auth::guard('web')->user()
            ?? \Illuminate\Support\Facades\Auth::user();
    }

    public static function canAccess(): bool
    {
        $user = static::getAuthenticatedUser();
        if (!$user) {
            return false;
        }

        return method_exists($user, 'canViewSettings') ? $user->canViewSettings() : false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getHeading(): string | Htmlable
    {
        return 'Store Operations & System Settings';
    }

    public function getSubheading(): string | Htmlable | null
    {
        return 'Comprehensive control center for store identity, Nepal payment gateways, logistics, IRD statutory VAT, SMTP mail, customer support channels, and server maintenance.';
    }

    // Active Navigation Tab
    public string $activeTab = 'identity';

    // 1. Store Identity & Positioning
    public string $store_name = '';
    public string $store_short_name = '';
    public string $store_tagline = '';
    public string $store_description = '';
    public string $legal_entity_name = '';
    public string $brand_established_year = '2026';
    public string $brand_email = '';
    public string $support_email = '';
    public string $support_phone = '';
    public string $whatsapp_number = '';
    public string $showroom_address = '';
    public string $business_city = 'Kathmandu';
    public string $business_country = 'Nepal';
    public string $business_postal_code = '44600';
    public string $business_registration_number = '';
    public string $default_currency = 'NPR';
    public string $store_timezone = 'Asia/Kathmandu';

    // Nepal & Kathmandu Positioning
    public string $dispatch_origin_hub = '';
    public string $primary_market = '';
    public string $service_region = '';
    public string $default_locale = 'en_NP';
    public string $nepal_delivery_message = '';

    // Storefront Announcement Bar
    public bool $announcement_enabled = true;
    public string $announcement_text = '';
    public string $announcement_text_npr = '';
    public string $announcement_link = '/products';
    public string $announcement_cta = 'Shop Now';
    public bool $show_vat_messaging = false;
    public bool $show_free_shipping_messaging = true;
    public bool $show_delivery_messaging = true;

    // SEO Identity
    public string $seo_site_title = '';
    public string $seo_meta_description = '';
    public string $seo_keywords = '';
    public string $seo_canonical_base_url = '';

    // 2. Nepal Payment Gateways
    public bool $payment_cod_enabled = true;
    public bool $payment_connectips_enabled = true;
    public bool $payment_esewa_enabled = true;
    public bool $payment_khalti_enabled = false;
    public bool $payment_bank_transfer_enabled = true;

    // ConnectIPS Gateway
    public string $connectips_merchant_id = '';
    public string $connectips_app_id = '';
    public string $connectips_app_name = '';

    // eSewa Wallet
    public string $esewa_id = '';
    public string $esewa_account_name = '';
    public string $esewa_qr_code_url = '';

    // Bank Transfer / Fonepay
    public string $bank_name = '';
    public string $bank_account_name = '';
    public string $bank_account_number = '';
    public string $bank_branch = '';

    // 3. Logistics & Shipping
    public string $shipping_origin_country = 'NP';
    public string $shipping_origin_city = 'Kathmandu';
    public string $shipping_origin_postal_code = '44600';
    public string $shipping_origin_address = 'Bohara Tol, Kageshwori Manahara 09';
    public string $default_courier = 'Nepal Can Move (NCM) & Pathao Parcel';
    public float $shipping_free_threshold_npr = 2000.00;
    public float $standard_shipping_rate_npr = 100.00;
    public float $outside_valley_shipping_rate_npr = 150.00;
    public float $express_shipping_rate_npr = 200.00;
    public string $packaging_notes = '';

    // 4. Nepal Tax & Statutory VAT Engine
    public bool $vat_enabled = true;
    public float $default_vat_rate = 13.00;
    public string $vat_number_prefix = 'NP';
    public bool $display_prices_with_vat = true;
    public string $tax_calculation_mode = 'nepal_single_rate_13';
    public string $tax_display_label = '13% VAT Included';

    // 5. Transactional Mail & SMTP
    public string $smtp_provider = 'Custom SMTP';
    public string $smtp_host = '';
    public string $smtp_port = '465';
    public string $smtp_encryption = 'ssl';
    public string $smtp_username = '';
    public string $smtp_password = '';
    public string $smtp_from_address = '';
    public string $smtp_from_name = '';
    public string $smtp_reply_to = '';
    public string $test_email_recipient = '';

    // Transactional notification event toggles
    public bool $mail_event_customer_registration = true;
    public bool $mail_event_order_confirmation = true;
    public bool $mail_event_order_shipped = true;
    public bool $mail_event_order_delivered = true;
    public bool $mail_event_order_cancelled = true;
    public bool $mail_event_contact_notification = true;
    public bool $mail_event_admin_order_notification = true;

    // 6. Customer Support & Socials
    public string $whatsapp_welcome_message = '';
    public bool $concierge_widget_enabled = true;
    public string $concierge_hours = 'Sun – Fri: 10:00 – 19:00 NPT';
    public string $concierge_response_time = 'Typically responds within 1 hour';
    public string $concierge_cta_text = 'Contact Support';

    // Social Links CRUD array
    public array $social_links = [];

    // 7. Maintenance & System
    public bool $maintenance_mode = false;
    public string $maintenance_message = '';
    public string $maintenance_expected_return = 'Shortly';
    public string $maintenance_allowed_ips = '';
    public string $maintenance_contact_email = '';

    // Feature Toggles
    public bool $feature_registration_enabled = true;
    public bool $feature_guest_checkout_enabled = true;
    public bool $feature_customer_accounts_enabled = true;
    public bool $feature_wishlist_enabled = true;
    public bool $feature_reviews_enabled = true;
    public bool $feature_google_login_enabled = true;
    public bool $feature_newsletter_enabled = true;

    // Diagnostics / Status Cache
    public array $systemDiagnostics = [];

    public function mount(StoreSettingsService $storeService): void
    {
        $brand = $storeService->getBrandIdentity();
        $pos = $storeService->getNepalPositioning();

        // 1. Identity
        $this->store_name = Setting::get('store_name', $brand['store_name']);
        $this->store_short_name = Setting::get('store_short_name', $brand['store_short_name']);
        $this->store_tagline = Setting::get('store_tagline', $brand['store_tagline']);
        $this->store_description = Setting::get('store_description', $brand['store_description']);
        $this->legal_entity_name = Setting::get('legal_entity_name', $brand['legal_entity_name']);
        $this->brand_established_year = (string) Setting::get('brand_established_year', $brand['brand_established_year']);
        $this->brand_email = Setting::get('brand_email', $brand['brand_email']);
        $this->support_email = Setting::get('support_email', $brand['support_email']);
        $this->support_phone = Setting::get('support_phone', $brand['support_phone']);
        $this->whatsapp_number = Setting::get('whatsapp_number', $brand['whatsapp_number']);
        $this->showroom_address = Setting::get('showroom_address', $brand['showroom_address']);
        $this->business_city = Setting::get('business_city', $brand['business_city']);
        $this->business_country = Setting::get('business_country', $brand['business_country']);
        $this->business_postal_code = Setting::get('business_postal_code', $brand['business_postal_code']);
        $this->business_registration_number = Setting::get('business_registration_number', $brand['business_registration_number']);
        $this->default_currency = 'NPR';
        $this->store_timezone = Setting::get('store_timezone', $brand['timezone']);

        // Positioning
        $this->dispatch_origin_hub = Setting::get('dispatch_origin_hub', $pos['dispatch_origin_hub']);
        $this->primary_market = Setting::get('primary_market', $pos['primary_market']);
        $this->service_region = Setting::get('service_region', $pos['service_region']);
        $this->default_locale = Setting::get('default_locale', $pos['default_locale']);
        $this->nepal_delivery_message = Setting::get('nepal_delivery_message', $pos['nepal_delivery_message']);

        // Announcement
        $ann = $storeService->getAnnouncementBar();
        $this->announcement_enabled = (bool) Setting::get('announcement_enabled', $ann['enabled']);
        $this->announcement_text = Setting::get('announcement_text', $ann['text']);
        $this->announcement_text_npr = Setting::get('announcement_text_npr', $ann['text']);
        $this->announcement_link = Setting::get('announcement_link', $ann['link']);
        $this->announcement_cta = Setting::get('announcement_cta', $ann['cta']);
        $this->show_vat_messaging = (bool) Setting::get('show_vat_messaging', $ann['show_vat_messaging']);
        $this->show_free_shipping_messaging = (bool) Setting::get('show_free_shipping_messaging', $ann['show_free_shipping_messaging']);
        $this->show_delivery_messaging = (bool) Setting::get('show_delivery_messaging', $ann['show_delivery_messaging']);

        // SEO
        $this->seo_site_title = Setting::get('seo_site_title', "Laijau | Nepal's Premier Retail & E-Commerce Destination");
        $this->seo_meta_description = Setting::get('seo_meta_description', $this->store_description);
        $this->seo_keywords = Setting::get('seo_keywords', 'online shopping Nepal, shoes Nepal, clothing Kathmandu, Laijau ecommerce, cash on delivery Nepal');
        $this->seo_canonical_base_url = Setting::get('seo_canonical_base_url', config('app.url', 'https://laijau.com'));

        // 2. Nepal Payment Gateways
        $this->payment_cod_enabled = (bool) Setting::get('payment_cod_enabled', true);
        $this->payment_connectips_enabled = (bool) Setting::get('payment_connectips_enabled', true);
        $this->payment_esewa_enabled = (bool) Setting::get('payment_esewa_enabled', true);
        $this->payment_khalti_enabled = (bool) Setting::get('payment_khalti_enabled', false);
        $this->payment_bank_transfer_enabled = (bool) Setting::get('payment_bank_transfer_enabled', true);

        $this->connectips_merchant_id = Setting::get('connectips_merchant_id', '');
        $this->connectips_app_id = Setting::get('connectips_app_id', '');
        $this->connectips_app_name = Setting::get('connectips_app_name', 'LAIJAU');

        $this->esewa_id = Setting::get('esewa_id', '9843512095');
        $this->esewa_account_name = Setting::get('esewa_account_name', 'Delta Nine business group');
        $this->esewa_qr_code_url = Setting::get('esewa_qr_code_url', asset('images/payments/esewa-qr.png'));

        $this->bank_name = Setting::get('bank_name', 'Nabil Bank / NIMB');
        $this->bank_account_name = Setting::get('bank_account_name', 'Delta Nine business group');
        $this->bank_account_number = Setting::get('bank_account_number', '');
        $this->bank_branch = Setting::get('bank_branch', 'Kathmandu Branch');

        // 3. Shipping
        $this->shipping_origin_country = Setting::get('shipping_origin_country', 'NP');
        $this->shipping_origin_city = Setting::get('shipping_origin_city', 'Kathmandu');
        $this->shipping_origin_postal_code = Setting::get('shipping_origin_postal_code', '44600');
        $this->shipping_origin_address = Setting::get('shipping_origin_address', 'Bohara Tol, Kageshwori Manahara 09');
        $this->default_courier = Setting::get('default_courier', 'NCM & Pathao Courier Logistics');
        $this->shipping_free_threshold_npr = (float) Setting::get('shipping_free_threshold_npr', 2000.00);
        $this->standard_shipping_rate_npr = (float) Setting::get('standard_shipping_rate_npr', 100.00);
        $this->outside_valley_shipping_rate_npr = (float) Setting::get('outside_valley_shipping_rate_npr', 150.00);
        $this->express_shipping_rate_npr = (float) Setting::get('express_shipping_rate_npr', 200.00);
        $this->packaging_notes = Setting::get('packaging_notes', 'Carefully packaged with tamper-evident seal and official Laijau branding.');

        // 4. Tax
        $this->vat_enabled = (bool) Setting::get('vat_enabled', true);
        $this->default_vat_rate = (float) Setting::get('default_vat_rate', 13.00);
        $this->vat_number_prefix = Setting::get('vat_number_prefix', 'NP');
        $this->display_prices_with_vat = (bool) Setting::get('display_prices_with_vat', true);
        $this->tax_calculation_mode = Setting::get('tax_calculation_mode', 'nepal_single_rate_13');
        $this->tax_display_label = Setting::get('tax_display_label', '13% VAT Included');

        // 5. Mail
        $this->smtp_provider = Setting::get('smtp_provider', 'Custom SMTP');
        $this->smtp_host = Setting::get('smtp_host', config('mail.mailers.smtp.host', ''));
        $this->smtp_port = (string) Setting::get('smtp_port', config('mail.mailers.smtp.port', '465'));
        $this->smtp_encryption = Setting::get('smtp_encryption', config('mail.mailers.smtp.encryption', 'ssl'));
        $this->smtp_username = Setting::get('smtp_username', config('mail.mailers.smtp.username', ''));
        $this->smtp_password = Setting::maskSecret(Setting::getSecret('smtp_password') ?: config('mail.mailers.smtp.password', ''));
        $this->smtp_from_address = Setting::get('smtp_from_address', config('mail.from.address', 'info@laijau.com'));
        $this->smtp_from_name = Setting::get('smtp_from_name', config('mail.from.name', 'Laijau'));
        $this->smtp_reply_to = Setting::get('smtp_reply_to', 'info@laijau.com');

        $this->mail_event_customer_registration = (bool) Setting::get('mail_event_customer_registration', true);
        $this->mail_event_order_confirmation = (bool) Setting::get('mail_event_order_confirmation', true);
        $this->mail_event_order_shipped = (bool) Setting::get('mail_event_order_shipped', true);
        $this->mail_event_order_delivered = (bool) Setting::get('mail_event_order_delivered', true);
        $this->mail_event_order_cancelled = (bool) Setting::get('mail_event_order_cancelled', true);
        $this->mail_event_contact_notification = (bool) Setting::get('mail_event_contact_notification', true);
        $this->mail_event_admin_order_notification = (bool) Setting::get('mail_event_admin_order_notification', true);

        // 6. Support & Socials
        $this->whatsapp_welcome_message = Setting::get('whatsapp_welcome_message', "Namaste! Welcome to Laijau. How may our support team assist you today?");
        $this->concierge_widget_enabled = (bool) Setting::get('concierge_widget_enabled', true);
        $this->concierge_hours = Setting::get('concierge_hours', 'Sun – Fri: 10:00 – 19:00 NPT');
        $this->concierge_response_time = Setting::get('concierge_response_time', 'Typically responds within 1 hour');
        $this->concierge_cta_text = Setting::get('concierge_cta_text', 'Contact Support');

        $this->social_links = $storeService->getSocialLinks();

        // 7. Maintenance & Features
        $this->maintenance_mode = (bool) Setting::get('maintenance_mode', false);
        $this->maintenance_message = Setting::get('maintenance_message', 'Our online store is currently undergoing scheduled maintenance. We will return shortly.');
        $this->maintenance_expected_return = Setting::get('maintenance_expected_return', 'Shortly');
        $this->maintenance_allowed_ips = Setting::get('maintenance_allowed_ips', '');
        $this->maintenance_contact_email = Setting::get('maintenance_contact_email', 'support@laijau.com');

        $this->feature_registration_enabled = (bool) Setting::get('feature_registration_enabled', true);
        $this->feature_guest_checkout_enabled = (bool) Setting::get('feature_guest_checkout_enabled', true);
        $this->feature_customer_accounts_enabled = (bool) Setting::get('feature_customer_accounts_enabled', true);
        $this->feature_wishlist_enabled = (bool) Setting::get('feature_wishlist_enabled', true);
        $this->feature_reviews_enabled = (bool) Setting::get('feature_reviews_enabled', true);
        $this->feature_google_login_enabled = (bool) Setting::get('feature_google_login_enabled', true);
        $this->feature_newsletter_enabled = (bool) Setting::get('feature_newsletter_enabled', true);

        $this->loadSystemDiagnostics();
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function addSocialLink(): void
    {
        $this->social_links[] = [
            'platform' => 'Other',
            'url' => 'https://',
            'display_label' => 'Link',
            'icon_identifier' => 'link',
            'enabled' => true,
            'sort_order' => count($this->social_links) + 1,
        ];
    }

    public function removeSocialLink(int $index): void
    {
        if (isset($this->social_links[$index])) {
            unset($this->social_links[$index]);
            $this->social_links = array_values($this->social_links);
        }
    }

    public function sendTestEmail(): void
    {
        $recipient = trim($this->test_email_recipient) ?: (static::getAuthenticatedUser()?->email ?? 'info@laijau.com');
        $mailService = app(MailSettingsService::class);
        $result = $mailService->sendTestEmail($recipient);

        if ($result['success']) {
            Notification::make()
                ->title('Test Email Dispatched')
                ->body($result['message'])
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('SMTP Dispatch Failed')
                ->body($result['message'])
                ->danger()
                ->send();
        }
    }

    public function loadSystemDiagnostics(): void
    {
        $mysqlVersion = 'Unknown';
        try {
            $res = DB::select("SELECT VERSION() as ver");
            $mysqlVersion = $res[0]->ver ?? 'MySQL 8.x';
        } catch (\Throwable $e) {
        }

        $this->systemDiagnostics = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'gd_webp_support' => function_exists('imagewebp') ? 'Active (GD WebP v2)' : 'Disabled',
            'mysql_version' => $mysqlVersion,
            'environment' => app()->environment(),
            'timezone' => config('app.timezone', 'Asia/Kathmandu'),
            'memory_limit' => ini_get('memory_limit'),
            'storage_status' => Storage::disk('public')->exists('') ? 'Linked & Writable' : 'Active',
            'active_workers' => 'PHP FPM / CLI Multi-thread',
        ];
    }

    public function saveSettings(): void
    {
        /** @var \App\Models\User|null $user */
        $user = static::getAuthenticatedUser();

        // 1. Enforce Server-Side Role Authorization
        if ($user instanceof \App\Models\User && ($user->isViewer() || $user->isSupportAgent())) {
            Notification::make()
                ->title('Access Denied')
                ->body('Your administrative role has read-only permissions and cannot modify store settings.')
                ->danger()
                ->send();
            return;
        }

        $isSuperAdmin = !$user || !method_exists($user, 'isSuperAdmin') || $user->isSuperAdmin();

        // 1. Identity & Positioning
        Setting::set('store_name', trim($this->store_name));
        Setting::set('store_short_name', trim($this->store_short_name));
        Setting::set('store_tagline', trim($this->store_tagline));
        Setting::set('store_description', trim($this->store_description));
        Setting::set('legal_entity_name', trim($this->legal_entity_name));
        Setting::set('brand_established_year', trim($this->brand_established_year));
        Setting::set('brand_email', trim($this->brand_email));
        Setting::set('support_email', trim($this->support_email));
        Setting::set('support_phone', trim($this->support_phone));
        Setting::set('whatsapp_number', trim($this->whatsapp_number));
        Setting::set('showroom_address', trim($this->showroom_address));
        Setting::set('business_city', trim($this->business_city));
        Setting::set('business_country', trim($this->business_country));
        Setting::set('business_postal_code', trim($this->business_postal_code));
        Setting::set('business_registration_number', trim($this->business_registration_number));
        Setting::set('default_currency', 'NPR');
        Setting::set('store_timezone', trim($this->store_timezone));

        Setting::set('dispatch_origin_hub', trim($this->dispatch_origin_hub));
        Setting::set('primary_market', trim($this->primary_market));
        Setting::set('service_region', trim($this->service_region));
        Setting::set('default_locale', trim($this->default_locale));
        Setting::set('nepal_delivery_message', trim($this->nepal_delivery_message));

        // Announcement Bar
        Setting::set('announcement_enabled', $this->announcement_enabled ? '1' : '0');
        Setting::set('announcement_text', trim($this->announcement_text));
        Setting::set('announcement_text_npr', trim($this->announcement_text_npr));
        Setting::set('announcement_link', trim($this->announcement_link));
        Setting::set('announcement_cta', trim($this->announcement_cta));
        Setting::set('show_vat_messaging', $this->show_vat_messaging ? '1' : '0');
        Setting::set('show_free_shipping_messaging', $this->show_free_shipping_messaging ? '1' : '0');
        Setting::set('show_delivery_messaging', $this->show_delivery_messaging ? '1' : '0');

        // SEO
        Setting::set('seo_site_title', trim($this->seo_site_title));
        Setting::set('seo_meta_description', trim($this->seo_meta_description));
        Setting::set('seo_keywords', trim($this->seo_keywords));
        Setting::set('seo_canonical_base_url', trim($this->seo_canonical_base_url));

        // 2. Nepal Payment Gateways
        Setting::set('payment_cod_enabled', $this->payment_cod_enabled ? '1' : '0');
        Setting::set('payment_connectips_enabled', $this->payment_connectips_enabled ? '1' : '0');
        Setting::set('payment_esewa_enabled', $this->payment_esewa_enabled ? '1' : '0');
        Setting::set('payment_khalti_enabled', $this->payment_khalti_enabled ? '1' : '0');
        Setting::set('payment_bank_transfer_enabled', $this->payment_bank_transfer_enabled ? '1' : '0');

        Setting::set('connectips_merchant_id', trim($this->connectips_merchant_id));
        Setting::set('connectips_app_id', trim($this->connectips_app_id));
        Setting::set('connectips_app_name', trim($this->connectips_app_name));

        Setting::set('esewa_id', trim($this->esewa_id));
        Setting::set('esewa_account_name', trim($this->esewa_account_name));

        Setting::set('bank_name', trim($this->bank_name));
        Setting::set('bank_account_name', trim($this->bank_account_name));
        Setting::set('bank_account_number', trim($this->bank_account_number));
        Setting::set('bank_branch', trim($this->bank_branch));

        // 3. Shipping
        Setting::set('shipping_origin_country', trim($this->shipping_origin_country));
        Setting::set('shipping_origin_city', trim($this->shipping_origin_city));
        Setting::set('shipping_origin_postal_code', trim($this->shipping_origin_postal_code));
        Setting::set('shipping_origin_address', trim($this->shipping_origin_address));
        Setting::set('default_courier', trim($this->default_courier));
        Setting::set('shipping_free_threshold_npr', (string) max(0, $this->shipping_free_threshold_npr));
        Setting::set('standard_shipping_rate_npr', (string) max(0, $this->standard_shipping_rate_npr));
        Setting::set('outside_valley_shipping_rate_npr', (string) max(0, $this->outside_valley_shipping_rate_npr));
        Setting::set('express_shipping_rate_npr', (string) max(0, $this->express_shipping_rate_npr));
        Setting::set('packaging_notes', trim($this->packaging_notes));

        // 4. Tax & Currency (Strictly NPR & Nepal IRD 13%)
        Setting::set('vat_enabled', $this->vat_enabled ? '1' : '0');
        Setting::set('default_vat_rate', (string) max(0, $this->default_vat_rate));
        Setting::set('vat_number_prefix', trim($this->vat_number_prefix));
        Setting::set('display_prices_with_vat', $this->display_prices_with_vat ? '1' : '0');
        Setting::set('tax_calculation_mode', $this->tax_calculation_mode);
        Setting::set('tax_display_label', trim($this->tax_display_label));

        // 5. Mail
        Setting::set('smtp_provider', trim($this->smtp_provider));
        Setting::set('smtp_host', trim($this->smtp_host));
        Setting::set('smtp_port', trim($this->smtp_port));
        Setting::set('smtp_encryption', trim($this->smtp_encryption));
        Setting::set('smtp_username', trim($this->smtp_username));
        Setting::set('smtp_from_address', trim($this->smtp_from_address));
        Setting::set('smtp_from_name', trim($this->smtp_from_name));
        Setting::set('smtp_reply_to', trim($this->smtp_reply_to));

        if ($isSuperAdmin) {
            if (!Setting::isSecretMasked($this->smtp_password) && !empty($this->smtp_password)) {
                Setting::setSecret('smtp_password', $this->smtp_password);
                SettingAuditLog::logChange('smtp_password', '***', '***');
            }
            $this->smtp_password = Setting::maskSecret(Setting::getSecret('smtp_password'));
        }

        Setting::set('mail_event_order_confirmation', $this->mail_event_order_confirmation ? '1' : '0');
        Setting::set('mail_event_order_shipped', $this->mail_event_order_shipped ? '1' : '0');
        Setting::set('mail_event_order_delivered', $this->mail_event_order_delivered ? '1' : '0');
        Setting::set('mail_event_order_cancelled', $this->mail_event_order_cancelled ? '1' : '0');
        Setting::set('mail_event_customer_registration', $this->mail_event_customer_registration ? '1' : '0');
        Setting::set('mail_event_contact_notification', $this->mail_event_contact_notification ? '1' : '0');
        Setting::set('mail_event_admin_order_notification', $this->mail_event_admin_order_notification ? '1' : '0');

        // 6. Support & Socials
        Setting::set('whatsapp_welcome_message', trim($this->whatsapp_welcome_message));
        Setting::set('concierge_widget_enabled', $this->concierge_widget_enabled ? '1' : '0');
        Setting::set('concierge_hours', trim($this->concierge_hours));
        Setting::set('concierge_response_time', trim($this->concierge_response_time));
        Setting::set('concierge_cta_text', trim($this->concierge_cta_text));

        // Persist validated social links
        $cleanSocials = [];
        foreach ($this->social_links as $i => $link) {
            if (!empty($link['platform']) && !empty($link['url']) && filter_var($link['url'], FILTER_VALIDATE_URL)) {
                $cleanSocials[] = [
                    'platform' => trim($link['platform']),
                    'url' => trim($link['url']),
                    'display_label' => trim($link['display_label'] ?? $link['platform']),
                    'icon_identifier' => strtolower(trim($link['icon_identifier'] ?? $link['platform'])),
                    'enabled' => !empty($link['enabled']),
                    'sort_order' => (int) ($link['sort_order'] ?? ($i + 1)),
                ];
            }
        }
        Setting::set('social_links', json_encode($cleanSocials));

        // 7. Maintenance & System (Super Admin only for maintenance controls)
        if ($isSuperAdmin) {
            $oldMaint = Setting::get('maintenance_mode');
            Setting::set('maintenance_mode', $this->maintenance_mode ? '1' : '0');
            if ($oldMaint !== ($this->maintenance_mode ? '1' : '0')) {
                SettingAuditLog::logChange('maintenance_mode', $oldMaint ? 'active' : 'inactive', $this->maintenance_mode ? 'active' : 'inactive', 'toggled');
            }

            Setting::set('maintenance_message', trim($this->maintenance_message));
            Setting::set('maintenance_expected_return', trim($this->maintenance_expected_return));
            Setting::set('maintenance_allowed_ips', trim($this->maintenance_allowed_ips));
            Setting::set('maintenance_contact_email', trim($this->maintenance_contact_email));

            Setting::set('feature_registration_enabled', $this->feature_registration_enabled ? '1' : '0');
            Setting::set('feature_guest_checkout_enabled', $this->feature_guest_checkout_enabled ? '1' : '0');
            Setting::set('feature_customer_accounts_enabled', $this->feature_customer_accounts_enabled ? '1' : '0');
            Setting::set('feature_wishlist_enabled', $this->feature_wishlist_enabled ? '1' : '0');
            Setting::set('feature_reviews_enabled', $this->feature_reviews_enabled ? '1' : '0');
            Setting::set('feature_google_login_enabled', $this->feature_google_login_enabled ? '1' : '0');
            Setting::set('feature_newsletter_enabled', $this->feature_newsletter_enabled ? '1' : '0');
        }

        Notification::make()
            ->title('Settings Saved Successfully')
            ->body('All configurations, rates, Nepal channels, and settings updated.')
            ->success()
            ->send();
    }

    public function purgeCache(): void
    {
        $user = static::getAuthenticatedUser();
        if ($user && method_exists($user, 'isSuperAdmin') && !$user->isSuperAdmin()) {
            Notification::make()
                ->title('Permission Denied')
                ->body('Only Super Administrators can purge application caches.')
                ->danger()
                ->send();
            return;
        }

        try {
            Artisan::call('optimize:clear');

            Notification::make()
                ->title('System Cache Purged')
                ->body('Application, view, route, and config caches refreshed.')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Cache Purge Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function optimizeStorageLink(): void
    {
        $user = static::getAuthenticatedUser();
        if ($user && method_exists($user, 'isSuperAdmin') && !$user->isSuperAdmin()) {
            Notification::make()
                ->title('Permission Denied')
                ->body('Only Super Administrators can manage system storage links.')
                ->danger()
                ->send();
            return;
        }

        try {
            Artisan::call('storage:link', ['--relative' => true, '--force' => true]);

            Notification::make()
                ->title('Public Storage Synchronized')
                ->body('Public storage symlink verified and active.')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Storage Sync Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
