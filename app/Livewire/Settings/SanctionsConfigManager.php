<?php

namespace App\Livewire\Settings;

use App\Models\SanctionsConfig;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;

class SanctionsConfigManager extends Component implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('type')
                    ->label('Type')
                    ->options([
                        'retards' => 'Retards',
                        'absences' => 'Absences',
                    ])
                    ->required(),
                
                TextInput::make('threshold')
                    ->label('Seuil')
                    ->numeric()
                    ->integer()
                    ->required()
                    ->helperText('Nombre d\'occurrences nécessaires pour déclencher cette sanction'),
                
                TextInput::make('hours_deducted')
                    ->label('Heures déduites')
                    ->numeric()
                    ->step(0.25)
                    ->required()
                    ->helperText('Heures à déduire du salaire lorsque ce seuil est atteint'),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(SanctionsConfig::query()->orderBy('type')->orderBy('threshold'))
            ->columns([
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'retards' => 'warning',
                        'absences' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'retards' => 'Retards',
                        'absences' => 'Absences',
                        default => $state,
                    }),
                
                TextColumn::make('threshold')
                    ->label('Seuil')
                    ->suffix(' occurrence(s)')
                    ->sortable(),
                
                TextColumn::make('hours_deducted')
                    ->label('Heures déduites')
                    ->suffix(' h')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => number_format($state, 2)),
                
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->date('d M Y')
                    ->sortable(),
            ])
            ->actions([
                EditAction::make()
                    ->form([
                        Select::make('type')
                            ->label('Type')
                            ->options([
                                'retards' => 'Retards',
                                'absences' => 'Absences',
                            ])
                            ->required()
                            ->disabled(),
                        
                        TextInput::make('threshold')
                            ->label('Seuil')
                            ->numeric()
                            ->integer()
                            ->required(),
                        
                        TextInput::make('hours_deducted')
                            ->label('Heures déduites')
                            ->numeric()
                            ->step(0.25)
                            ->required(),
                    ])
                    ->using(function (SanctionsConfig $record, array $data): SanctionsConfig {
                        $record->update($data);
                        return $record;
                    })
                    ->successNotificationTitle('Règle de sanction modifiée avec succès'),
                
                DeleteAction::make(),
            ])
            ->headerActions([
                \Filament\Tables\Actions\CreateAction::make()
                    ->form([
                        Select::make('type')
                            ->label('Type')
                            ->options([
                                'retards' => 'Retards',
                                'absences' => 'Absences',
                            ])
                            ->required(),
                        
                        TextInput::make('threshold')
                            ->label('Seuil')
                            ->numeric()
                            ->integer()
                            ->required()
                            ->default(1),
                        
                        TextInput::make('hours_deducted')
                            ->label('Heures déduites')
                            ->numeric()
                            ->step(0.25)
                            ->required()
                            ->default(1.0),
                    ])
                    ->using(function (array $data): SanctionsConfig {
                        return SanctionsConfig::create($data);
                    })
                    ->successNotificationTitle('Règle de sanction créée avec succès'),
            ]);
    }

    public function render()
    {
        return view('livewire.settings.sanctions-config-manager');
    }
}
