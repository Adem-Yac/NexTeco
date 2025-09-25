@props([
    'navigation',
])

<div class="sticky top-0 z-20 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-b dark:border-gray-700">
    <nav class="flex items-center justify-end h-16 px-4 md:px-8">
        <!-- Droite : Sélecteur de langue, notifications, utilisateur -->
        <div class="flex items-center space-x-12">
            
            <!-- Notifications (Filament Livewire) -->
            @if (filament()->auth()->check() && filament()->hasDatabaseNotifications())
                @livewire(Filament\Livewire\DatabaseNotifications::class, [
                    'lazy' => filament()->hasLazyLoadedDatabaseNotifications(),
                ])
            @endif
            <!-- Utilisateur -->
            @if (filament()->auth()->check())
                <x-filament-panels::user-menu :class="'h-12 w-12'" />
            @endif
        </div>
    </nav>
</div>
