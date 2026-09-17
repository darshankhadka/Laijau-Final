@php
    $seo = app(\App\Services\StoreSettingsService::class)->getSeoIdentity();
@endphp
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', $seo['site_title'])</title>
    <meta name="description" content="@yield('description', $seo['meta_description'])">
    <meta name="keywords" content="@yield('keywords', $seo['keywords'])">
    <link rel="canonical" href="@yield('canonical', url()->current())">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:title" content="@yield('title', $seo['site_title'])">
    <meta property="og:description" content="@yield('description', $seo['meta_description'])">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="@yield('og_image', $seo['og_image'])">

    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title', $seo['site_title'])">
    <meta name="twitter:description" content="@yield('description', $seo['meta_description'])">
    <meta name="twitter:image" content="@yield('og_image', $seo['og_image'])">

    <!-- Favicons -->
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <meta name="theme-color" content="#ffffff">

    <!-- Preload Critical Local Web Fonts (Inter & Outfit) -->
    <link rel="preload" href="/fonts/storefront/inter-400.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/storefront/outfit-700.woff2" as="font" type="font/woff2" crossorigin>

    <!-- Authoritative Server Authentication State -->
    <script>
        window.__LAIJAU_AUTH_USER__ = @json(auth('web')->user());
    </script>

    <!-- Vite Assets (Tailwind v4 CSS + Alpine.js Store) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('head')
</head>
<body class="flex flex-col min-h-screen bg-gray-50 text-gray-900 antialiased pb-[calc(4.5rem+env(safe-area-inset-bottom,0px))] lg:pb-0 font-sans">
    <!-- Header -->
    <x-storefront.header />

    <!-- Cart Drawer Modal -->
    <x-storefront.cart-drawer />

    <!-- Instant Search Modal -->
    <x-storefront.search-modal />

    <!-- GDPR Cookie Consent Dialog -->
    <x-storefront.cookie-consent />

    <!-- Concierge WhatsApp Floating Button -->
    <x-storefront.whatsapp />

    <!-- Main Page Content -->
    <main class="flex-1 flex flex-col">
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    <!-- Footer -->
    <x-storefront.footer />

    <!-- Mobile Ergonomic Bottom Navigation -->
    <x-storefront.mobile-nav />

    <!-- Public Storefront PWA Install Banner -->
    <x-storefront.pwa-install />

    @stack('scripts')
</body>
</html>
