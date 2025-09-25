<?php

namespace App\Actions;

use App\Actions\CalculatePayslips;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class PayslipPdfExporter
{
    public function export(array $payslip_preview, string $activeTab)
    {
        try {
            if (empty($payslip_preview)) {
                Notification::make()
                    ->title('Erreur')
                    ->body('Aucune fiche de paie sélectionnée pour l\'exportation.')
                    ->danger()
                    ->send();
                return;
            }

            $pdf = Pdf::loadView('pdf-fiche-de-paie', [
                'payslip_preview' => $payslip_preview,
                'calculatePayslips' => new CalculatePayslips(),
                'ficheDetails' => $payslip_preview['fiche_details'] ?? [],
                'activeTab' => $activeTab ?? 'employes',
                'mode_calculation' => $payslip_preview['mode_calculation'] ?? 'horaire',
            ]);

            // Configuration optimisée pour les sauts de page
            $pdf->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'defaultFont' => 'Arial',
                'dpi' => 150,
                'defaultPaperSize' => 'a4',
                'defaultPaperOrientation' => 'portrait',
                'isFontSubsettingEnabled' => true,
                'isPhpEnabled' => false,
                'isJavascriptEnabled' => false,
                'isCssFloatEnabled' => true,
                'isCssPositionEnabled' => true,
                'isCssPageBreakEnabled' => true,
            ]);

            $filename = 'fiche_de_paie_' . $payslip_preview['mois'] . '_' . str_replace(' ', '_', $payslip_preview['employe']) . '.pdf';

            return Response::streamDownload(function () use ($pdf) {
                echo $pdf->output();
            }, $filename);
        } catch (\Throwable $e) {
            Log::error("Erreur lors de l'exportation PDF : " . $e->getMessage());
            Notification::make()
                ->title('Erreur')
                ->body("Échec de l'exportation PDF : {$e->getMessage()}")
                ->danger()
                ->send();
        }
    }
}
