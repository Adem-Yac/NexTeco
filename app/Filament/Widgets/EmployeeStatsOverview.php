<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Employe;
use App\Models\Presence;
use App\Models\Departement;
use Carbon\Carbon;

class EmployeeStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $totalEmployes = Employe::count();
        $totalDepartements = Departement::count();
        $pendingLeaveRequests = \App\Models\demande_conge::where('status', 'en_attente')->count();
        $pendingReclamations = \App\Models\reclamations::where('statut', 'en_attente')->count();

        // Attendance Rate Calculation
        $today = Carbon::now();
        $startOfMonth = $today->startOfMonth()->toDateString();
        $endOfMonth = $today->endOfMonth()->toDateString();

        $presences = Presence::whereBetween('date', [$startOfMonth, $endOfMonth])->get();
        $totalWorkingDays = 0; // This needs to be calculated based on shifts and holidays
        $totalPresentDays = 0;
        $totalLateDays = 0;
        $totalAbsentDays = 0;

        // A more robust calculation would involve iterating through each employee and their expected shifts
        // For simplicity, let's assume each presence record represents one working day.
        // This part needs further refinement based on actual shift management and attendance logic.
        // For now, let's just get a basic count based on presence records.
        foreach ($presences as $presence) {
            $totalPresentDays++; // Simplified: each presence is a present day
            if (
                $presence->check_in &&
                $presence->shift && // <-- Add this check
                Carbon::parse($presence->check_in)->greaterThan(Carbon::parse($presence->shift->check_in_time))
            ) {
                $totalLateDays++;
            }
        }

        // For absence rate, we need to know the total expected presences.
        // This is a placeholder and should be replaced with actual logic
        // to count expected working days for all employees in the month.
        $totalExpectedPresences = $totalEmployes * Carbon::now()->daysInMonth; // Very basic approximation

        $attendanceRate = $totalExpectedPresences > 0 ? ($totalPresentDays / $totalExpectedPresences) * 100 : 0;
        $lateRate = $totalPresentDays > 0 ? ($totalLateDays / $totalPresentDays) * 100 : 0;
        $absenceRate = $totalExpectedPresences > 0 ? (($totalExpectedPresences - $totalPresentDays) / $totalExpectedPresences) * 100 : 0;


        return [
            Stat::make('Nombre total d\'employés', $totalEmployes)
                ->description('Nombre total d\'employés enregistrés')
                ->descriptionIcon('heroicon-s-users')
                ->color('primary'),
            Stat::make('Taux de présence', round($attendanceRate, 2) . '%')
                ->description('Pourcentage de présence ce mois-ci')
                ->descriptionIcon('heroicon-s-check-circle')
                ->color('success'),
            Stat::make('Taux de retard', round($lateRate, 2) . '%')
                ->description('Pourcentage de retards ce mois-ci')
                ->descriptionIcon('heroicon-s-clock')
                ->color('warning'),
            Stat::make('Taux d\'absence', round($absenceRate, 2) . '%')
                ->description('Pourcentage d\'absences ce mois-ci')
                ->descriptionIcon('heroicon-s-x-circle')
                ->color('danger'),
            Stat::make('Départements', $totalDepartements)
                ->description('Nombre de départements disponibles')
                ->descriptionIcon('heroicon-s-building-office-2')
                ->color('info'),
            Stat::make('Demandes de congé en attente', $pendingLeaveRequests)
                ->description('Demandes de congé en attente')
                ->descriptionIcon('heroicon-s-document-text')
                ->color('warning'),
            Stat::make('Réclamations en attente', $pendingReclamations)
                ->description('Réclamations en attente')
                ->descriptionIcon('heroicon-s-exclamation-triangle')
                ->color('warning'),
        ];
    }
}