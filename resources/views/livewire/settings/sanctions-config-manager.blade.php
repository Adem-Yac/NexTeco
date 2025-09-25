<div>
    <div class="space-y-6">
        <!-- Statistiques rapides -->
        <div class="grid grid-cols-3 gap-4">
            <div class="bg-orange-50 dark:bg-orange-900/20 rounded-lg p-4">
                <div class="text-2xl font-bold text-orange-600 dark:text-orange-400">
                    {{ \App\Models\SanctionsConfig::count() }}
                </div>
                <div class="text-sm text-orange-600 dark:text-orange-400">
                    Nombre total de règles
                </div>
            </div>
            <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4">
                <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">
                    {{ \App\Models\SanctionsConfig::where('type', 'retards')->count() }}
                </div>
                <div class="text-sm text-yellow-600 dark:text-yellow-400">
                    Règles de retard
                </div>
            </div>
            <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4">
                <div class="text-2xl font-bold text-red-600 dark:text-red-400">
                    {{ \App\Models\SanctionsConfig::where('type', 'absences')->count() }}
                </div>
                <div class="text-sm text-red-600 dark:text-red-400">
                    Règles d'absence
                </div>
            </div>
        </div>

        <!-- Boîte d'information -->
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <x-heroicon-o-information-circle class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5" />
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                        Fonctionnement des sanctions
                    </h3>
                    <div class="mt-2 text-sm text-blue-700 dark:text-blue-300 space-y-1">
                        <p>Pour chaque seuil atteint, une sanction est appliquée automatiquement.</p>
                        <p>Exemple avec 22 absences et ces règles :</p>
                        <ul class="list-disc ml-5">
                            <li>10 absences = 10h déduites</li>
                            <li>3 absences = 4h déduites</li>
                            <li>1 absence = 3h déduites</li>
                        </ul>
                        <p class="mt-2">Avec 22 absences :</p>
                        <ul class="list-decimal ml-5">
                            <li>22 ≥ 10 → -10h (reste 12)</li>
                            <li>12 ≥ 10 → -10h (reste 2)</li>
                            <li>2 ≥ 1 → -3h (reste 1)</li>
                            <li>1 ≥ 1 → -3h (reste 0)</li>
                        </ul>
                        <p class="mt-2"><strong>Total : 26h déduites</strong></p>
                        <ul class="list-disc ml-5 mt-2">
                            <li>Plus il y a d’absences, plus la sanction est forte</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tableau -->
        <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
            {{ $this->table }}
        </div>
    </div>
</div>
