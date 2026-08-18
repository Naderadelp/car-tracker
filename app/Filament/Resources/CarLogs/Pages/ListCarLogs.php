<?php

namespace App\Filament\Resources\CarLogs\Pages;

use App\Filament\Resources\CarLogs\CarLogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCarLogs extends ListRecords
{
    protected static string $resource = CarLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
