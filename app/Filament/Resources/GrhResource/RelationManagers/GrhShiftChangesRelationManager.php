<?php

namespace App\Filament\Resources\GrhResource\RelationManagers;

use App\Models\Shift;
use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\RelationManagers\RelationManager;

class GrhShiftChangesRelationManager extends RelationManager
{
    protected static string $relationship = 'shiftChanges'; // doit correspondre à la méthode dans Employe.php
    protected static ?string $title = 'Historique des shifts';
    protected static ?string $recordTitleAttribute = 'date_debut'; // champ par défaut affiché

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DatePicker::make('date_debut')
                ->label('Date de début du shift')
                ->required(),

            Forms\Components\Select::make('shift_id')
                ->label('Nouveau shift')
                ->options(Shift::all()->pluck('nom', 'id'))
                ->searchable()
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date_debut')->label('Date début'),
                Tables\Columns\TextColumn::make('shift.nom')->label('Nom du shift'),
                Tables\Columns\TextColumn::make('shift.heure_debut')->label('Heure début'),
                Tables\Columns\TextColumn::make('shift.heure_fin')->label('Heure fin'),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
