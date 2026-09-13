<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use AchyutN\FilamentLogViewer\FilamentLogViewer;
use App\Filament\Admin\Pages\Auth\Login;
use Filament\Actions\Action;
use Filament\Enums\ThemeMode;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Vite;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use JeffersonGoncalves\Filament\Gtag\GtagPlugin;
use JeffersonGoncalves\Filament\Gtm\GtmPlugin;
use JeffersonGoncalves\Filament\OneTimeOperations\OneTimeOperationsPlugin;
use JeffersonGoncalves\Filament\PageVisits\FilamentPageVisitsPlugin;
use JeffersonGoncalves\Filament\Pwa\FilamentPwaPlugin;
use JeffersonGoncalves\Filament\ScannerGuard\ScannerGuardPlugin;
use JeffersonGoncalves\Filament\ShortUrl\FilamentShortUrlPlugin;
use Joaopaulolndev\FilamentEditProfile\FilamentEditProfilePlugin;
use Joaopaulolndev\FilamentEditProfile\Pages\EditProfilePage;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->authGuard('admin')
            ->colors([
                'primary' => [
                    50 => '#FFFBEB',
                    100 => '#FEF3C7',
                    200 => '#FDE68A',
                    300 => '#FCD34D',
                    400 => '#FBBF24',
                    500 => '#F59E0B',
                    600 => '#D97706',
                    700 => '#B45309',
                    800 => '#92400E',
                    900 => '#78350F',
                    950 => '#451A03',
                ],
                'gray' => [
                    50 => '#F8F5EE',
                    100 => '#F0EBDF',
                    200 => '#D9D2C5',
                    300 => '#B8B0A4',
                    400 => '#8B8377',
                    500 => '#5C5349',
                    600 => '#3D362F',
                    700 => '#2A2620',
                    800 => '#1F1B17',
                    900 => '#13110E',
                    950 => '#0B0A09',
                ],
            ])
            ->brandLogo(fn () => view('filament.admin.logo'))
            ->favicon(asset('favicon.ico'))
            ->font('DM Sans', url: Vite::asset('resources/css/fonts/dm-sans.css'), provider: LocalFontProvider::class)
            ->monoFont('JetBrains Mono', url: Vite::asset('resources/css/fonts/jetbrains-mono.css'), provider: LocalFontProvider::class)
            ->serifFont('Fraunces', url: Vite::asset('resources/css/fonts/fraunces.css'), provider: LocalFontProvider::class)
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->defaultThemeMode(ThemeMode::System)
            ->darkMode(true)
            ->maxContentWidth(Width::Full)
            ->sidebarCollapsibleOnDesktop()
            ->renderHook(
                PanelsRenderHook::SIDEBAR_FOOTER,
                fn () => view('filament.partials.sidebar-status'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('filament.partials.external-links'),
            )
            ->renderHook(
                PanelsRenderHook::FOOTER,
                fn () => view('filament.partials.footer'),
            )
            ->discoverClusters(in: app_path('Filament/Admin/Clusters'), for: 'App\\Filament\\Admin\\Clusters')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\\Filament\\Admin\\Widgets')
            ->pages([
                Pages\Dashboard::class,
            ])
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
            ->authMiddleware([
                Authenticate::class,
            ])
            ->navigationGroups([
                NavigationGroup::make()->label(fn () => __('admin.navigation.management')),
                NavigationGroup::make()->label(fn () => __('admin.navigation.links')),
                NavigationGroup::make()->label(fn () => __('admin.navigation.analytics')),
                NavigationGroup::make()->label(fn () => __('admin.navigation.user')),
                NavigationGroup::make()->label(fn () => __('admin.navigation.settings'))->collapsed(),
            ])
            ->plugins([
                // Injects manifest link + theme-color + apple-touch-icon links
                // into the panel <head>. theme-color mirrors the dark-default /
                // light-cookie logic that components.favicon used to carry, so
                // switching the source doesn't flip the mobile address-bar tint.
                FilamentPwaPlugin::make()
                    ->themeColor(request()->cookie('theme') === 'light' ? '#FFFEF9' : '#0B0A09'),
                FilamentLogViewer::make()
                    ->navigationGroup(__('admin.navigation.settings')),
                OneTimeOperationsPlugin::make(),
                // Short links (jeffersongoncalves/laravel-short-url admin UI).
                // The redirect route is registered by the core package as the
                // app *fallback* (SHORT_URL_ROUTE_FALLBACK=true in .env), so every
                // site route wins over a short key sitting at the root.
                FilamentShortUrlPlugin::make()
                    ->navigationGroup(__('admin.navigation.links'))
                    ->navigationIcon('heroicon-o-link')
                    ->navigationSort(30)
                    // Links here are minted by App\Support\OutboundLink, one per
                    // outbound destination — nothing to organise by hand and
                    // nothing to bulk-import, so the organisation surface is off.
                    ->hidePixels()
                    ->hideFolders()
                    ->hideTags()
                    ->hideImport(),
                // Read-only page-visit browser (jeffersongoncalves/laravel-page-visits
                // data — device/browser/geo/locale/referer/UTM per pageview).
                FilamentPageVisitsPlugin::make()
                    ->navigationGroup(__('admin.navigation.analytics')),
                // Vulnerability-scanner ban list (jeffersongoncalves/laravel-scanner-guard).
                // No fluent nav-group setter on this plugin — the resource
                // isn't grouped under one of the panel's NavigationGroups yet.
                ScannerGuardPlugin::make(),
                GtmPlugin::make(),
                GtagPlugin::make(),
                FilamentEditProfilePlugin::make()
                    ->slug('my-profile')
                    ->setTitle(__('admin.profile.title'))
                    ->setNavigationLabel(__('admin.profile.title'))
                    ->setNavigationGroup(__('admin.navigation.user'))
                    ->setIcon('heroicon-o-user')
                    ->setSort(10)
                    ->shouldRegisterNavigation(false)
                    ->shouldShowEmailForm()
                    // Endonyms (each language in its own name) — intentionally
                    // not translated, so the selector reads the same regardless
                    // of the admin's current interface locale.
                    ->shouldShowLocaleForm(options: [
                        'pt_BR' => '🇧🇷 Português',
                        'en' => '🇺🇸 English',
                        'es' => '🇪🇸 Español',
                    ])
                    ->shouldShowSanctumTokens()
                    ->shouldShowMultiFactorAuthentication()
                    ->shouldShowBrowserSessionsForm()
                    ->shouldShowAvatarForm(),
            ])
            ->userMenuItems([
                'profile' => Action::make('profile')
                    ->label(fn (): string => __('admin.profile.title'))
                    ->url(fn (): string => EditProfilePage::getUrl())
                    ->icon('heroicon-m-user-circle'),
            ])
            ->unsavedChangesAlerts()
            ->profile()
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s');
    }
}
