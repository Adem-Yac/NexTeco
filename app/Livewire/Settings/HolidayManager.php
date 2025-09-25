<?php

namespace App\Livewire\Settings;

use App\Models\Holiday;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;

class HolidayManager extends Component implements HasForms, HasTable
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
                DatePicker::make('date')
                    ->label('Date')
                    ->required()
                    ->unique(Holiday::class, 'date'),
                
                TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(255),
                
                Select::make('duration')
                    ->label('Durée')
                    ->options([
                        1 => '1 Jour',
                        2 => '2 Jours',
                        3 => '3 Jours',
                    ])
                    ->default(1)
                    ->required(),
                
                Textarea::make('description')
                    ->label('Description')
                    ->rows(3),
                
                Toggle::make('is_recurring')
                    ->label('Jour férié récurrent')
                    ->default(false),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Holiday::query()->orderBy('date', 'desc'))
            ->columns([
                TextColumn::make('date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),
                
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('duration')
                    ->label('Durée')
                    ->suffix(' jour(s)')
                    ->badge()
                    ->color(fn (int $state): string => match ($state) {
                        1 => 'success',
                        2 => 'warning',
                        3 => 'danger',
                        default => 'gray',
                    }),
                
                TextColumn::make('description')
                    ->label('Description')
                    ->limit(50),
                
                ToggleColumn::make('is_recurring')
                    ->label('Récurrent'),
            ])
            ->actions([
                EditAction::make()
                    ->form([
                        DatePicker::make('date')
                            ->label('Date')
                            ->required(),
                        TextInput::make('name')
                            ->label('Nom')
                            ->required()
                            ->maxLength(255),
                        Select::make('duration')
                            ->label('Durée')
                            ->options([
                                1 => '1 Jour',
                                2 => '2 Jours',
                                3 => '3 Jours',
                            ])
                            ->required(),
                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3),
                        Toggle::make('is_recurring')
                            ->label('Jour férié récurrent'),
                    ])
                    ->using(function (Holiday $record, array $data): Holiday {
                        $record->update($data);
                        return $record;
                    })
                    ->successNotificationTitle('Jour férié modifié avec succès'),
                
                DeleteAction::make(),
            ])
            ->headerActions([
                \Filament\Tables\Actions\CreateAction::make()
                    ->form([
                        DatePicker::make('date')
                            ->label('Date')
                            ->required()
                            ->unique(Holiday::class, 'date'),
                        
                        TextInput::make('name')
                            ->label('Nom')
                            ->required()
                            ->maxLength(255),
                        
                        Select::make('duration')
                            ->label('Durée')
                            ->options([
                                1 => '1 Jour',
                                2 => '2 Jours consécutifs',
                                3 => '3 Jours consécutifs',
                            ])
                            ->default(1)
                            ->required(),
                        
                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3),
                        
                        Toggle::make('is_recurring')
                            ->label('Jour férié récurrent')
                            ->default(false),
                    ])
                    ->using(function (array $data): Holiday {
                        return Holiday::create($data);
                    })
                    ->successNotificationTitle('Jour férié créé avec succès'),
            ]);
    }

    public function render()
    {
        return view('livewire.settings.holiday-manager');
    }
}
