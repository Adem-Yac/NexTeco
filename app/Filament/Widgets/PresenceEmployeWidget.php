<?php

namespace App\Filament\Widgets;

use Filament\Widgets\TableWidget;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Presence;
use Illuminate\Support\Facades\Auth;
use Filament\Facades\Filament;


class PresenceEmployeWidget extends TableWidget
{
    protected static ?int $sort = 2;
    protected static ?string $heading = 'Mes Présences';

    public function getTableQuery(): Builder
    {
        $user = Auth::guard('employe')->user();

        return Presence::query()
            ->where('user_type', 'employe')
            ->where('user_id', $user?->id)
            ->latest();
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('date')
                ->label('Date')
                ->date('Y-m-d'),
            Tables\Columns\TextColumn::make('check_in')
                ->label('Check In')
                ->time('H:i'),
            Tables\Columns\TextColumn::make('check_out')
                ->label('Check Out')
                ->time('H:i'),
            Tables\Columns\TextColumn::make('etat')
                ->label('Etat Présence')
                ->formatStateUsing(fn ($state) => match ($state) {
                    'present' => 'Présent',
                    'retard' => 'Retard',
                    'absent' => 'Absent',
                    default => ucfirst($state),
                }),
        ];
    }
     public static function canView(): bool
    {
        return Filament::getCurrentPanel()?->getId() == 'employe';
    }

    public function getColumnSpan(): int | string | array
    {
        return 'full'; // ou 2, 3, etc.
    }

    protected function getTableContentGridClass(): ?string
    {
        // Ajoute une classe utilitaire pour l'ombre
        return 'shadow-2xl';
    }
}
