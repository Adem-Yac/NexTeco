<div>
    <div class="space-y-6">
        <!-- Statistiques rapides -->
        <div class="grid grid-cols-2 gap-4">
            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                    {{ \App\Models\Holiday::count() }}
                </div>
                <div class="text-sm text-blue-600 dark:text-blue-400">
                    Nombre total de jours fériés
                </div>
            </div>
            <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                    {{ \App\Models\Holiday::where('is_recurring', true)->count() }}
                </div>
                <div class="text-sm text-green-600 dark:text-green-400">
                    Jours fériés récurrents
                </div>
            </div>
        </div>

        <!-- Tableau -->
        <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
            {{ $this->table }}
        </div>
    </div>
</div>
