<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\StoreSettingsService;
use App\Services\CurrencyService;
use App\Services\PaymentSettingsService;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function index(
        StoreSettingsService $storeService,
        PaymentSettingsService $paymentService,
        CurrencyService $currencyService
    ): JsonResponse {
        $brand = $storeService->getBrandIdentity();
        $positioning = $storeService->getNepalPositioning();
        $announcement = $storeService->getAnnouncementBar();
        $seo = $storeService->getSeoIdentity();
        $socials = $storeService->getSocialLinks();
        $availablePaymentMethods = $paymentService->getAvailablePaymentMethods();

        return response()->json([
            // Store Brand & Positioning
            'store_name' => $brand['store_name'],
            'store_short_name' => $brand['store_short_name'],
            'store_tagline' => $brand['store_tagline'],
            'store_description' => $brand['store_description'],
            'legal_entity_name' => $brand['legal_entity_name'],
            'store_logo_url' => $brand['logo_url'],
            'support_email' => $brand['support_email'],
            'support_phone' => $brand['support_phone'],
            'whatsapp_number' => $brand['whatsapp_number'],
            'whatsapp_welcome_message' => Setting::get('whatsapp_welcome_message', "Namaste! Welcome to Laijau. How may our support team assist you today?"),
            'support_widget_enabled' => (bool) Setting::get('concierge_widget_enabled', true),
            'support_hours' => Setting::get('concierge_hours', 'Sun – Fri: 10:00 – 19:00 NPT'),
            'support_response_time' => Setting::get('concierge_response_time', 'Typically responds within 1 hour'),
            'showroom_address' => $brand['showroom_address'],
            'business_city' => $brand['business_city'],
            'business_country' => $brand['business_country'],
            'business_postal_code' => $brand['business_postal_code'],
            'business_registration_number' => $brand['business_registration_number'],
            'dispatch_origin_hub' => $positioning['dispatch_origin_hub'],
            'primary_market' => $positioning['primary_market'],
            'service_region' => $positioning['service_region'],
            'default_locale' => $positioning['default_locale'],
            'nepal_delivery_message' => $positioning['nepal_delivery_message'],

            // Announcement Bar
            'announcement_bar' => $announcement,

            // SEO Metadata
            'seo' => $seo,

            // Currency
            'default_currency' => Setting::get('default_currency', 'NPR'),
            'supported_currencies' => ['NPR'],

            // Nepal Delivery & Shipping Thresholds
            'shipping_valley_flat_rate' => (float) Setting::get('shipping_valley_flat_rate', 100.00),
            'shipping_outside_valley_flat_rate' => (float) Setting::get('shipping_outside_valley_flat_rate', 150.00),
            'shipping_free_threshold_npr' => (float) Setting::get('shipping_free_threshold_npr', 5000.00),
            'default_courier' => Setting::get('default_courier', 'Pathao (Valley) & Nepal Can Move (Nationwide)'),

            // Social Channels
            'social_links' => $socials,

            // Taxes & VAT (Nepal IRD Statutory 13%)
            'vat_enabled' => (bool) Setting::get('vat_enabled', true),
            'default_vat_rate' => (float) Setting::get('default_vat_rate', 13.00),
            'display_prices_with_vat' => (bool) Setting::get('display_prices_with_vat', true),
            'tax_calculation_mode' => 'nepal_single_rate_13',
            'tax_display_label' => Setting::get('tax_display_label', '13% VAT Included'),

            // Payments (Nepal Domestic Only)
            'payment_cod_enabled' => (bool) Setting::get('payment_cod_enabled', true),
            'payment_connectips_enabled' => (bool) Setting::get('payment_connectips_enabled', true),
            'payment_esewa_enabled' => (bool) Setting::get('payment_esewa_enabled', true),
            'available_payment_methods' => array_values($availablePaymentMethods),

            // Maintenance Status
            'maintenance_mode' => (bool) Setting::get('maintenance_mode', false),
            'maintenance_message' => Setting::get('maintenance_message', 'Our online store is currently undergoing scheduled maintenance. We will return shortly.'),
            'maintenance_expected_return' => Setting::get('maintenance_expected_return', 'Shortly'),

            // Tracking Identifiers
            'gtm_id' => Setting::get('gtm_id'),
            'ga4_measurement_id' => Setting::get('ga4_measurement_id'),
            'google_ads_conversion_id' => Setting::get('google_ads_conversion_id'),
            'google_ads_conversion_label' => Setting::get('google_ads_conversion_label'),
            'facebook_pixel_id' => Setting::get('facebook_pixel_id'),
        ])->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=600');
    }
}
