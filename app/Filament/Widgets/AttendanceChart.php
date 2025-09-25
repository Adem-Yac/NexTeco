<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Presence;
use App\Models\Employe;
use Carbon\Carbon;

class AttendanceChart extends ChartWidget
{
    protected static ?string $heading = 'Taux de Présence';
    protected static ?int $sort = 2; // Position after the stats overview

    protected function getType(): string
    {
        return 'bar'; // A bar chart seems appropriate for showing daily presence/absence/tardiness
    }

    protected function getData(): array
    {
        $days = [];
        $presentData = [];
        $lateData = [];
        $absentData = [];

        // Detect if we are in the employee panel (guard 'employe')
        $isEmployeePanel = auth('employe')->check();
        $employeeId = $isEmployeePanel ? auth('employe')->id() : null;

        // Get data for the last 7 days
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $days[] = $date->format('D d/m');

            if ($isEmployeePanel && $employeeId) {
                $totalEmployes = 1;
                $presencesToday = Presence::where('user_type', 'employe')
                    ->where('user_id', $employeeId)
                    ->whereDate('date', $date)
                    ->get();
            } else {
                $totalEmployes = Employe::count();
                $presencesToday = Presence::whereDate('date', $date)->get();
            }

            $presentCount = 0;
            $lateCount = 0;
            foreach ($presencesToday as $presence) {
                $presentCount++;
                if (
                    $presence->shift &&
                    $presence->shift->check_in_time &&
                    $presence->check_in &&
                    Carbon::parse($presence->check_in)->greaterThan(Carbon::parse($presence->shift->check_in_time))
                ) {
                    $lateCount++;
                }
            }

            $absentCount = $totalEmployes - $presentCount;

            $presentData[] = $presentCount;
            $lateData[] = $lateCount;
            $absentData[] = max(0, $absentCount);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Présence',
                    'data' => $presentData,
                    'backgroundColor' => '#10B981', // green
                    'borderColor' => '#059669',
                ],
                [
                    'label' => 'Retard',
                    'data' => $lateData,
                    'backgroundColor' => '#F59E0B', // amber
                    'borderColor' => '#D97706',
                ],
                [
                    'label' => 'Absence',
                    'data' => $absentData,
                    'backgroundColor' => '#EF4444', // red
                    'borderColor' => '#DC2626',
                ],
            ],
            'labels' => $days,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'stepSize' => 1, // Ensure whole numbers for counts
                    ],
                ],
            ],
            'responsive' => true,
            'maintainAspectRatio' => false,
        ];
    }
}