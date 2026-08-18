<?php

namespace App\Filament\Resources\ServiceCenters\Pages;

use App\Filament\Resources\ServiceCenters\ServiceCenterResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditServiceCenter extends EditRecord
{
    protected static string $resource = ServiceCenterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
