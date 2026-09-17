<?php

namespace App\Services;

use App\Models\Setting;

class StoreSettingsService
{
    /**
     * Get Laijau brand identity configuration with safe defaults.
     */
    public function getBrandIdentity(): array
    {
        return [
            'store_name' => Setting::get('store_name', 'Laijau'),
            'store_short_name' => Setting::get('store_short_name', 'Laijau'),
            'store_tagline' => Setting::get('store_tagline', "Nepal's Premier Retail & E-Commerce Destination"),
            'store_description' => Setting::get('store_description', "Laijau is Nepal's premier retail and ecommerce platform, offering curated footwear, apparel, and lifestyle essentials with Cash on Delivery in Kathmandu Valley and fast courier dispatch nationwide."),
            'legal_entity_name' => Setting::get('legal_entity_name', 'Delta Nine business group'),
            'business_registration_number' => Setting::get('business_registration_number', '604335148'),
            'brand_established_year' => Setting::get('brand_established_year', '2016'),
            'brand_email' => Setting::get('brand_email', 'info@laijau.com'),
            'support_email' => Setting::get('support_email', 'info@laijau.com'),
            'support_phone' => Setting::get('support_phone', '9843512095'),
            'whatsapp_number' => Setting::get('whatsapp_number', '9843512095'),
            'showroom_address' => Setting::get('showroom_address', "Bohara Tol, Kageshwori Manahara 09\nKathmandu, Nepal"),
            'business_city' => Setting::get('business_city', 'Kathmandu'),
            'business_country' => Setting::get('business_country', 'Nepal'),
            'business_postal_code' => Setting::get('business_postal_code', '44600'),
            'timezone' => Setting::get('store_timezone', config('app.timezone', 'Asia/Kathmandu')),
            'logo_url' => $this->resolveMediaUrl(Setting::get('store_logo_url'), asset('images/logo.png')),
            'logo_gold_url' => $this->resolveMediaUrl(Setting::get('store_logo_gold_url'), asset('images/logo-gold.png')),
            'favicon_url' => $this->resolveMediaUrl(Setting::get('store_favicon_url'), asset('favicon.ico')),
            'og_image_url' => $this->resolveMediaUrl(Setting::get('store_og_image_url'), asset('images/logo-1x1.png')),
        ];
    }

    /**
     * Get Nepal market positioning metadata.
     */
    public function getNepalPositioning(): array
    {
        return [
            'dispatch_origin_hub' => Setting::get('dispatch_origin_hub', 'Laijau Central Fulfillment Center, Kathmandu, Nepal'),
            'primary_market' => Setting::get('primary_market', 'Nepal (All 7 Provinces)'),
            'service_region' => Setting::get('service_region', 'Kathmandu Valley & Nationwide Nepal'),
            'default_locale' => Setting::get('default_locale', 'en_NP'),
            'default_currency' => Setting::get('default_currency', 'NPR'),
            'nepal_delivery_message' => Setting::get('nepal_delivery_message', 'Dispatched directly from our Kathmandu fulfillment center with COD inside the Valley and trusted courier delivery across Nepal.'),
        ];
    }

    /**
     * Get announcement bar configuration.
     */
    public function getAnnouncementBar(): array
    {
        return [
            'enabled' => (bool) Setting::get('announcement_enabled', true),
            'text' => Setting::get('announcement_text', '✨ Cash on Delivery Available in Kathmandu Valley • Fast Nationwide Delivery • WhatsApp Order Support: 9843512095'),
            'link' => Setting::get('announcement_link', '/products'),
            'cta' => Setting::get('announcement_cta', 'Shop Now'),
            'show_vat_messaging' => false,
            'show_free_shipping_messaging' => (bool) Setting::get('show_free_shipping_messaging', true),
            'show_delivery_messaging' => (bool) Setting::get('show_delivery_messaging', true),
        ];
    }

    /**
     * Get SEO identity and default meta tags.
     */
    public function getSeoIdentity(): array
    {
        $brand = $this->getBrandIdentity();

        return [
            'site_title' => Setting::get('seo_site_title', "Laijau | Nepal's Premier Retail & E-Commerce Destination"),
            'meta_description' => Setting::get('seo_meta_description', $brand['store_description']),
            'keywords' => Setting::get('seo_keywords', 'Laijau, laijau.com, Nepal shopping, online shopping Nepal, Kathmandu shoes, footwear Nepal, sneakers Kathmandu, boots Nepal, Cash on Delivery Kathmandu, eSewa shopping, connectIPS'),
            'canonical_base_url' => Setting::get('seo_canonical_base_url', config('app.url', 'https://laijau.com')),
            'org_name' => Setting::get('seo_org_name', 'Laijau'),
            'org_logo' => $brand['logo_url'],
            'og_image' => Setting::get('seo_og_image', $brand['og_image_url']),
        ];
    }

    /**
     * Get CRUD-ready social platforms list.
     */
    public function getSocialLinks(): array
    {
        $raw = Setting::get('social_links');

        if (!empty($raw)) {
            $links = is_string($raw) ? json_decode($raw, true) : $raw;
            if (is_array($links)) {
                $filtered = array_filter($links, function ($link) {
                    return !empty($link['enabled']) && !empty($link['url']) && filter_var($link['url'], FILTER_VALIDATE_URL);
                });

                usort($filtered, fn($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));
                return array_values($filtered);
            }
        }

        return [
            [
                'platform' => 'Instagram',
                'url' => 'https://instagram.com/laijau.nepal',
                'display_label' => '@laijau.nepal',
                'icon_identifier' => 'instagram',
                'enabled' => true,
                'sort_order' => 1,
            ],
            [
                'platform' => 'Facebook',
                'url' => 'https://facebook.com/laijau.nepal',
                'display_label' => 'laijau.nepal',
                'icon_identifier' => 'facebook',
                'enabled' => true,
                'sort_order' => 2,
            ],
            [
                'platform' => 'TikTok',
                'url' => 'https://tiktok.com/@laijau.com',
                'display_label' => '@laijau.com',
                'icon_identifier' => 'tiktok',
                'enabled' => true,
                'sort_order' => 3,
            ],
        ];
    }

    /**
     * Helper to resolve media URLs safely.
     */
    protected function resolveMediaUrl(?string $path, string $fallback): string
    {
        if (empty($path)) {
            return $fallback;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return asset('storage/' . ltrim($path, '/'));
    }
}
