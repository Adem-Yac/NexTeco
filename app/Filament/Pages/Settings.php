<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Settings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'paramètres sanctions';
    protected static ?string $title = 'paramètres sanctions';
    protected static string $view = 'filament.pages.settings';
    protected static ?int $navigationSort = 100;

    // Hide from sidebar
    protected static bool $shouldRegisterNavigation = false;

    public function getWidgets(): array
    {
        return [
            \App\Livewire\Settings\HolidayManager::class,
            \App\Livewire\Settings\SanctionsConfigManager::class,
        ];
    }
}