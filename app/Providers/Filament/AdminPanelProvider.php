<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('intadmin')
            ->authGuard('admin')
            ->login()
            ->colors([
                'primary' => '#001b48', // Official Laijau Logo Navy
                'gray' => Color::Zinc,
            ])
            ->brandName('Laijau')
            ->brandLogo(fn() => asset('images/logo.png'))
            ->darkModeBrandLogo(fn() => asset('images/logo-gold.png'))
            ->brandLogoHeight('2.5rem')
            ->favicon(fn() => asset('favicon.ico'))
            ->maxContentWidth('full')
            ->sidebarCollapsibleOnDesktop()
            ->renderHook(
                \Filament\View\PanelsRenderHook::HEAD_END,
                fn(): string => '<link rel="stylesheet" href="/css/laijau-admin-v2.css?v=' . (file_exists(public_path('css/laijau-admin-v2.css')) ? filemtime(public_path('css/laijau-admin-v2.css')) : '2.0') . '">' .
                    '<link rel="stylesheet" href="/css/nepal-accounting.css?v=' . (file_exists(public_path('css/nepal-accounting.css')) ? filemtime(public_path('css/nepal-accounting.css')) : '4.0') . '">' .
                    (auth('admin')->check() ? '<link rel="manifest" href="/admin.webmanifest">' : '')
            )
            ->renderHook(
                \Filament\View\PanelsRenderHook::BODY_END,
                fn(): string => '<script src="/js/retail-keyboard-shortcuts.js?v=' . (file_exists(public_path('js/retail-keyboard-shortcuts.js')) ? filemtime(public_path('js/retail-keyboard-shortcuts.js')) : '2.0') . '" defer></script>'
            )
            ->renderHook(
                \Filament\View\PanelsRenderHook::BODY_END,
                fn(): string => view('filament.components.mobile-bottom-nav')->render()
            )
            ->renderHook(
                \Filament\View\PanelsRenderHook::BODY_END,
                fn(): string => view('filament.components.admin-pwa-install')->render()
            )
            ->navigationGroups([
                \Filament\Navigation\NavigationGroup::make('Sales'),
                \Filament\Navigation\NavigationGroup::make('Products'),
                \Filament\Navigation\NavigationGroup::make('Inventory'),
                \Filament\Navigation\NavigationGroup::make('Fulfillment'),
                \Filament\Navigation\NavigationGroup::make('CRM'),
                \Filament\Navigation\NavigationGroup::make('Finance'),
                \Filament\Navigation\NavigationGroup::make('People'),
                \Filament\Navigation\NavigationGroup::make('Settings'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make()
                    ->registerNavigation(false),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
