<?php

namespace App\Filament\Resources\FillUps\Pages;

use App\Filament\Resources\FillUps\FillUpResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFillUp extends EditRecord
{
    protected static string $resource = FillUpResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
