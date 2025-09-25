<?php

namespace App\Filament\Resources\GrhResource\Pages;

use App\Filament\Resources\GrhResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGrh extends EditRecord
{
    protected static string $resource = GrhResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
