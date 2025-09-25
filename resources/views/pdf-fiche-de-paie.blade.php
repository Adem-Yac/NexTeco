<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fiche de Paie</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            font-size: 20px;
            margin: 15px; 
            line-height: 1.3; 
        }
        .container { 
            max-width: 1000px; 
            margin: 0 auto; 
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .section { 
            margin-bottom: 15px; 
            page-break-inside: avoid;
        }
        .section h2 { 
            font-size: 27px;
            font-weight: bold; 
            margin-bottom: 10px; 
            color: #333; 
            background-color: #f5f5f5;
            padding: 13px;
            border: 1px solid #ddd;
        }
        .section h3 { 
            font-size: 24px;
            font-weight: bold; 
            margin-bottom: 13px;
            color: #333; 
        }
        
        /* Grille pour la première page - 4 sections côte à côte */
        .first-page-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-template-rows: 1fr 1fr;
            gap: 15px;
            height: 100vh;
            margin-bottom: 20px;
        }
        
        .grid-section {
            border: 1px solid #ddd;
            padding: 10px;
            background: #fafafa;
            border-radius: 5px;
        }
        
        .grid-section h2 {
            font-size: 24px;
            margin-bottom: 13px;
            background-color: #e9ecef;
            padding: 10px;
        }
        
        .grid-section .info-table {
            font-size: 17px;
        }
        
        .grid-section .info-table td {
            padding: 3px 5px;
        }
        
        .grid-section .info-table .label {
            width: 50%;
        }
        
        .grid { 
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }
        .grid-row {
            display: table-row;
        }
        .grid-cell {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding: 5px;
        }
        .card { 
            background: #f8f8f8; 
            padding: 10px; 
            border-radius: 6px; 
            border: 1px solid #ddd;
            margin-bottom: 10px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 4px 8px;
            border: 1px solid #ddd;
        }
        .info-table .label {
            background-color: #f9f9f9;
            font-weight: bold;
            width: 40%;
        }
        .sanctions-section {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            padding: 10px;
            margin-bottom: 15px;
        }
        .sanctions-title {
            color: #dc2626;
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .table { 
            width: 100%; 
            border-collapse: collapse; 
            background: #fff; 
            font-size: 18px;
            margin-top: 17px; 
        }
        .table th, .table td { 
            border: 1px solid #ddd; 
            padding: 6px; 
            text-align: left; 
        }
        .table th { 
            background: #f1f1f1; 
            font-weight: bold; 
        }
        .text-red { color: #dc2626; }
        .text-blue { color: #2563eb; }
        .text-yellow { color: #d97706; }
        .text-green { color: #16a34a; }
        .bg-gray { background: #f9fafb; }
        .bg-holiday { background: #f0fdf4; }
        .total-row {
            font-weight: bold;
            background-color: #f5f5f5;
        }
        
        /* Styles pour les sauts de page */
        .page-break {
            page-break-before: always;
        }
        
        /* Numérotation des pages */
        .page-number {
            position: fixed;
            bottom: 34px;
            right: 34px; 
            font-size: 17px; 
            color: #666;
        }
        
        /* Ajustements pour éviter les coupures de contenu */
        .section {
            page-break-inside: avoid;
        }
        
        .table {
            page-break-inside: avoid;
        }
        
        /* Tableau des présences optimisé pour toute la page */
        .presence-table {
            width: 100%;
            font-size: 18px;
        }
        
        .presence-table th, .presence-table td {
            padding: 4px;
            border: 1px solid #ddd;
        }
        
        .presence-table th {
            background: #e9ecef;
            font-weight: bold;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        @if(!empty($payslip_preview))
            @php
                $ficheDetails = isset($ficheDetails) ? $ficheDetails : (isset($payslip_preview['fiche_details']) ? $payslip_preview['fiche_details'] : []);
                $calculatePayslips = isset($calculatePayslips) ? $calculatePayslips : new \App\Actions\CalculatePayslips();
            @endphp

            <!-- PAGE 1: 4 sections divisées -->
            <div class="section">
                <div class="header">
                    <h1>FICHE DE PAIE</h1>
                    <p><strong>Référence:</strong> {{ $ficheDetails['id'] ?? 'N/A' }}</p>
                    <p><strong>Période:</strong> {{ isset($payslip_preview['mois']) ? \Carbon\Carbon::parse($payslip_preview['mois'] . '-01')->locale('fr')->isoFormat('MMMM YYYY') : 'N/A' }}</p>
                </div>

                <div class="first-page-grid">
                    <!-- Section 1: INFORMATIONS GÉNÉRALES -->
                    <div class="grid-section">
                        <h2>INFORMATIONS GÉNÉRALES</h2>
                        <table class="info-table">
                            <tr>
                                <td class="label">Employé</td>
                                <td>{{ $payslip_preview['employe'] ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td class="label">Poste</td>
                                <td>{{ $payslip_preview['poste'] ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td class="label">Département</td>
                                <td>{{ $payslip_preview['departement'] ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td class="label">Shift</td>
                                <td>{{ $payslip_preview['shift'] ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td class="label">Mode de calcul</td>
                                <td>{{ $payslip_preview['mode_calculation'] === 'mensuel' ? 'Mensuel' : 'Horaire' }}</td>
                            </tr>
                            <tr>
                                <td class="label">Date de génération</td>
                                <td>{{ $ficheDetails['date_generation'] ?? now()->format('d/m/Y H:i') }}</td>
                            </tr>
                        </table>
                    </div>

                    <!-- Section 2: RÉSUMÉ DU TRAVAIL -->
                    <div class="grid-section">
                        <h2>RÉSUMÉ DU TRAVAIL</h2>
                        <table class="info-table">
                            <tr>
                                <td class="label">Heures théoriques</td>
                                <td>{{ $payslip_preview['heures_theoriques'] ?? '0,00' }} h</td>
                            </tr>
                            <tr>
                                <td class="label">Heures travaillées</td>
                                <td>{{ $payslip_preview['heures_travaillees'] ?? '0,00' }} h</td>
                            </tr>
                            <tr>
                                <td class="label">Heures supplémentaires</td>
                                <td class="text-blue">{{ $payslip_preview['heures_sup'] ?? '0,00' }} h</td>
                            </tr>
                            <tr>
                                <td class="label">Retards</td>
                                <td class="text-yellow">{{ $payslip_preview['retards'] ?? '0' }}</td>
                            </tr>
                            <tr>
                                <td class="label">Absences</td>
                                <td class="text-red">{{ $payslip_preview['absences'] ?? '0' }}</td>
                            </tr>
                            <tr>
                                <td class="label">Jours fériés</td>
                                <td class="text-green">{{ $payslip_preview['holidays'] ?? '0' }}</td>
                            </tr>
                        </table>
                    </div>

                    <!-- Section 3: SANCTIONS APPLIQUÉES -->
                    <div class="grid-section">
                        <h2>SANCTIONS APPLIQUÉES</h2>
                        @if(($payslip_preview['retards_hours_deducted'] ?? 0) > 0 || ($payslip_preview['absences_hours_deducted'] ?? 0) > 0)
                            <table class="info-table">
                                @if(($payslip_preview['retards_hours_deducted'] ?? 0) > 0)
                                    <tr>
                                        <td class="label">Heures déduites (retards)</td>
                                        <td class="text-red">{{ $payslip_preview['retards_hours_deducted'] }} h</td>
                                    </tr>
                                    <tr>
                                        <td class="label">Montant déduit (retards)</td>
                                        <td class="text-red">{{ $payslip_preview['retards_deduction'] }} DZD</td>
                                    </tr>
                                @endif
                                @if(($payslip_preview['absences_hours_deducted'] ?? 0) > 0)
                                    <tr>
                                        <td class="label">Heures déduites (absences)</td>
                                        <td class="text-red">{{ $payslip_preview['absences_hours_deducted'] }} h</td>
                                    </tr>
                                    <tr>
                                        <td class="label">Montant déduit (absences)</td>
                                        <td class="text-red">{{ $payslip_preview['absences_deduction'] }} DZD</td>
                                    </tr>
                                @endif
                                <tr class="total-row">
                                    <td class="label">Total déductions</td>
                                    <td class="text-red">{{ $payslip_preview['total_deductions'] }} DZD</td>
                                </tr>
                            </table>
                        @else
                            <p style="color: #666; font-style: italic;">Aucune sanction appliquée</p>
                        @endif
                    </div>

                    <!-- Section 4: DÉTAILS FINANCIERS -->
                    <div class="grid-section">
                        <h2>DÉTAILS FINANCIERS</h2>
                        <table class="info-table">
                            <tr>
                                <td class="label">Salaire de base</td>
                                <td>{{ $payslip_preview['salaire_base'] }} DZD</td>
                            </tr>
                            @if($payslip_preview['mode_calculation'] === 'mensuel' && isset($payslip_preview['salaire_mensuel']))
                                <tr>
                                    <td class="label">Salaire mensuel</td>
                                    <td>{{ $payslip_preview['salaire_mensuel'] }} DZD</td>
                                </tr>
                            @endif
                            <tr>
                                <td class="label">Montant base</td>
                                <td>{{ $payslip_preview['montant_base'] }} DZD</td>
                            </tr>
                            @if(isset($ficheDetails['taux_horaire_sup']) && $ficheDetails['taux_horaire_sup'])
                                <tr>
                                    <td class="label">Taux horaire supplémentaire</td>
                                    <td>{{ $ficheDetails['taux_horaire_sup'] }}x</td>
                                </tr>
                            @endif
                            @if(isset($ficheDetails['montant_heures_sup']) && $ficheDetails['montant_heures_sup'])
                                <tr>
                                    <td class="label">Montant heures supplémentaires</td>
                                    <td>{{ $ficheDetails['montant_heures_sup'] }} DZD</td>
                                </tr>
                            @endif
                            @if(isset($ficheDetails['prime']) && $ficheDetails['prime'])
                                <tr>
                                    <td class="label">Primes</td>
                                    <td>{{ $ficheDetails['prime'] }} DZD</td>
                                </tr>
                            @endif
                            @if(isset($ficheDetails['avance']) && $ficheDetails['avance'])
                                <tr>
                                    <td class="label">Avances</td>
                                    <td>{{ $ficheDetails['avance'] }} DZD</td>
                                </tr>
                            @endif
                            <tr class="total-row">
                                <td class="label">MONTANT TOTAL</td>
                                <td>{{ $ficheDetails['montant'] }} DZD</td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="page-number">Page 1</div>
            </div>

            <!-- PAGE 2: DÉTAIL DES PRÉSENCES sur toute la page -->
            @if(isset($payslip_preview['presences']) && count($payslip_preview['presences']))
                <div class="section page-break">
                    <h2>DÉTAIL DES PRÉSENCES</h2>
                    <table class="presence-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Jour</th>
                                <th>Type</th>
                                <th>Heures</th>
                                <th>Arrivée</th>
                                <th>Départ</th>
                                <th>État</th>
                                <th>Anomalie</th>
                                <th>Résolue</th>
                                <th>Statut Global</th>
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
                                @endphp
                                <tr class="{{ $isWeekend ? 'bg-gray' : ($isHoliday ? 'bg-holiday' : (floatval(str_replace(',', '.', $presence['duree'])) == 0 ? 'text-red' : '')) }}">
                                    <td>{{ $date->format('d/m/Y') }}</td>
                                    <td>{{ $date->locale('fr')->isoFormat('ddd') }}</td>
                                    <td>
                                        @if($isHoliday)
                                            <span class="text-green">Férié</span>
                                        @elseif($isWeekend)
                                            WE
                                        @else
                                            JO
                                        @endif
                                    </td>
                                    <td>{{ $presence['duree'] }} h</td>
                                    <td>{{ $presence['check_in'] ?? '—' }}</td>
                                    <td>{{ $presence['check_out'] ?? '—' }}</td>
                                    <td class="{{ $presence['etat'] === 'retard' ? 'text-yellow' : '' }}">{{ $etat }}</td>
                                    <td>{{ $anomalieDisplay }}</td>
                                    <td>{{ $presence['anomalie_type'] ? $anomalieResolue : '—' }}</td>
                                    <td>{{ $status }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    
                    <div class="page-number">Page 2</div>
                </div>
            @endif
        @endif
    </div>
</body>
</html>