<?php

namespace App\Filament\Resources\FuelPrices\Pages;

use App\Filament\Resources\FuelPrices\FuelPriceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFuelPrice extends EditRecord
{
    protected static string $resource = FuelPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
