<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use AchyutN\FilamentLogViewer\FilamentLogViewer;
use App\Filament\Admin\Pages\Auth\Login;
use App\Models\Admin;
use DutchCodingCompany\FilamentDeveloperLogins\FilamentDeveloperLoginsPlugin;
use Filament\Actions\Action;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Enums\Width;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use JeffersonGoncalves\Filament\Admin\AdminPlugin;
use JeffersonGoncalves\Filament\Gtag\GtagPlugin;
use JeffersonGoncalves\Filament\Gtm\GtmPlugin;
use JeffersonGoncalves\Filament\OneTimeOperations\OneTimeOperationsPlugin;
use JeffersonGoncalves\Filament\PageVisits\FilamentPageVisitsPlugin;
use JeffersonGoncalves\Filament\Pwa\FilamentPwaPlugin;
use JeffersonGoncalves\Filament\ScannerGuard\ScannerGuardPlugin;
use JeffersonGoncalves\Filament\ShortUrl\FilamentShortUrlPlugin;
use JeffersonGoncalves\Filament\User\UserPlugin;
use JeffersonGoncalves\FilamentEditorialTheme\EditorialThemePlugin;
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
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->authGuard('admin')
            ->brandLogo(fn () => view('filament.admin.logo'))
            ->favicon(asset('favicon.ico'))
            ->defaultThemeMode(ThemeMode::System)
            ->darkMode(true)
            ->maxContentWidth(Width::Full)
            ->sidebarCollapsibleOnDesktop()
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
                // Editorial Terminal theme (jeffersongoncalves/filament-editorial-theme): palettes,
                // footer, sidebar status and external links. The login stays App\Filament\Admin\Pages\Auth\Login
                // (filament-admin's status check) and only borrows the theme's view.
                EditorialThemePlugin::make()
                    ->footerCopyright(fn (): string => '© '.date('Y').' · Jefferson Gonçalves · Assis/SP')
                    ->footerRight(fn (): string => (string) config('app.name')),
                // One-click login as any active admin, local environment only.
                FilamentDeveloperLoginsPlugin::make()
                    ->enabled(fn (): bool => app()->environment('local'))
                    ->modelClass(Admin::class)
                    ->users(fn (): array => Admin::query()->where('status', true)->orderBy('name')->pluck('email', 'name')->all()),
                // Admin / User resources (jeffersongoncalves/filament-admin, filament-user).
                AdminPlugin::make()
                    ->navigationGroup(__('admin.navigation.user')),
                // The App panel (/app, the impersonation target) is disabled here.
                UserPlugin::make()
                    ->navigationGroup(__('admin.navigation.user'))
                    ->withoutImpersonation(),
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
                ScannerGuardPlugin::make()
                    ->navigationGroup(__('admin.navigation.settings')),
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
