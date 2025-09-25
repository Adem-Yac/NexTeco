<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use App\Filament\Resources\FicheDePaieResource;
use App\Filament\Resources\DispositifBiometriqueResource;
use App\Filament\Pages\Settings;

class GrhPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('grh')
            ->path('grh')
            ->authGuard('grh') // Use the custom guard
            ->default()
            ->login()
            ->favicon(asset('favicon.ico'))
            ->brandName('NextTeco')
            ->colors([
                'primary' => '#5D5FEF',
            ])
            ->resources([
                FicheDePaieResource::class,
                \App\Filament\Resources\DemandeCongeResource::class,
                \App\Filament\Resources\ReclamationResource::class,
                DispositifBiometriqueResource::class,
                \App\Filament\Resources\PresenceResource::class,
                \App\Filament\Resources\PointagesResource::class,
                \App\Filament\Resources\EmployeResource::class,
                \App\Filament\Resources\FicheDePaieResource::class,
                \App\Filament\Resources\DepartementResource::class,
                \App\Filament\Resources\ShiftResource::class,
                \App\Filament\Resources\AvanceResource::class,
                \App\Filament\Resources\PrimeResource::class,
            ])


            ->discoverResources(in: app_path('Filament/Grh/Resources'), for: 'App\\Filament\\Grh\\Resources')
            ->discoverPages(in: app_path('Filament/Grh/Pages'), for: 'App\\Filament\\Grh\\Pages')
            ->pages([
                Pages\Dashboard::class,
                Settings::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
            
                \App\Filament\Widgets\EmployeeStatsOverview::class,
                \App\Filament\Widgets\AttendanceChart::class,
                \App\Filament\Widgets\DepartmentDistributionChart::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
