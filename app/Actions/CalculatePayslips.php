<?php

namespace App\Actions;

use App\Models\demande_conge;
use App\Models\Employe;
use App\Models\fiche_de_paie;
use App\Models\Grh;
use App\Models\Holiday;
use App\Models\Presence;
use App\Models\SanctionsConfig;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use DateTime;

class PayslipCalculationException extends Exception {}
class PayslipBusinessException extends Exception {}

class CalculatePayslips
{
    /**
     * Génère une fiche de paie totalement indépendante.
     * @param string $userType 'employe' ou 'grh'
     * @param int $userId
     * @param float|null $tauxHoraireSup
     * @param float|null $montantHeuresSup
     * @param string|null $mois format 'Y-m'
     * @param string $modeCalculation 'horaire' ou 'mensuel'
     * @param array|null $sanctionsConfig Configuration des sanctions (retards, absences) - deprecated, now uses DB
     * @return fiche_de_paie
     * @throws PayslipCalculationException|PayslipBusinessException
     */

    public function run(
        string $userType,
        int $userId,
        ?float $tauxHoraireSup = null,
        ?float $montantHeuresSup = null,
        ?string $mois = null,
        string $modeCalculation = 'horaire',
    ): fiche_de_paie {
        try {
            Log::info("[Fiche de paie] Début du traitement fiche de paie pour $userType #$userId, mois $mois, mode $modeCalculation");

            // Validation des paramètres
            $this->validateBasicParams($userType, $userId, $tauxHoraireSup, $montantHeuresSup, $modeCalculation);

            // Récupération et validation de l'utilisateur
            $user = $this->getValidatedUser($userType, $userId, $modeCalculation);
            $shift = $user->shift ?? null;
            $salaire = floatval($user->salaire);
            $salaireMensuel = $user->salaire_mensuel !== null ? floatval($user->salaire_mensuel) : null;

            // Validation du shift
            $this->validateShift($shift);

            // Vérification du format du mois
            if ($mois === null) {
                $mois = Carbon::now()->subMonth()->format('Y-m');
            }
            if (!preg_match('/^\d{4}-\d{2}$/', $mois)) {
                throw new PayslipBusinessException("Format du mois invalide (attendu: YYYY-MM)");
            }

            // Récupération des présences du mois
            $start = Carbon::parse($mois . '-01');
            $end = $start->copy()->endOfMonth();
            $presences = Presence::where('user_type', $userType)
                ->where('user_id', $userId)
                ->whereBetween('date', [$start, $end])
                ->where('anomalie_resolue', true)
                ->get();

            // Récupération des congés approuvés
            $conges = $this->getApprovedLeaves($userType, $userId, $start, $end);

            // Récupération des jours fériés
            Log::info("[Fiche de paie] Avant getHolidaysInRange pour période {$start->format('Y-m-d')} à {$end->format('Y-m-d')}");
            $holidays = Holiday::getHolidaysInRange($start, $end);
            Log::info("[Fiche de paie] Après getHolidaysInRange, type=" . get_class($holidays) . ", contenu=" . json_encode($holidays));

            // Vérification absences/retards
            Log::info("[Fiche de paie] Avant calculateHolidayDays");
            $joursOuvres = $this->getBusinessDaysInMonth($start, $end, $shift);
            $joursPointes = $presences->pluck('date')->unique()->count();
            $joursConges = $this->calculateDaysOnLeave($conges, $start, $end, $shift);
            $joursFeries = $this->calculateHolidayDays($holidays, $start, $end, $shift);
            Log::info("[Fiche de paie] $joursFeries jours fériés trouvés dans la période");

            if ($joursPointes + $joursConges + $joursFeries === 0) {
                throw new PayslipBusinessException("Aucune présence, congé ou jour férié enregistré pour ce mois.");
            }

            // Initialisation des variables
            $dureeStandard = $this->getDureeStandard($shift);
            $breakDuration = $shift && $shift->pause && $shift->duree_pause ? ($shift->duree_pause / 60.0) : 0.0;
            $heuresParJour = [];
            $heuresSup = 0;
            $totalHeures = 0;

            // Créer un tableau indexé par date
            $currentDate = clone $start;
            while ($currentDate->lte($end)) {
                $dateStr = $currentDate->format('Y-m-d');
                $heuresParJour[$dateStr] = 0;
                $currentDate->addDay();
            }

            // Calcul des heures travaillées
            foreach ($presences as $presence) {
                $dateStr = $presence->date->format('Y-m-d');
                $heuresJour = $this->parseTimeToHours($presence->heures_travaillees);

                $heuresEffective = $heuresJour;
                if ($breakDuration > 0 && $heuresJour >= $dureeStandard + $breakDuration) {
                    $heuresEffective -= $breakDuration;
                }

                $heuresParJour[$dateStr] = $heuresEffective;

                $heuresSupJour = max(0, $heuresEffective - $dureeStandard);
                $heuresSup += $heuresSupJour;
                $totalHeures += $heuresEffective;
            }

            // Ajouter les heures pour congés (AVANT les jours fériés pour éviter le doublonnage)
            $totalHeures += $this->addLeaveHours($conges, $start, $end, $shift, $dureeStandard, $heuresParJour);

            // Ajouter les heures pour jours fériés (seulement si pas déjà en congé)
            $totalHeures += $this->addHolidayHours($holidays, $start, $end, $shift, $dureeStandard, $heuresParJour);

            // Calcul des sanctions (retards et absences) - now uses database config
            $sanctionsData = $this->calculateSanctions($presences, $joursOuvres, $joursPointes, $joursConges, $joursFeries, $salaire, $salaireMensuel, $modeCalculation);

            // Calcul du salaire de base
            if ($modeCalculation === 'mensuel') {
                $montantBase = $salaireMensuel - $sanctionsData['total_deductions'];
                Log::info("[Fiche de paie] Mode mensuel : salaireMensuel=$salaireMensuel, total_déductions={$sanctionsData['total_deductions']}, montantBase=$montantBase");
            } else {
                $heuresNormales = $totalHeures - $heuresSup;
                $montantBase = round($salaire * $heuresNormales, 2) - $sanctionsData['total_deductions'];
                Log::info("[Fiche de paie] Mode horaire : heuresNormales=$heuresNormales, salaire=$salaire, total_déductions={$sanctionsData['total_deductions']}, montantBase=$montantBase");
            }

            // Calcul des heures supplémentaires
            if ($heuresSup > 0) {
                if ($montantHeuresSup !== null) {
                    $montantSup = $montantHeuresSup;
                } else {
                    $tauxSup = $tauxHoraireSup ?? ($shift && $shift->taux_horaire_sup ? floatval($shift->taux_horaire_sup) : 1.5);
                    // Base hourly rate for overtime
                    $tauxHoraire = $modeCalculation === 'mensuel'
                        ? ($salaire > 0 ? $salaire : ($salaireMensuel / ($joursOuvres * $dureeStandard)))
                        : $salaire;
                    $montantSup = round($heuresSup * $tauxHoraire * $tauxSup, 2);
                }
            } else {
                $montantSup = 0.0;
            }
            Log::info("[Fiche de paie] Heures supplémentaires : heuresSup=$heuresSup, montantSup=$montantSup");

            // Récupération des primes et avances
            $primes = $this->getPrimes($user, $mois) ?? 0.0;
            $avances = $this->getAvances($user, $mois) ?? 0.0;
            Log::info("[Fiche de paie] Primes=$primes, Avance=$avances");

            // Création de la fiche de paie
            return $this->createPayslip(
                $userType,
                $userId,
                $mois,
                $montantBase,
                $montantSup,
                $heuresSup,
                $primes,
                $avances,
                $tauxHoraireSup,
                $modeCalculation,
                $sanctionsData
            );
        } catch (PayslipBusinessException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error("[Fiche de paie] Erreur inattendue : " . $e->getMessage());
            throw new PayslipCalculationException("Une erreur est survenue lors du calcul: " . $e->getMessage());
        }
    }

    /**
     * Add leave hours to total worked hours
     * IMPORTANT: Cette méthode doit être appelée AVANT addHolidayHours pour éviter le doublonnage
     */
    protected function addLeaveHours($conges, Carbon $start, Carbon $end, $shift, float $dureeStandard, array &$heuresParJour): float
    {
        $totalLeaveHours = 0;

        Log::info("[Fiche de paie] Traitement de " . count($conges) . " congés approuvés");
        foreach ($conges as $conge) {
            $debutConge = max($start, Carbon::parse($conge->date_debut));
            $finConge = min($end, Carbon::parse($conge->date_fin));

            Log::info("[Fiche de paie] Traitement du congé ID={$conge->id} : période ajustée de {$debutConge->format('Y-m-d')} à {$finConge->format('Y-m-d')}");

            $jourConge = clone $debutConge;
            while ($jourConge->lte($finConge)) {
                $dateStr = $jourConge->format('Y-m-d');
                $carbonDate = $this->ensureCarbon($jourConge);

                if ($this->isWorkingDay($carbonDate, $shift)) {
                    if ($heuresParJour[$dateStr] == 0) {
                        $heuresParJour[$dateStr] = $dureeStandard;
                        $totalLeaveHours += $dureeStandard;
                        Log::info("[Fiche de paie] Jour de congé ajouté : {$dateStr} = {$dureeStandard}h");
                    } else {
                        Log::info("[Fiche de paie] Jour de congé ignoré (déjà travaillé) : {$dateStr}");
                    }
                } else {
                    Log::info("[Fiche de paie] Jour de congé ignoré (jour non travaillé) : {$dateStr}");
                }

                $jourConge->addDay();
            }
        }

        Log::info("[Fiche de paie] Total heures congés ajoutées : $totalLeaveHours");
        return $totalLeaveHours;
    }

    /**
     * Add holiday hours to total worked hours
     * IMPORTANT: Cette méthode doit être appelée APRÈS addLeaveHours pour éviter le doublonnage
     */
    protected function addHolidayHours($holidays, Carbon $start, Carbon $end, $shift, float $dureeStandard, array &$heuresParJour): float
    {
        $totalHolidayHours = 0;

        if (!$holidays || !is_iterable($holidays)) {
            Log::info("[Fiche de paie] Aucun jour férié trouvé ou holidays non itérable dans addHolidayHours, type=" . gettype($holidays));
            return 0.0;
        }

        Log::info("[Fiche de paie] Traitement de " . count($holidays) . " jours fériés dans addHolidayHours");
        foreach ($holidays as $holiday) {
            Log::info("[Fiche de paie] Traitement du jour férié dans addHolidayHours : " . json_encode($holiday));
            if ($holiday->is_recurring) {
                for ($year = $start->year; $year <= $end->year; $year++) {
                    $holidayDates = $holiday->getRecurringHolidayDatesForYear($year);
                    foreach ($holidayDates as $dateStr) {
                        $date = Carbon::parse($dateStr);
                        if ($date->between($start, $end) && $this->isWorkingDay($date, $shift)) {
                            // Vérifier si ce jour n'est pas déjà comptabilisé (présence ou congé)
                            if ($heuresParJour[$date->format('Y-m-d')] == 0) {
                                $heuresParJour[$date->format('Y-m-d')] = $dureeStandard;
                                $totalHolidayHours += $dureeStandard;
                                Log::info("[Fiche de paie] Jour férié ajouté : {$date->format('Y-m-d')} = {$dureeStandard}h");
                            } else {
                                Log::info("[Fiche de paie] Jour férié ignoré (déjà comptabilisé) : {$date->format('Y-m-d')} avec {$heuresParJour[$date->format('Y-m-d')]}h");
                            }
                        }
                    }
                }
            } else {
                $holidayDates = $holiday->getHolidayDates();
                foreach ($holidayDates as $dateStr) {
                    $date = Carbon::parse($dateStr);
                    if ($date->between($start, $end) && $this->isWorkingDay($date, $shift)) {
                        // Vérifier si ce jour n'est pas déjà comptabilisé (présence ou congé)
                        if ($heuresParJour[$date->format('Y-m-d')] == 0) {
                            $heuresParJour[$date->format('Y-m-d')] = $dureeStandard;
                            $totalHolidayHours += $dureeStandard;
                            Log::info("[Fiche de paie] Jour férié ajouté : {$date->format('Y-m-d')} = {$dureeStandard}h");
                        } else {
                            Log::info("[Fiche de paie] Jour férié ignoré (déjà comptabilisé) : {$date->format('Y-m-d')} avec {$heuresParJour[$date->format('Y-m-d')]}h");
                        }
                    }
                }
            }
        }

        Log::info("[Fiche de paie] Total heures fériés ajoutées : $totalHolidayHours");
        return $totalHolidayHours;
    }

    /**
     * Calculate statistics for preview
     */
    protected function calculateStatistics($presences, $conges, $holidays, Carbon $start, Carbon $end, $shift): array
    {
        $dureeStandard = $this->getDureeStandard($shift);
        $joursOuvres = $this->getBusinessDaysInMonth($start, $end, $shift);
        $heuresTheoriques = $joursOuvres * $dureeStandard;

        $workedHours = 0.0;
        $heuresSup = 0.0;
        $retards = 0;
        $absences = 0;

        // Process presences
        foreach ($presences as $presence) {
            $duree = $this->parseTimeToHours($presence->heures_travaillees);
            $workedHours += $duree;

            // Calculate overtime
            $sup = max(0, $duree - $dureeStandard);
            $heuresSup += $sup;

            // Check for late arrival using single etat field
            if ($presence->etat === 'retard') {
                $retards++;
            }
        }

        // Add leave hours
        $heuresConges = $this->calculateLeaveHours($conges, $start, $end, $shift, $dureeStandard);
        $workedHours += $heuresConges;

        // Add holiday hours (en utilisant la même logique que dans run())
        Log::info("[Fiche de paie] Avant addHolidayHours dans calculateStatistics");
        $heuresParJour = [];
        $currentDate = clone $start;
        while ($currentDate->lte($end)) {
            $heuresParJour[$currentDate->format('Y-m-d')] = 0;
            $currentDate->addDay();
        }

        // Simuler l'ajout des congés d'abord (pour éviter le doublonnage)
        foreach ($conges as $conge) {
            $debutConge = max($start, Carbon::parse($conge->date_debut));
            $finConge = min($end, Carbon::parse($conge->date_fin));

            $jourConge = clone $debutConge;
            while ($jourConge->lte($finConge)) {
                $dateStr = $jourConge->format('Y-m-d');
                if ($this->isWorkingDay($jourConge, $shift)) {
                    $heuresParJour[$dateStr] = $dureeStandard;
                }
                $jourConge->addDay();
            }
        }

        // Puis ajouter les jours fériés (seulement si pas déjà en congé)
        $heuresFeries = $this->addHolidayHours($holidays, $start, $end, $shift, $dureeStandard, $heuresParJour);
        $workedHours += $heuresFeries;
        Log::info("[Fiche de paie] $heuresFeries heures fériés ajoutées dans calculateStatistics");

        // Calculate absences (excluding holidays)
        $joursPointes = $presences->pluck('date')->unique()->count();
        $joursConges = $this->calculateDaysOnLeave($conges, $start, $end, $shift);
        $joursFeries = $this->calculateHolidayDays($holidays, $start, $end, $shift);
        $absences = max(0, $joursOuvres - $joursPointes - $joursConges - $joursFeries);

        return [
            'heures_theoriques' => $heuresTheoriques,
            'heures_travaillees' => $workedHours,
            'heures_sup' => $heuresSup,
            'retards' => $retards,
            'absences' => $absences,
        ];
    }

    /**
     * Calculate holiday days in the period (en évitant le doublonnage avec les congés)
     */
    protected function calculateHolidayDays($holidays, Carbon $start, Carbon $end, $shift): int
    {
        $joursFeries = 0;

        if (!$holidays || !is_iterable($holidays)) {
            Log::info("[Fiche de paie] Aucun jour férié trouvé ou holidays non itérable, type=" . gettype($holidays));
            return 0;
        }

        Log::info("[Fiche de paie] Traitement de " . count($holidays) . " jours fériés dans calculateHolidayDays");
        foreach ($holidays as $holiday) {
            Log::info("[Fiche de paie] Traitement du jour férié : " . json_encode($holiday));
            if ($holiday->is_recurring) {
                for ($year = $start->year; $year <= $end->year; $year++) {
                    $holidayDates = $holiday->getRecurringHolidayDatesForYear($year);
                    foreach ($holidayDates as $dateStr) {
                        $date = Carbon::parse($dateStr);
                        if ($date->between($start, $end) && $this->isWorkingDay($date, $shift)) {
                            $joursFeries++;
                            Log::info("[Fiche de paie] Jour férié comptabilisé : {$dateStr}");
                        }
                    }
                }
            } else {
                $holidayDates = $holiday->getHolidayDates();
                foreach ($holidayDates as $dateStr) {
                    $date = Carbon::parse($dateStr);
                    if ($date->between($start, $end) && $this->isWorkingDay($date, $shift)) {
                        $joursFeries++;
                        Log::info("[Fiche de paie] Jour férié comptabilisé : {$dateStr}");
                    }
                }
            }
        }

        Log::info("[Fiche de paie] Jours fériés calculés : $joursFeries");
        return $joursFeries;
    }

    /**
     * Get preview data for the Livewire component
     */
    public function getPayslipPreviewData(string $userType, int $userId, string $mois, $record, string $modeCalculation = 'horaire'): array
    {
        $start = Carbon::parse($mois . '-01');
        $end = $start->copy()->endOfMonth();
        $shift = $record->shift ?? null;

        // Get approved leaves
        $conges = $this->getApprovedLeaves($userType, $userId, $start, $end);

        // Get holidays
        Log::info("[Fiche de paie] Avant getHolidaysInRange dans getPayslipPreviewData pour période {$start->format('Y-m-d')} à {$end->format('Y-m-d')}");
        $holidays = Holiday::getHolidaysInRange($start, $end);
        Log::info("[Fiche de paie] Après getHolidaysInRange dans getPayslipPreviewData, type=" . get_class($holidays) . ", contenu=" . json_encode($holidays));
        $joursFeries = $this->calculateHolidayDays($holidays, $start, $end, $shift);
        Log::info("[Fiche de paie] $joursFeries jours fériés trouvés dans getPayslipPreviewData");

        // Fetch presences
        $presences = Presence::where('user_type', $userType)
            ->where('user_id', $userId)
            ->whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->get();

        // Calculate statistics
        $stats = $this->calculateStatistics($presences, $conges, $holidays, $start, $end, $shift);

        // Generate daily details
        $dailyDetails = $this->generateDailyDetails($presences, $conges, $holidays, $start, $end, $shift);

        // Calculate financial details
        $salaireBase = floatval($record->salaire);
        $salaireMensuel = $record->salaire_mensuel !== null ? floatval($record->salaire_mensuel) : null;

        // Calculate sanctions
        $joursOuvres = $this->getBusinessDaysInMonth($start, $end, $shift);
        $joursPointes = $presences->pluck('date')->unique()->count();
        $joursConges = $this->calculateDaysOnLeave($conges, $start, $end, $shift);
        $joursFeries = $this->calculateHolidayDays($holidays, $start, $end, $shift);
        $sanctionsData = $this->calculateSanctions($presences, $joursOuvres, $joursPointes, $joursConges, $joursFeries, $salaireBase, $salaireMensuel, $modeCalculation);

        if ($modeCalculation === 'mensuel') {
            $montantBase = $salaireMensuel - $sanctionsData['total_deductions'];
        } else {
            $heuresNormales = $stats['heures_travaillees'] - $stats['heures_sup'];
            $montantBase = round($salaireBase * $heuresNormales, 2) - $sanctionsData['total_deductions'];
        }

        return [
            'mois' => $mois,
            'employe' => $record->name . ' ' . $record->prenom,
            'heures_theoriques' => number_format($stats['heures_theoriques'], 2, ',', ''),
            'heures_travaillees' => number_format($stats['heures_travaillees'], 2, ',', ''),
            'heures_sup' => number_format($stats['heures_sup'], 2, ',', ''),
            'retards' => $sanctionsData['retards_count'],
            'absences' => $sanctionsData['absences_count'],
            'holidays' => $sanctionsData['holidays_count'],
            'retards_hours_deducted' => number_format($sanctionsData['retards_hours_deducted'], 2, ',', ''),
            'absences_hours_deducted' => number_format($sanctionsData['absences_hours_deducted'], 2, ',', ''),
            'retards_threshold_applied' => $sanctionsData['retards_threshold_applied'],
            'absences_threshold_applied' => $sanctionsData['absences_threshold_applied'],
            'presences' => $dailyDetails,
            'salaire_base' => number_format($salaireBase, 2, ',', ''),
            'salaire_mensuel' => $salaireMensuel ? number_format($salaireMensuel, 2, ',', '') : 'N/A',
            'montant_base' => number_format($montantBase, 2, ',', ''),
            'retards_deduction' => number_format($sanctionsData['retards_deduction'], 2, ',', ''),
            'absences_deduction' => number_format($sanctionsData['absences_deduction'], 2, ',', ''),
            'total_deductions' => number_format($sanctionsData['total_deductions'], 2, ',', ''),
            'poste' => $record->poste ?? 'N/A',
            'departement' => $record->departement ? $record->departement->nom : 'N/A',
            'shift' => $shift ? $shift->nom : 'N/A',
            'mode_calculation' => $modeCalculation,
        ];
    }

    /**
     * Calculate sanctions for tardiness and absences using database configuration
     * Utilise la logique correcte des paliers de gravité (pas de cumul)
     */
    protected function calculateSanctions(
        $presences,
        int $joursOuvres,
        int $joursPointes,
        int $joursConges,
        int $joursFeries,
        float $salaireHoraire,
    ): array {
        $retards = 0;
        // Exclude holidays from absence calculation
        $absences = max(0, $joursOuvres - $joursPointes - $joursConges - $joursFeries);

        // Count tardiness from presences with single etat field
        foreach ($presences as $presence) {
            if (isset($presence->etat) && $presence->etat === 'retard') {
                $retards++;
            }
        }

        $sanctionsData = [
            'retards_count' => $retards,
            'absences_count' => $absences,
            'holidays_count' => $joursFeries,
            'retards_hours_deducted' => 0.0,
            'absences_hours_deducted' => 0.0,
            'retards_deduction' => 0.0,
            'absences_deduction' => 0.0,
            'total_deductions' => 0.0,
            'retards_breakdown' => [],
            'absences_breakdown' => [],
            'retards_cycles' => [],
            'absences_cycles' => [],
            'retards_threshold_applied' => null,
            'absences_threshold_applied' => null,
        ];

        // Calculate tardiness sanctions (logique cyclique)
        if ($retards > 0) {
            $retardResult = $this->calculateCyclicSanctions('retards', $retards);
            $sanctionsData['retards_hours_deducted'] = $retardResult['total_hours_deducted'];
            $sanctionsData['retards_deduction'] = $sanctionsData['retards_hours_deducted'] * $salaireHoraire;
            $sanctionsData['retards_breakdown'] = $retardResult['breakdown'];
            $sanctionsData['retards_cycles'] = $retardResult['cycles_applied'];
            $sanctionsData['retards_threshold_applied'] = !empty($retardResult['cycles_applied']) ? $retardResult['cycles_applied'][0]['threshold'] ?? null : null;

            Log::info("[Fiche de paie] Résultat cyclique retards : nombre=$retards, total_heures_déduites={$sanctionsData['retards_hours_deducted']}, déduction={$sanctionsData['retards_deduction']}");
        }

        // Calculate absence sanctions (logique cyclique)
        if ($absences > 0) {
            $absenceResult = $this->calculateCyclicSanctions('absences', $absences);
            $sanctionsData['absences_hours_deducted'] = $absenceResult['total_hours_deducted'];
            $sanctionsData['absences_deduction'] = $sanctionsData['absences_hours_deducted'] * $salaireHoraire;
            $sanctionsData['absences_breakdown'] = $absenceResult['breakdown'];
            $sanctionsData['absences_cycles'] = $absenceResult['cycles_applied'];
            $sanctionsData['absences_threshold_applied'] = !empty($absenceResult['cycles_applied']) ? $absenceResult['cycles_applied'][0]['threshold'] ?? null : null;

            Log::info("[Fiche de paie] Résultat cyclique absences : nombre=$absences, total_heures_déduites={$sanctionsData['absences_hours_deducted']}, déduction={$sanctionsData['absences_deduction']}");
        }

        $sanctionsData['total_deductions'] = $sanctionsData['retards_deduction'] + $sanctionsData['absences_deduction'];

        Log::info("[Fiche de paie] Total sanctions : retards=$retards, absences=$absences, jours_fériés=$joursFeries, total_heures_déduites=" . ($sanctionsData['retards_hours_deducted'] + $sanctionsData['absences_hours_deducted']) . ", total_déductions={$sanctionsData['total_deductions']}");

        return $sanctionsData;
    }

    /**
     * Calculate sanctions using cyclic threshold logic
     * Applique les seuils de manière répétitive jusqu'à épuisement du total
     */
    protected function calculateCyclicSanctions(string $type, int $count): array
    {
        if ($count <= 0) {
            return [
                'total_hours_deducted' => 0.0,
                'breakdown' => [],
                'cycles_applied' => []
            ];
        }

        // Récupérer tous les seuils pour ce type, triés du plus grand au plus petit
        $thresholds = SanctionsConfig::where('type', $type)
            ->orderBy('threshold', 'desc')
            ->get()
            ->mapWithKeys(function ($config) {
                return [$config->threshold => floatval($config->hours_deducted)];
            })
            ->toArray();

        if (empty($thresholds)) {
            Log::info("[Fiche de paie] Aucun seuil de sanction configuré pour type=$type");
            return [
                'total_hours_deducted' => 0.0,
                'breakdown' => [],
                'cycles_applied' => []
            ];
        }

        $totalDeduction = 0.0;
        $remainingCount = $count;
        $breakdown = [];
        $cyclesApplied = [];

        Log::info("[Fiche de paie] Début du calcul cyclique des sanctions pour type=$type, nombre=$count");
        Log::info("[Fiche de paie] Seuils disponibles : " . json_encode($thresholds));

        // Boucle cyclique : on recommence depuis le plus grand seuil à chaque fois
        $cycleNumber = 1;
        while ($remainingCount > 0) {
            $appliedInThisCycle = false;

            foreach ($thresholds as $threshold => $hoursDeducted) {
                if ($remainingCount >= $threshold) {
                    $remainingCount -= $threshold;
                    $totalDeduction += $hoursDeducted;

                    // Enregistrer pour le breakdown
                    $key = "threshold_{$threshold}";
                    if (!isset($breakdown[$key])) {
                        $breakdown[$key] = [
                            'threshold' => $threshold,
                            'hours_per_application' => $hoursDeducted,
                            'applications_count' => 0,
                            'total_hours' => 0.0
                        ];
                    }
                    $breakdown[$key]['applications_count']++;
                    $breakdown[$key]['total_hours'] += $hoursDeducted;

                    $cyclesApplied[] = [
                        'cycle' => $cycleNumber,
                        'threshold' => $threshold,
                        'hours_deducted' => $hoursDeducted,
                        'remaining_after' => $remainingCount
                    ];

                    Log::info("[Fiche de paie] Cycle $cycleNumber : Seuil $threshold appliqué → -{$hoursDeducted}h, reste : $remainingCount");

                    $appliedInThisCycle = true;
                    break; // Recommencer depuis le plus grand seuil
                }
            }

            // Sécurité : si aucun seuil n'a pu être appliqué, on sort de la boucle
            if (!$appliedInThisCycle) {
                Log::warning("[Fiche de paie] Aucun seuil applicable trouvé pour le reste : $remainingCount");
                break;
            }

            $cycleNumber++;

            // Sécurité : éviter les boucles infinies
            if ($cycleNumber > 1000) {
                Log::error("[Fiche de paie] Trop de cycles, arrêt pour éviter une boucle infinie");
                break;
            }
        }

        Log::info("[Fiche de paie] Calcul cyclique terminé : type=$type, nombre_initial=$count, total_heures_déduites=$totalDeduction, cycles=" . ($cycleNumber - 1));

        return [
            'total_hours_deducted' => $totalDeduction,
            'breakdown' => array_values($breakdown),
            'cycles_applied' => $cyclesApplied
        ];
    }

    /**
     * Create payslip record
     */
    protected function createPayslip(
        string $userType,
        int $userId,
        string $mois,
        float $montantBase,
        float $montantSup,
        float $heuresSup,
        float $primes,
        float $avance,
        ?float $tauxHoraireSup,
        string $modeCalculation,
        array $sanctionsData
    ): fiche_de_paie {
        DB::beginTransaction();
        try {
            $montantTotal = $montantBase + $montantSup + $primes - $avance;
            Log::info("[Fiche de paie] Création de la fiche de paie : montantBase=$montantBase, montantSup=$montantSup, primes=$primes, avance=$avance, montantTotal=$montantTotal");

            $fiche = fiche_de_paie::create([
                'user_type' => $userType,
                'user_id' => $userId,
                'mois' => $mois,
                'montant' => $montantTotal,
                'heures_sup' => $heuresSup,
                'prime' => $primes,
                'avance' => $avance,
                'taux_horaire_sup' => $tauxHoraireSup,
                'montant_heures_sup' => $montantSup,
                'retards_deduction' => $sanctionsData['retards_deduction'],
                'absences_deduction' => $sanctionsData['absences_deduction'],
                'status' => 'en_attente',
                'date_generation' => now(),
                'mode_calculation' => $modeCalculation,
            ]);
            DB::commit();
            Log::info("[Fiche de paie] Fiche créée avec succès pour $userType #$userId, fiche #{$fiche->id}, mode $modeCalculation, montant={$fiche->montant}");
            return $fiche;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw new PayslipCalculationException("Error creating payslip: " . $e->getMessage());
        }
    }

    /**
     * Format financial details for consistent output
     */
    public function formatFinancialDetails(fiche_de_paie $fiche): array
    {
        return [
            'id' => $fiche->id,
            'montant' => number_format($fiche->montant, 2, ',', ' '),
            'avance' => number_format($fiche->avance ?? 0, 2, ',', ' '),
            'prime' => number_format($fiche->prime ?? 0, 2, ',', ' '),
            'status' => $fiche->status,
            'date_generation' => $fiche->date_generation ? Carbon::parse($fiche->date_generation)->format('d/m/Y H:i') : null,
            'taux_horaire_sup' => $fiche->taux_horaire_sup ? number_format($fiche->taux_horaire_sup, 2, ',', '') : null,
            'montant_heures_sup' => $fiche->montant_heures_sup ? number_format($fiche->montant_heures_sup, 2, ',', ' ') : null,
            'mode_calculation' => $fiche->mode_calculation,
            'retards_deduction' => number_format($fiche->retards_deduction ?? 0, 2, ',', ' '),
            'absences_deduction' => number_format($fiche->absences_deduction ?? 0, 2, ',', ' '),
        ];
    }

    /**
     * Valide les paramètres de base
     */
    protected function validateBasicParams(
        string $userType,
        int $userId,
        ?float $tauxHoraireSup,
        ?float $montantHeuresSup,
        string $modeCalculation
    ): void {
        if (!in_array($userType, ['employe', 'grh'])) {
            throw new PayslipBusinessException("Type utilisateur invalide");
        }
        if ($userId <= 0) {
            throw new PayslipBusinessException("ID utilisateur invalide");
        }
        if ($tauxHoraireSup !== null && $montantHeuresSup !== null) {
            throw new PayslipBusinessException("Spécifiez soit un taux heure sup, soit un montant heure sup, jamais les deux.");
        }
        if (!in_array($modeCalculation, ['horaire', 'mensuel'])) {
            throw new PayslipBusinessException("Mode de calcul invalide (attendu: horaire ou mensuel)");
        }
    }

    /**
     * Récupère et valide l'utilisateur
     */
    protected function getValidatedUser(string $userType, int $userId, string $modeCalculation)
    {
        $user = $userType === 'employe'
            ? Employe::with(['shift'])->findOrFail($userId)
            : Grh::with(['shift'])->findOrFail($userId);

        if ($modeCalculation === 'horaire') {
            if (!is_numeric($user->salaire) || floatval($user->salaire) <= 0) {
                throw new PayslipBusinessException("Salaire horaire de base manquant ou invalide");
            }
        } else {
            if ($user->salaire_mensuel === null || floatval($user->salaire_mensuel) <= 0) {
                throw new PayslipBusinessException("Salaire mensuel requis pour le calcul mensuel");
            }
        }

        return $user;
    }

    /**
     * Valide les données du shift
     */
    protected function validateShift($shift): void
    {
        if (!$shift) {
            throw new PayslipBusinessException("Aucun shift n'est assigné à cet utilisateur");
        }

        if (!$shift->heure_debut || !$shift->heure_fin) {
            throw new PayslipBusinessException("Le shift doit avoir une heure de début et de fin");
        }

        Log::info("[Fiche de paie] Validation shift : heure_debut={$shift->heure_debut}, heure_fin={$shift->heure_fin}");

        $heureDebut = Carbon::parse($shift->heure_debut);
        $heureFin = Carbon::parse($shift->heure_fin);

        $debutMinutes = $heureDebut->hour * 60 + $heureDebut->minute;
        $finMinutes = $heureFin->hour * 60 + $heureFin->minute;

        if ($finMinutes < $debutMinutes) {
            $finMinutes += 24 * 60;
        }

        $dureeShiftMinutes = $finMinutes - $debutMinutes;

        Log::info("Durée du shift calculée manuellement : {$dureeShiftMinutes} minutes");

        if ($shift->pause && $shift->duree_pause) {
            if ($shift->duree_pause >= $dureeShiftMinutes) {
                Log::warning("Validation shift échouée : duree_pause={$shift->duree_pause}, dureeShiftMinutes={$dureeShiftMinutes}");
                throw new PayslipBusinessException("La durée de pause ne peut pas être supérieure à la durée totale du shift");
            }
        }

        if (!$shift->jours_travail) {
            throw new PayslipBusinessException("Le shift doit définir au moins un jour de travail");
        }

        $joursArray = is_array($shift->jours_travail) ? $shift->jours_travail : json_decode($shift->jours_travail, true);

        if (!is_array($joursArray) || empty($joursArray)) {
            throw new PayslipBusinessException("Le shift doit définir au moins un jour de travail");
        }
    }

    /**
     * Récupère les primes de l'utilisateur pour le mois donné
     */
    protected function getPrimes($user, ?string $mois = null): float
    {
        $mois = $mois ?? Carbon::now()->format('Y-m');
        return (float) \App\Models\Prime::where('user_type', $user instanceof \App\Models\Employe ? 'employe' : 'grh')
            ->where('user_id', $user->id)
            ->where('mois', $mois)
            ->sum('montant');
    }

    /**
     * Récupère les avances de l'utilisateur pour le mois donné
     */
    protected function getAvances($user, ?string $mois = null): float
    {
        $mois = $mois ?? Carbon::now()->format('Y-m');
        return (float) \App\Models\Avance::where('user_type', $user instanceof \App\Models\Employe ? 'employe' : 'grh')
            ->where('user_id', $user->id)
            ->where('mois', $mois)
            ->sum('montant');
    }

    /**
     * Calcule le nombre de jours de congés dans la période
     */
    protected function calculateDaysOnLeave($conges, Carbon $start, Carbon $end, $shift): int
    {
        $joursConges = 0;

        foreach ($conges as $conge) {
            $debutConge = max($start, Carbon::parse($conge->date_debut));
            $finConge = min($end, Carbon::parse($conge->date_fin));

            $jourConge = clone $debutConge;
            while ($jourConge->lte($finConge)) {
                $carbonDate = $this->ensureCarbon($jourConge);

                if ($this->isWorkingDay($carbonDate, $shift)) {
                    $joursConges++;
                }

                $jourConge->addDay();
            }
        }

        return $joursConges;
    }

    /**
     * Vérifie si un jour est un jour ouvré selon le shift
     */
    protected function isWorkingDay($date, $shift): bool
    {
        $date = $this->ensureCarbon($date);

        if (!$shift || !$shift->jours_travail) {
            return !in_array($date->format('N'), [6, 7]);
        }

        $joursShift = is_array($shift->jours_travail) ? $shift->jours_travail : json_decode($shift->jours_travail, true);

        if (!is_array($joursShift)) {
            return !in_array($date->format('N'), [6, 7]);
        }

        $jourMapping = [
            'lundi' => 1,
            'mardi' => 2,
            'mercredi' => 3,
            'jeudi' => 4,
            'vendredi' => 5,
            'samedi' => 6,
            'dimanche' => 7
        ];

        $numeroJour = (int) $date->format('N');

        foreach ($joursShift as $jour) {
            if (isset($jourMapping[strtolower($jour)]) && $jourMapping[strtolower($jour)] === $numeroJour) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convertit un temps en format texte en heures décimales
     */
    protected function parseTimeToHours(?string $time): float
    {
        if (!$time) return 0.0;
        $parts = explode(':', $time);
        if (count($parts) !== 3) return 0.0;
        [$h, $m, $s] = array_map('intval', $parts);
        return $h + ($m / 60) + ($s / 3600);
    }

    /**
     * Calcule la durée standard de travail selon le shift
     */
    protected function getDureeStandard($shift): float
    {
        if (!$shift || !$shift->heure_debut || !$shift->heure_fin) return 8.0;

        $heureDebut = Carbon::parse($shift->heure_debut);
        $heureFin = Carbon::parse($shift->heure_fin);

        $debutMinutes = $heureDebut->hour * 60 + $heureDebut->minute;
        $finMinutes = $heureFin->hour * 60 + $heureFin->minute;

        if ($finMinutes < $debutMinutes) {
            $finMinutes += 24 * 60;
        }

        $duree = ($finMinutes - $debutMinutes) / 60.0;

        if ($shift->pause && $shift->duree_pause) {
            $duree -= ($shift->duree_pause / 60.0);
        }

        return max(1, $duree);
    }

    /**
     * Calcule le nombre de jours ouvrés dans le mois selon le shift
     */
    protected function getBusinessDaysInMonth(Carbon $start, Carbon $end, $shift): int
    {
        $days = 0;
        $currentDate = clone $start;
        while ($currentDate->lte($end)) {
            $carbonDate = $this->ensureCarbon($currentDate);

            if ($this->isWorkingDay($carbonDate, $shift)) {
                $days++;
            }

            $currentDate->addDay();
        }
        return $days;
    }

    /**
     * Assure qu'une date est un objet Carbon
     */
    protected function ensureCarbon($date): Carbon
    {
        if ($date instanceof Carbon) {
            return $date;
        }

        if ($date instanceof DateTime) {
            return Carbon::instance($date);
        }

        return Carbon::parse($date);
    }

    /**
     * Determine global status for a presence row
     */
    public function determinePresenceStatus(array $presence): string
    {
        $isWeekend = $presence['is_weekend'] ?? false;
        $isHoliday = $presence['is_holiday'] ?? false;
        $anomalieType = $presence['anomalie_type'] ?? null;
        $duree = isset($presence['duree']) ? floatval(str_replace(',', '.', $presence['duree'])) : 0.0;
        $etat = $presence['etat'] ?? null;

        if ($isHoliday) {
            return 'Holiday: ' . ($presence['holiday_name'] ?? 'Public Holiday');
        }

        if ($isWeekend) {
            return $duree > 0 ? 'Présent (weekend)' : 'Weekend';
        }

        if ($duree == 0 || $anomalieType === 'absent') {
            $status = 'Absent';
            if (!($presence['anomalie_resolue'] ?? true)) {
                $status .= ' (Non résolu)';
            }
            return $status;
        }

        if ($anomalieType === 'conge') {
            return 'Congé';
        }

        if ($anomalieType) {
            $status = $this->translateAnomalyType($anomalieType);
            return $status . (($presence['anomalie_resolue'] ?? false) ? ' (Résolu)' : ' (Non résolu)');
        }

        if ($etat === 'retard') {
            return 'Retard';
        }

        return 'Présent';
    }

    /**
     * Translation helper for anomaly types
     */
    public function translateAnomalyType(?string $type): string
    {
        if (!$type) return 'Normal';

        return match ($type) {
            'unique_pointage' => 'Pointage unique',
            'absent' => 'Absent',
            'incomplet' => 'Incomplet',
            'hors_shift' => 'Hors shift',
            'conge' => 'Congé',
            'holiday' => 'Jour férié',
            default => ucfirst($type),
        };
    }

    /**
     * Translation helper for check status
     */
    public function translateCheckStatus(?string $status): string
    {
        if (!$status) return '—';

        return match ($status) {
            'present' => 'Présent',
            'retard' => 'Retard',
            'absent' => 'Absent',
            'incomplet' => 'Incomplet',
            default => ucfirst($status),
        };
    }

    /**
     * Get shift from presences collection (helper method)
     */
    protected function getShiftFromPresences($presences)
    {
        if ($presences->isEmpty()) {
            return null;
        }

        $firstPresence = $presences->first();
        $userType = $firstPresence->user_type;
        $userId = $firstPresence->user_id;

        $user = $userType === 'employe'
            ? Employe::with(['shift'])->find($userId)
            : Grh::with(['shift'])->find($userId);

        return $user ? $user->shift : null;
    }

    /**
     * Generate daily details for preview
     */
    protected function generateDailyDetails($presences, $conges, $holidays, Carbon $start, Carbon $end, $shift): array
    {
        $dureeStandard = $this->getDureeStandard($shift);
        $dailyDetailsByDate = [];

        // Create a map of date => presence
        $presencesByDate = [];
        if ($presences && is_iterable($presences)) {
            foreach ($presences as $presence) {
                if ($presence && isset($presence->date)) {
                    $dateKey = $presence->date->format('Y-m-d');
                    $presencesByDate[$dateKey] = $presence;
                }
            }
        }

        // Create leave days map
        $leaveDays = [];
        if ($conges && is_iterable($conges)) {
            foreach ($conges as $conge) {
                if ($conge && isset($conge->date_debut, $conge->date_fin)) {
                    $debutConge = max($start, Carbon::parse($conge->date_debut));
                    $finConge = min($end, Carbon::parse($conge->date_fin));

                    $jourConge = clone $debutConge;
                    while ($jourConge->lte($finConge)) {
                        if ($this->isWorkingDay($jourConge, $shift)) {
                            $leaveDays[$jourConge->format('Y-m-d')] = true;
                        }
                        $jourConge->addDay();
                    }
                }
            }
        }

        // Create holiday days map
        $holidayDays = [];
        if ($holidays && is_iterable($holidays)) {
            foreach ($holidays as $holiday) {
                if (!$holiday || !isset($holiday->is_recurring)) continue;

                if ($holiday->is_recurring) {
                    for ($year = $start->year; $year <= $end->year; $year++) {
                        $holidayDates = $holiday->getRecurringHolidayDatesForYear($year);
                        if (is_array($holidayDates)) {
                            foreach ($holidayDates as $dateStr) {
                                $date = Carbon::parse($dateStr);
                                if ($date->between($start, $end) && $this->isWorkingDay($date, $shift)) {
                                    $holidayDays[$dateStr] = $holiday->name ?? 'Holiday';
                                }
                            }
                        }
                    }
                } else {
                    $holidayDates = $holiday->getHolidayDates();
                    if (is_array($holidayDates)) {
                        foreach ($holidayDates as $dateStr) {
                            $date = Carbon::parse($dateStr);
                            if ($date->between($start, $end) && $this->isWorkingDay($date, $shift)) {
                                $holidayDays[$dateStr] = $holiday->name ?? 'Holiday';
                            }
                        }
                    }
                }
            }
        }

        // Process each day
        $currentDate = clone $start;
        while ($currentDate->lte($end)) {
            $dateStr = $currentDate->format('Y-m-d');
            $presence = $presencesByDate[$dateStr] ?? null;
            $isWorkingDay = $this->isWorkingDay($currentDate, $shift);
            $isWeekend = !$isWorkingDay;
            $isLeaveDay = isset($leaveDays[$dateStr]);
            $isHolidayDay = isset($holidayDays[$dateStr]);

            if ($presence && isset($presence->heures_travaillees)) {
                $duree = $this->parseTimeToHours($presence->heures_travaillees);

                $dailyDetailsByDate[$dateStr] = [
                    'date' => $dateStr,
                    'duree' => number_format($duree, 2, ',', ''),
                    'is_weekend' => $isWeekend,
                    'is_holiday' => $isHolidayDay,
                    'holiday_name' => $holidayDays[$dateStr] ?? null,
                    'check_in' => ($presence->check_in ?? null) ? $presence->check_in->format('H:i:s') : null,
                    'check_out' => ($presence->check_out ?? null) ? $presence->check_out->format('H:i:s') : null,
                    'etat' => $presence->etat ?? null,
                    'anomalie_type' => $presence->anomalie_type ?? null,
                    'anomalie_resolue' => $presence->anomalie_resolue ?? false,
                ];
            } else {
                $duree = 0;
                $anomalieType = null;
                $etat = null;

                if ($isHolidayDay) {
                    $duree = $dureeStandard;
                    $anomalieType = 'holiday';
                    $etat = 'present';
                } elseif ($isLeaveDay) {
                    $duree = $dureeStandard;
                    $anomalieType = 'conge';
                    $etat = 'present';
                } elseif ($isWorkingDay) {
                    $anomalieType = 'absent';
                    $etat = 'absent';
                }

                $dailyDetailsByDate[$dateStr] = [
                    'date' => $dateStr,
                    'duree' => number_format($duree, 2, ',', ''),
                    'is_weekend' => $isWeekend,
                    'is_holiday' => $isHolidayDay,
                    'holiday_name' => $holidayDays[$dateStr] ?? null,
                    'check_in' => null,
                    'check_out' => null,
                    'etat' => $etat,
                    'anomalie_type' => $anomalieType,
                    'anomalie_resolue' => ($isHolidayDay || $isLeaveDay) ? true : ($isWorkingDay ? false : null),
                ];
            }

            $currentDate->addDay();
        }

        // Convert to indexed array and sort
        $dailyDetails = array_values($dailyDetailsByDate);
        usort($dailyDetails, function ($a, $b) {
            return strcmp($a['date'] ?? '', $b['date'] ?? '');
        });

        return $dailyDetails;
    }

    /**
     * Get approved leaves for the period
     */
    protected function getApprovedLeaves(string $userType, int $userId, Carbon $start, Carbon $end): \Illuminate\Database\Eloquent\Collection
    {
        if ($userType === 'grh') {
            Log::info("[Fiche de paie] Aucun congé approuvé trouvé pour $userType #$userId (les GRH n'ont pas de demandes de congé)");
            return new \Illuminate\Database\Eloquent\Collection();
        }

        $conges = demande_conge::where('employe_id', $userId)
            ->whereIn('status', ['approuvee', 'approuvée', 'Approuvée', 'APPROUVEE'])
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('date_debut', [$start, $end])
                    ->orWhereBetween('date_fin', [$start, $end])
                    ->orWhere(function ($q) use ($start, $end) {
                        $q->where('date_debut', '<', $start)
                            ->where('date_fin', '>', $end);
                    });
            })
            ->get();
        Log::info("[Fiche de paie] {$conges->count()} congés approuvés trouvés pour $userType #$userId");
        return $conges;
    }

    /**
     * Calculate leave hours for statistics
     */
    protected function calculateLeaveHours($conges, Carbon $start, Carbon $end, $shift, float $dureeStandard): float
    {
        $totalLeaveHours = 0;

        foreach ($conges as $conge) {
            $debutConge = max($start, Carbon::parse($conge->date_debut));
            $finConge = min($end, Carbon::parse($conge->date_fin));

            $jourConge = clone $debutConge;
            while ($jourConge->lte($finConge)) {
                if ($this->isWorkingDay($jourConge, $shift)) {
                    $totalLeaveHours += $dureeStandard;
                }
                $jourConge->addDay();
            }
        }

        return $totalLeaveHours;
    }
}
