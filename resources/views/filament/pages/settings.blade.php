<x-filament-panels::page>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Widget Jours Fériés -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Gestion des jours fériés
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Gérez les jours fériés et événements récurrents de l'entreprise
                        </p>
                    </div>
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center">
                            <x-heroicon-o-calendar-days class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                        </div>
                    </div>
                </div>
            </div>
            <div class="p-6">
                @livewire('settings.holiday-manager')
            </div>
        </div>

        <!-- Widget Configuration des Sanctions -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Configuration des sanctions
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Configurez les pénalités pour les retards et les absences
                        </p>
                    </div>
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-red-100 dark:bg-red-900 rounded-lg flex items-center justify-center">
                            <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-red-600 dark:text-red-400" />
                        </div>
                    </div>
                </div>
            </div>
            <div class="p-6">
                @livewire('settings.sanctions-config-manager')
            </div>
        </div>
    </div>
</x-filament-panels::page>
