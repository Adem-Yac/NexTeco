<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Departement;

class DepartmentDistributionChart extends ChartWidget
{
    protected static ?string $heading = 'Répartition par département';
    protected static ?int $sort = 3; // Position after the attendance chart

    protected function getType(): string
    {
        return 'doughnut'; // A doughnut chart is suitable for showing distribution
    }

    protected function getData(): array
    {
        $departements = Departement::all();
        $employeeCounts = [];
        $labels = [];
        $colors = [];

        foreach ($departements as $departement) {
            $count = $departement->employes()->count();
            $employeeCounts[] = $count;
            $labels[] = $departement->nom;
            $colors[] = $this->stringToColor($departement->nom);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Employés',
                    'data' => $employeeCounts,
                    'backgroundColor' => $colors,
                    'borderColor' => $colors,
                ],
            ],
            'labels' => $labels,
        ];
    }

    // Génère une couleur hex unique et stable à partir d'une chaîne (nom du département)
    protected function stringToColor($string): string
    {
        $hash = md5($string);
        // Utilise les 6 premiers caractères pour la couleur
        return '#' . substr($hash, 0, 6);
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'cutout' => '70%', // For doughnut chart
        ];
    }
} 