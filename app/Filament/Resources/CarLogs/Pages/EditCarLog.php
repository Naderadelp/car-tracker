<?php

namespace App\Filament\Resources\CarLogs\Pages;

use App\Filament\Resources\CarLogs\CarLogResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCarLog extends EditRecord
{
    protected static string $resource = CarLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
