<?php

namespace App\Filament\Resources\FillUps\Pages;

use App\Filament\Resources\FillUps\FillUpResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFillUps extends ListRecords
{
    protected static string $resource = FillUpResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
