<?php

namespace App\Providers\Filament;

use AchyutN\FilamentLogViewer\FilamentLogViewer;
use App\Filament\Admin\Pages\Auth\Login;
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
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
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
                'primary' => Color::Amber,
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
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->defaultThemeMode(ThemeMode::Dark)
            ->darkMode(true, isForced: true)
            ->discoverClusters(in: app_path('Filament/Admin/Clusters'), for: 'App\\Filament\\Admin\\Clusters')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\\Filament\\Admin\\Widgets')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
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
                NavigationGroup::make()->label(fn () => __('admin.navigation.user')),
                NavigationGroup::make()->label(fn () => __('admin.navigation.settings'))->collapsed(),
            ])
            ->plugins([
                FilamentLogViewer::make()
                    ->navigationGroup(__('admin.navigation.settings')),
                FilamentEditProfilePlugin::make()
                    ->slug('my-profile')
                    ->setTitle(__('admin.profile.title'))
                    ->setNavigationLabel(__('admin.profile.title'))
                    ->setNavigationGroup(__('admin.navigation.user'))
                    ->setIcon('heroicon-o-user')
                    ->setSort(10)
                    ->shouldRegisterNavigation(false)
                    ->shouldShowEmailForm()
                    ->shouldShowLocaleForm(options: [
                        'pt_BR' => __('🇧🇷 Português'),
                        'en' => __('🇺🇸 Inglês'),
                        'es' => __('🇪🇸 Espanhol'),
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
