<x-filament::page> 
    <div class="mb-6 flex gap-3">
        <button wire:click="switchTab('employes')" @class([
            'px-4 py-2 rounded transition-colors',
            'bg-primary-600 text-white' => $activeTab === 'employes',
            'bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-100' => $activeTab !== 'employes',
        ])>
            Employés
        </button>
        <button wire:click="switchTab('grhs')" @class([
            'px-4 py-2 rounded transition-colors',
            'bg-primary-600 text-white' => $activeTab === 'grhs',
            'bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-100' => $activeTab !== 'grhs',
        ])>
            GRH
        </button>
    </div>

    @if (isset($this) && property_exists($this, 'table'))
        {{ $this->table }}
    @endif

    @if(!empty($payslip_preview))
        @php
            $ficheDetails = isset($ficheDetails) ? $ficheDetails : (isset($payslip_preview['fiche_details']) ? $payslip_preview['fiche_details'] : []);
            $calculatePayslips = isset($calculatePayslips) ? $calculatePayslips : new \App\Actions\CalculatePayslips();
        @endphp

    <x-filament::section heading="Fiche de Paie - Résumé" class="mt-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- General Information -->
                <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg shadow-sm">
                    <h3 class="text-lg font-semibold mb-3 text-gray-800 dark:text-gray-100">Informations Générales</h3>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="font-medium">Référence:</dt>
                            <dd>{{ $ficheDetails['id'] ?? 'N/A' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="font-medium">Période:</dt>
                            <dd>{{ isset($payslip_preview['mois']) ? \Carbon\Carbon::parse($payslip_preview['mois'] . '-01')->locale('fr')->isoFormat('MMMM YYYY') : 'N/A' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="font-medium">Employé:</dt>
                            <dd>{{ $payslip_preview['employe'] ?? 'N/A' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="font-medium">Poste:</dt>
                            <dd>{{ $payslip_preview['poste'] ?? 'N/A' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="font-medium">Département:</dt>
                            <dd>{{ $payslip_preview['departement'] ?? 'N/A' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="font-medium">Shift:</dt>
                            <dd>{{ $payslip_preview['shift'] ?? 'N/A' }}</dd>
                        </div>
                        @if(isset($this))
                            <div class="flex justify-between">
                                <dt class="font-medium">Statut:</dt>
                                <dd>{{ isset($this) && method_exists($this, 'translateStatus') ? $this->translateStatus($ficheDetails['status'] ?? 'en_attente') : ($ficheDetails['status'] ?? 'en_attente') }}</dd>
                            </div>
                        @endif
                        <div class="flex justify-between">
                            <dt class="font-medium">Mode de calcul:</dt>
                            <dd>{{ $payslip_preview['mode_calculation'] ?? 'N/A' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="font-medium">Date de génération:</dt>
                            <dd>{{ $ficheDetails['date_generation'] ?? now()->format('d/m/Y H:i') }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Work Summary -->
                <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg shadow-sm">
                    <h3 class="text-lg font-semibold mb-3 text-gray-800 dark:text-gray-100">Résumé du Travail</h3>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="font-medium">Heures Théoriques:</dt>
                            <dd>{{ $payslip_preview['heures_theoriques'] ?? '0,00' }} h</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="font-medium">Heures Travaillées:</dt>
                            <dd>{{ $payslip_preview['heures_travaillees'] ?? '0,00' }} h</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="font-medium">Heures Supplémentaires:</dt>
                            <dd class="font-semibold">{{ $payslip_preview['heures_sup'] ?? '0,00' }} h</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="font-medium">Retards:</dt>
                            <dd>{{ $payslip_preview['retards'] ?? '0' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="font-medium">Absences:</dt>
                            <dd>{{ $payslip_preview['absences'] ?? '0' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="font-medium">Jours fériés:</dt>
                            <dd>{{ $payslip_preview['holidays'] ?? '0' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Sanctions Details (if any) -->
            @if(($payslip_preview['retards_hours_deducted'] ?? 0) > 0 || ($payslip_preview['absences_hours_deducted'] ?? 0) > 0)
                <div class="bg-red-50 dark:bg-red-900 p-4 rounded-lg shadow-sm mb-6 border border-red-200 dark:border-red-700">
                    <h3 class="text-lg font-semibold mb-3 text-red-800 dark:text-red-200">Sanctions Appliquées</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        @if(($payslip_preview['retards_hours_deducted'] ?? 0) > 0)
                            <div class="flex justify-between">
                                <span class="font-medium">Heures déduites (retards):</span>
                                <span>{{ $payslip_preview['retards_hours_deducted'] }} h</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium">Montant déduit (retards):</span>
                                <span>{{ $payslip_preview['retards_deduction'] }} DZD</span>
                            </div>
                        @endif
                        @if(($payslip_preview['absences_hours_deducted'] ?? 0) > 0)
                            <div class="flex justify-between">
                                <span class="font-medium">Heures déduites (absences):</span>
                                <span>{{ $payslip_preview['absences_hours_deducted'] }} h</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium">Montant déduit (absences):</span>
                                <span>{{ $payslip_preview['absences_deduction'] }} DZD</span>
                            </div>
                        @endif
                        <div class="flex justify-between font-semibold col-span-full border-t pt-2">
                            <span>Total déductions:</span>
                            <span>{{ $payslip_preview['total_deductions'] }} DZD</span>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Financial Details -->
            <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg shadow-sm mb-6">
                <h3 class="text-lg font-semibold mb-3 text-gray-800 dark:text-gray-100">Détails Financiers</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div class="flex justify-between">
                        <span class="font-medium">Salaire de Base:</span>
                        <span>{{ $payslip_preview['salaire_base'] }} DZD</span>
                    </div>
                    @if($payslip_preview['mode_calculation'] === 'mensuel' && isset($payslip_preview['salaire_mensuel']))
                        <div class="flex justify-between">
                            <span class="font-medium">Salaire Mensuel:</span>
                            <span>{{ $payslip_preview['salaire_mensuel'] }} DZD</span>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <span class="font-medium">Montant Base:</span>
                        <span>{{ $payslip_preview['montant_base'] }} DZD</span>
                    </div>
                    @if(isset($ficheDetails['taux_horaire_sup']) && $ficheDetails['taux_horaire_sup'])
                        <div class="flex justify-between">
                            <span class="font-medium">Taux Heure Sup:</span>
                            <span>{{ $ficheDetails['taux_horaire_sup'] }}x</span>
                        </div>
                    @endif
                    @if(isset($ficheDetails['montant_heures_sup']) && $ficheDetails['montant_heures_sup'])
                        <div class="flex justify-between">
                            <span class="font-medium">Montant Heures Sup:</span>
                            <span>{{ $ficheDetails['montant_heures_sup'] }} DZD</span>
                        </div>
                    @endif
                    @if(isset($ficheDetails['prime']) && $ficheDetails['prime'])
                        <div class="flex justify-between">
                            <span class="font-medium">Primes:</span>
                            <span>{{ $ficheDetails['prime'] }} DZD</span>
                        </div>
                    @endif
                    @if(isset($ficheDetails['avance']) && $ficheDetails['avance'])
                        <div class="flex justify-between">
                            <span class="font-medium">Avances:</span>
                            <span>{{ $ficheDetails['avance'] }} DZD</span>
                        </div>
                    @endif
                    <div class="flex justify-between font-semibold text-gray-800 dark:text-gray-100 col-span-full border-t pt-2">
                        <span>Montant Total:</span>
                        <span>{{ $ficheDetails['montant'] }} DZD</span>
                    </div>
                </div>
            </div>

            <!-- Presence Details -->
            @if(isset($payslip_preview['presences']) && count($payslip_preview['presences']))
                <div class="overflow-x-auto mb-6">
                    <h3 class="text-lg font-semibold mb-3 text-gray-800">Détail des Présences</h3>
                    <table class="min-w-full text-xs border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
                        <thead class="bg-gray-100 dark:bg-gray-700">
                            <tr>
                                <th class="px-3 py-2 border text-left">Date</th>
                                <th class="px-3 py-2 border text-left">Jour</th>
                                <th class="px-3 py-2 border text-left">Type</th>
                                <th class="px-3 py-2 border text-left">Heures</th>
                                <th class="px-3 py-2 border text-left">Arrivée</th>
                                <th class="px-3 py-2 border text-left">Départ</th>
                                <th class="px-3 py-2 border text-left">État</th>
                                <th class="px-3 py-2 border text-left">Anomalie</th>
                                <th class="px-3 py-2 border text-left">Résolue</th>
                                <th class="px-3 py-2 border text-left">Statut Global</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($payslip_preview['presences'] as $presence)
                                @php
                                    $date = \Carbon\Carbon::parse($presence['date']);
                                    $isWeekend = $presence['is_weekend'] ?? false;
                                    $isHoliday = $presence['is_holiday'] ?? false;
                                    $anomalieResolue = isset($presence['anomalie_resolue']) && $presence['anomalie_resolue'] ? 'Oui' : 'Non';
                                    $etat = $calculatePayslips->translateCheckStatus($presence['etat'] ?? null);
                                    $anomalieDisplay = $calculatePayslips->translateAnomalyType($presence['anomalie_type'] ?? null);
                                    $status = $calculatePayslips->determinePresenceStatus($presence);
                                    $etatColor = ($presence['etat'] === 'present' ? '#39B54A' : ($presence['etat'] === 'absent' || $presence['etat'] === 'retard' ? '#EF4444' : 'inherit'));
                                    $statusColor = (isset($presence['anomalie_type']) && !isset($presence['anomalie_resolue']) || (isset($presence['anomalie_resolue']) && !$presence['anomalie_resolue'])) ? '#EF4444' : 'inherit';
                                @endphp
                                <tr class="{{ $isWeekend ? 'bg-gray-50 dark:bg-gray-800' : ($isHoliday ? 'bg-green-50 dark:bg-green-900' : (floatval(str_replace(',', '.', $presence['duree'])) == 0 ? 'text-red-600 dark:text-red-400' : '')) }}">
                                    <td class="px-3 py-2 border border-gray-200 dark:border-gray-700">{{ $date->format('d/m/Y') }}</td>
                                    <td class="px-3 py-2 border border-gray-200 dark:border-gray-700">{{ $date->locale('fr')->isoFormat('dddd') }}</td>
                                    <td class="px-3 py-2 border border-gray-200 dark:border-gray-700">
                                        @if($isHoliday)
                                            <span class="text-green-600 font-medium">Jour férié</span>
                                        @elseif($isWeekend)
                                            Week-end
                                        @else
                                            Jour ouvré
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 border border-gray-200 dark:border-gray-700">{{ $presence['duree'] }} h</td>
                                    <td class="px-3 py-2 border border-gray-200 dark:border-gray-700">{{ $presence['check_in'] ?? '—' }}</td>
                                    <td class="px-3 py-2 border border-gray-200 dark:border-gray-700">{{ $presence['check_out'] ?? '—' }}</td>
                                    <td class="px-3 py-2 border border-gray-200 dark:border-gray-700" style="color: {{ $etatColor }};">{{ $etat }}</td>
                                    <td class="px-3 py-2 border border-gray-200 dark:border-gray-700">{{ $anomalieDisplay }}</td>
                                    <td class="px-3 py-2 border border-gray-200 dark:border-gray-700">{{ $presence['anomalie_type'] ? $anomalieResolue : '—' }}</td>
                                    <td class="px-3 py-2 border border-gray-200 dark:border-gray-700" style="color: {{ $statusColor }};">{{ $status }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <!-- Export Buttons -->
            <div class="flex justify-end gap-3">
                <button wire:click="exportCsv" type="button"
                     class="bg-primary-600 hover:bg-primary-500 text-white px-4 py-2 rounded text-sm transition-colors">
                    Exporter en CSV
                </button>
                <button wire:click="exportPdf" type="button"
                     class="bg-primary-600 hover:bg-primary-500 text-white px-4 py-2 rounded text-sm transition-colors">
                    Exporter en PDF
                </button>
            </div>
        </x-filament::section>
    @endif
</x-filament::page>