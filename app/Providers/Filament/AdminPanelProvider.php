<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
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
            ->path('admin')
            ->login()
            /*
             * Authentication guard vs. authorization guard.
             *
             * config/auth.php only exposes one session-capable guard (`web`),
             * so that is what the panel's login form and Authenticate
             * middleware must use — the `api` guard is token driven and cannot
             * back a browser session.
             *
             * Role and permission rows, however, are stored under spatie's
             * `api` guard label. Those two are decoupled on purpose:
             * App\Models\User::guardName() pins permission resolution to `api`,
             * so hasRole()/hasPermissionTo()/can() answer correctly no matter
             * which guard established the session. Stating the auth guard here
             * makes the split explicit instead of relying on the framework
             * default.
             */
            ->authGuard('web')
            ->brandName('Car Tracker Ops')
            ->brandLogo(fn () => view('filament.admin.brand'))
            ->brandLogoHeight('1.6rem')
            /*
             * Amber used to be this panel's primary colour, which meant a link,
             * a submit button and a warning all looked the same. On a product
             * whose whole job is telling drivers that something is about to
             * lapse, amber is the one colour that has to keep meaning
             * "attention" — so the chrome moved to petrol and amber went back
             * to `warning`, which is already Filament's default for it. The
             * neutrals are Slate rather than the default Zinc so they sit under
             * the petrol instead of fighting it. Nothing else is overridden:
             * danger, warning, success and info are left at Filament's own
             * ramps so the semantic colours stay the ones people recognise.
             *
             * Worth knowing about Color::hex(): it keeps only the *hue* of the
             * colour given and regenerates lightness and chroma from a fixed
             * ramp. #106E7C is therefore a hue selector, not the colour that
             * ships — it renders as #0097b8 at shade 600. That is why
             * App\Filament\Support\DashboardPalette stores the generated hex
             * values rather than these: Chart.js is handed literal colours and
             * cannot read the CSS variables this produces.
             */
            ->colors([
                'primary' => Color::hex('#106E7C'),
                'gray' => Color::Slate,
            ])
            /*
             * Space Grotesk and Space Mono are a designed pair, which is the
             * point: the mono face is not a fallback here, it carries every
             * reading on the dashboard (see resources/views/filament/admin/styles.blade.php).
             * Both are served by the Bunny provider Filament defaults to for
             * custom families, and the CSS keeps a system stack behind them.
             */
            ->font('Space Grotesk')
            ->monoFont('Space Mono')
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn () => view('filament.admin.styles'),
            )
            /*
             * Fourteen resources is more than a flat list can carry. The groups
             * are declared here, rather than left to be discovered, so they
             * appear in the order an operator works in — the fleet and the
             * records drivers create first, the lookup tables an admin curates
             * next, and account administration last — and so each one gets an
             * icon in the collapsed sidebar. Membership is declared on each
             * resource's $navigationGroup.
             */
            ->navigationGroups([
                NavigationGroup::make('Fleet')
                    ->icon('heroicon-o-truck'),
                NavigationGroup::make('Logbook')
                    ->icon('heroicon-o-book-open'),
                NavigationGroup::make('Maintenance')
                    ->icon('heroicon-o-wrench-screwdriver'),
                NavigationGroup::make('Reference')
                    ->icon('heroicon-o-table-cells'),
                NavigationGroup::make('Access')
                    ->icon('heroicon-o-lock-closed'),
            ])
            ->sidebarCollapsibleOnDesktop()
            ->unsavedChangesAlerts()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            /*
             * Widgets are discovered, and each one gates itself on an existing
             * `index-{subject}` permission through
             * App\Filament\Concerns\AuthorizesWidgetWithApiPermission. Filament's
             * own AccountWidget and FilamentInfoWidget are not registered: the
             * account is already in the user menu, and the dashboard should
             * open on the fleet rather than on the framework's own branding.
             */
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
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
