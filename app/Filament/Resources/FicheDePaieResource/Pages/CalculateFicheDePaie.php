<?php 

namespace App\Filament\Resources\FicheDePaieResource\Pages;

use App\Filament\Resources\FicheDePaieResource;
use App\Models\Employe;
use App\Models\Grh;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use App\Actions\CalculatePayslips;
use App\Actions\PayslipCalculationException;
use App\Actions\PayslipBusinessException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Response;
use App\Actions\PayslipCsvGenerator;
use App\Actions\PayslipPdfExporter;

class CalculateFicheDePaie extends Page implements Tables\Contracts\HasTable
{
    use InteractsWithTable;

    protected static string $resource = FicheDePaieResource::class;
    protected static string $view = 'filament.resources.fiche-de-paie-resource.pages.calculate-fiche-de-paie';

    public string $activeTab = 'employes';
    public array $payslip_preview = [];

    public function mount(): void
    {
        $this->activeTab = 'employes';
        $this->resetPayslipPreview();
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetTable();
        $this->resetPayslipPreview();
    }

    public function resetPayslipPreview(): void
    {
        $this->payslip_preview = [];
    }

    protected function getTableActions(): array
    {
        return [
            Tables\Actions\Action::make('calculate')
                ->label('Calculer la paie')
                ->icon('heroicon-o-calculator')
                ->form(function () {
                    return [
                        TextInput::make('mois')
                            ->label('Mois')
                            ->required()
                            ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state . '-01')->format('Y-m') : null)
                            ->dehydrateStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('Y-m') : null)
                            ->placeholder('YYYY-MM')
                            ->mask('9999-99'),
                        Select::make('mode_calculation')
                            ->label('Mode de calcul')
                            ->options([
                                'horaire' => 'Horaire',
                                'mensuel' => 'Mensuel',
                            ])
                            ->required()
                            ->reactive(),
                        Select::make('mode')
                            ->label('Mode heures sup')
                            ->options([
                                'taux' => 'Taux heure sup',
                                'montant' => 'Montant heure sup',
                            ])
                            ->required()
                            ->reactive(),
                        TextInput::make('taux_heure_sup')
                            ->label('Taux heure sup')
                            ->numeric()
                            ->minValue(0.01)
                            ->suffix('x')
                            ->visible(fn ($get) => $get('mode') === 'taux')
                            ->required(fn ($get) => $get('mode') === 'taux'),
                        TextInput::make('montant_heure_sup')
                            ->label('Montant heure sup')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('€')
                            ->visible(fn ($get) => $get('mode') === 'montant')
                            ->required(fn ($get) => $get('mode') === 'montant'),
                    ];
                })
                ->action(function (array $data, $record) {
                    $this->calculatePayslip($data, $record);
                })
        ];
    }

    protected function calculatePayslip(array $data, $record): void
    {
        $this->resetPayslipPreview();
        
        try {
            $userType = $this->activeTab === 'grhs' ? 'grh' : 'employe';
            $userId = $record->id;
            $mois = $data['mois'] ?? null;
            $modeCalculation = $data['mode_calculation'] ?? 'horaire';
            $mode = $data['mode'] ?? null;
            $tauxHoraireSup = $mode === 'taux' ? floatval($data['taux_heure_sup']) : null;
            $montantHeuresSup = $mode === 'montant' ? floatval($data['montant_heure_sup']) : null;

            $calculatePayslips = new CalculatePayslips();
            $fiche = $calculatePayslips->run(
                $userType,
                $userId,
                $tauxHoraireSup,
                $montantHeuresSup,
                $mois,
                $modeCalculation
            );

            $previewData = $calculatePayslips->getPayslipPreviewData(
                $userType,
                $userId,
                $mois,
                $record,
                $modeCalculation
            );

            $this->payslip_preview = array_merge($previewData, [
                'fiche_id' => $fiche->id,
                'fiche_details' => $calculatePayslips->formatFinancialDetails($fiche),
            ]);

            Notification::make()
                ->title('Succès')
                ->body($modeCalculation === 'mensuel' ? 'Fiche de paie mensuelle générée avec succès' : 'Fiche de paie horaire générée avec succès')
                ->success()
                ->send();

        } catch (PayslipBusinessException $e) {
            $this->resetPayslipPreview();
            Notification::make()
                ->title('Erreur métier')
                ->body($e->getMessage())
                ->warning()
                ->send();
        } catch (PayslipCalculationException $e) {
            $this->resetPayslipPreview();
            Notification::make()
                ->title('Erreur de calcul')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } catch (\Throwable $e) {
            Log::error('Unexpected error in payslip calculation: ' . $e->getMessage());
            $this->resetPayslipPreview();
            Notification::make()
                ->title('Erreur')
                ->body('Une erreur inattendue est survenue')
                ->danger()
                ->send();
        }
    }

    public function exportCsv()
    {
        $preview = $this->payslip_preview;
        if (empty($preview)) {
            Notification::make()
                ->title('Erreur')
                ->body('Aucune donnée à exporter. Veuillez d\'abord calculer la paie.')
                ->danger()
                ->send();
            return;
        }
        
        $filename = 'fiche_paie_' . str_replace(' ', '_', $preview['employe'] ?? 'employe') . '_' . ($preview['mois'] ?? date('Y-m')) . '.csv';
        
        return Response::streamDownload(function () use ($preview) {
            echo "\xEF\xBB\xBF";
            $out = fopen('php://output', 'w');
            $delimiter = ';';
            
            $writeCSV = function($handle, $data) use ($delimiter) {
                fputcsv($handle, $data, $delimiter);
            };
            
            $csvGenerator = new PayslipCsvGenerator();
            $csvGenerator->generate($out, $writeCSV, $preview);
            
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function exportPdf()
    {
        $pdfExporter = new PayslipPdfExporter();
        return $pdfExporter->export($this->payslip_preview, $this->activeTab);
    }

    public function table(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        if ($this->activeTab === 'grhs') {
            $query = Grh::query()->with(['shift:id,nom']);
            $columns = [
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('name')->label('Nom')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('prenom')->label('Prénom')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('salaire')->label('Salaire horaire')->money('EUR')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('salaire_mensuel')->label('Salaire mensuel')->money('EUR')->sortable()->searchable()->formatStateUsing(fn ($state) => $state ?? 'N/A'),
                Tables\Columns\TextColumn::make('shift.nom')->label('Shift')->sortable()->searchable()->formatStateUsing(fn ($state) => $state ?? '—'),
            ];
        } else {
            $query = Employe::query()->with(['departement:id,nom', 'shift:id,nom']);
            $columns = [
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('name')->label('Nom')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('prenom')->label('Prénom')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('poste')->label('Poste')->sortable()->searchable()->formatStateUsing(fn ($state) => $state ?? '—'),
                Tables\Columns\TextColumn::make('salaire')->label('Salaire horaire')->money('EUR')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('salaire_mensuel')->label('Salaire mensuel')->money('EUR')->sortable()->searchable()->formatStateUsing(fn ($state) => $state ?? 'N/A'),
                Tables\Columns\TextColumn::make('departement.nom')->label('Département')->sortable()->searchable()->formatStateUsing(fn ($state) => $state ?? '—'),
                Tables\Columns\TextColumn::make('shift.nom')->label('Shift')->sortable()->searchable()->formatStateUsing(fn ($state) => $state ?? '—'),
            ];
        }

        return $table
            ->query($query)
            ->columns($columns)
            ->actions($this->getTableActions());
    }

    protected function translateStatus(string $status): string
    {
        return match($status) {
            'en_attente' => 'En attente',
            'validé' => 'Validé',
            'payé' => 'Payé',
            'annulé' => 'Annulé',
            default => ucfirst($status),
        };
    }
}