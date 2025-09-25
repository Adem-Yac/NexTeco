<?php

namespace App\Actions;

use App\Actions\CalculatePayslips;
use Carbon\Carbon;

class PayslipCsvGenerator
{
    public function generate($out, callable $writeCSV, array $preview): void
    {
        $calculatePayslips = new CalculatePayslips();

        // Section 1: Informations générales
        $writeCSV($out, ['FICHE DE PAIE']);
        $writeCSV($out, ['Référence', $preview['fiche_id'] ?? 'N/A']);
        $writeCSV($out, ['Période', isset($preview['mois']) ? Carbon::parse($preview['mois'] . '-01')->locale('fr')->isoFormat('MMMM YYYY') : 'N/A']);
        $writeCSV($out, ['Date de génération', $preview['fiche_details']['date_generation'] ?? date('d/m/Y H:i')]);
        $writeCSV($out, ['Statut', $this->translateStatus($preview['fiche_details']['status'] ?? 'en_attente')]);
        $writeCSV($out, ['Mode de calcul', $preview['mode_calculation'] === 'mensuel' ? 'Mensuel' : 'Horaire']);
        $writeCSV($out, []);

        // Section 2: Informations employé
        $writeCSV($out, ['INFORMATIONS EMPLOYÉ']);
        $writeCSV($out, ['Nom et prénom', $preview['employe'] ?? 'N/A']);
        $writeCSV($out, ['Poste', $preview['poste'] ?? 'N/A']);
        $writeCSV($out, ['Département', $preview['departement'] ?? 'N/A']);
        $writeCSV($out, ['Shift', $preview['shift'] ?? 'N/A']);
        $writeCSV($out, []);

        // Section 3: Résumé des heures
        $writeCSV($out, ['RÉSUMÉ DES HEURES']);
        $writeCSV($out, ['Heures à travailler (théoriques)', $preview['heures_theoriques'] ?? '0,00']);
        $writeCSV($out, ['Heures travaillées', $preview['heures_travaillees'] ?? '0,00']);
        $writeCSV($out, ['Heures supplémentaires', $preview['heures_sup'] ?? '0,00']);
        $writeCSV($out, ['Retards', $preview['retards'] ?? '0']);
        $writeCSV($out, ['Absences', $preview['absences'] ?? '0']);
        $writeCSV($out, ['Jours fériés', $preview['holidays'] ?? '0']);
        $writeCSV($out, []);

        // Section 4: Sanctions
        if (($preview['retards_hours_deducted'] ?? 0) > 0 || ($preview['absences_hours_deducted'] ?? 0) > 0) {
            $writeCSV($out, ['SANCTIONS']);
            if (($preview['retards_hours_deducted'] ?? 0) > 0) {
                $writeCSV($out, ['Heures déduites pour retards', $preview['retards_hours_deducted'] . ' h']);
                $writeCSV($out, ['Montant déduit pour retards', $preview['retards_deduction'] . ' DZD']);
            }
            if (($preview['absences_hours_deducted'] ?? 0) > 0) {
                $writeCSV($out, ['Heures déduites pour absences', $preview['absences_hours_deducted'] . ' h']);
                $writeCSV($out, ['Montant déduit pour absences', $preview['absences_deduction'] . ' DZD']);
            }
            $writeCSV($out, ['Total déductions', $preview['total_deductions'] . ' DZD']);
            $writeCSV($out, []);
        }

        // Section 5: Rémunération
        $writeCSV($out, ['RÉMUNÉRATION']);
        if ($preview['mode_calculation'] === 'mensuel') {
            $writeCSV($out, ['Salaire mensuel', $preview['salaire_mensuel'] . ' DZD']);
        } else {
            $writeCSV($out, ['Salaire horaire', $preview['salaire_base'] . ' DZD']);
        }
        $writeCSV($out, ['Montant base', $preview['montant_base'] . ' DZD']);

        if (isset($preview['fiche_details']['taux_horaire_sup']) && $preview['fiche_details']['taux_horaire_sup']) {
            $writeCSV($out, ['Taux horaire supplémentaire', $preview['fiche_details']['taux_horaire_sup'] . 'x']);
        }
        if (isset($preview['fiche_details']['montant_heures_sup']) && $preview['fiche_details']['montant_heures_sup']) {
            $writeCSV($out, ['Montant heures supplémentaires', $preview['fiche_details']['montant_heures_sup'] . ' DZD']);
        }
        if (isset($preview['fiche_details']['prime']) && $preview['fiche_details']['prime']) {
            $writeCSV($out, ['Primes', $preview['fiche_details']['prime'] . ' DZD']);
        }
        if (isset($preview['fiche_details']['avance']) && $preview['fiche_details']['avance']) {
            $writeCSV($out, ['Avances', $preview['fiche_details']['avance'] . ' DZD']);
        }
        $writeCSV($out, ['Montant total', $preview['fiche_details']['montant'] . ' DZD']);
        $writeCSV($out, []);

        // Section 6: Détail des présences
        $writeCSV($out, ['DÉTAIL DES PRÉSENCES']);
        $writeCSV($out, [
            'Date',
            'Jour',
            'Type de jour',
            'Heures travaillées',
            'Heure d\'arrivée',
            'Heure de départ',
            'État',
            'Type d\'anomalie',
            'Anomalie résolue',
            'Statut global'
        ]);

        if (isset($preview['presences']) && is_array($preview['presences'])) {
            foreach ($preview['presences'] as $row) {
                $date = isset($row['date']) ? Carbon::parse($row['date']) : null;
                if ($date) {
                    $jour = $date->locale('fr')->isoFormat('dddd');
                    $date_formatted = $date->format('d/m/Y');
                    $isHoliday = $row['is_holiday'] ?? false;
                    $isWeekend = $row['is_weekend'] ?? false;
                    $type_jour = $isHoliday ? 'Férié' : ($isWeekend ? 'WE' : 'JO');
                    $anomalie_type = $row['anomalie_type'] ?? null;
                    $anomalie_resolue = isset($row['anomalie_resolue']) && $row['anomalie_resolue'] ? 'Oui' : 'Non';
                    $anomalie_resolue_export = $anomalie_type === 'absent'
                        ? 'Non'
                        : ($anomalie_type ? $anomalie_resolue : '—');
                    $etat = $calculatePayslips->translateCheckStatus($row['etat'] ?? null);
                    $anomalie = $calculatePayslips->translateAnomalyType($row['anomalie_type'] ?? null);
                    $statut_global = $calculatePayslips->determinePresenceStatus($row);
                    $writeCSV($out, [
                        $date_formatted,
                        $jour,
                        $type_jour,
                        $row['duree'] ?? '0,00',
                        $row['check_in'] ?? '—',
                        $row['check_out'] ?? '—',
                        $etat,
                        $anomalie,
                        $anomalie_resolue_export,
                        $statut_global,
                    ]);
                }
            }
        }
    }

    protected function translateStatus(string $status): string
    {
        return match ($status) {
            'en_attente' => 'En attente',
            'validé' => 'Validé',
            'payé' => 'Payé',
            'annulé' => 'Annulé',
            default => ucfirst($status),
        };
    }
}
