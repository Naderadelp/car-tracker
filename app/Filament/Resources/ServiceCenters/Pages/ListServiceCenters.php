<?php

namespace App\Filament\Resources\ServiceCenters\Pages;

use App\Filament\Resources\ServiceCenters\ServiceCenterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListServiceCenters extends ListRecords
{
    protected static string $resource = ServiceCenterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
