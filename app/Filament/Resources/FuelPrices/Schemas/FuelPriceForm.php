<?php

namespace App\Filament\Resources\FuelPrices\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class FuelPriceForm
{
    /**
     * Mirrors the `type` enum on the fuel_prices table and
     * App\Http\Requests\FuelPrice\StoreFuelPriceRequest.
     */
    public const TYPES = [
        '92' => '92 octane',
        '95' => '95 octane',
        'electric' => 'Electric',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('type')
                    ->options(self::TYPES)
                    ->required()
                    ->native(false),
                TextInput::make('price_per_unit')
                    ->label('Price per unit')
                    ->required()
                    ->numeric()
                    ->minValue(0.01)
                    ->step(0.01)
                    ->prefix('EGP'),
                DatePicker::make('effective_from')
                    ->label('Effective from')
                    ->required()
                    ->default(now()),
            ]);
    }
}
