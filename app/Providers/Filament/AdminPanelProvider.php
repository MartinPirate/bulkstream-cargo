<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\DemoLogin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(DemoLogin::class)
            ->brandName('Bulkstream · Cargo Handling')
            ->brandLogo(fn () => new HtmlString('<span class="bs-wordmark">bulk<span>stream</span></span>'))
            ->brandLogoHeight('1.75rem')
            ->colors([
                'primary' => array_replace(Color::Indigo, [
                    500 => Color::convertToOklch('#3446a8'),
                    600 => Color::convertToOklch('#25348c'),
                    700 => Color::convertToOklch('#1e2b75'),
                ]),
                'gray' => Color::Zinc,
            ])
            ->darkMode(false)
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn (): string => '<style>'.file_get_contents(resource_path('css/bulkstream-theme.css')).'</style>')
            ->navigationGroups(['Operations', 'Terminal setup', 'Administration'])
            ->sidebarCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
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
            ]);
    }
}
