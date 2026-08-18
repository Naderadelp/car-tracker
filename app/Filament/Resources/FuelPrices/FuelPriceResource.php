<?php

namespace App\Filament\Resources\FuelPrices;

use App\Filament\Concerns\AuthorizesWithApiPermissions;
use App\Filament\Resources\FuelPrices\Pages\CreateFuelPrice;
use App\Filament\Resources\FuelPrices\Pages\EditFuelPrice;
use App\Filament\Resources\FuelPrices\Pages\ListFuelPrices;
use App\Filament\Resources\FuelPrices\Schemas\FuelPriceForm;
use App\Filament\Resources\FuelPrices\Tables\FuelPricesTable;
use App\Models\FuelPrice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class FuelPriceResource extends Resource
{
    use AuthorizesWithApiPermissions;

    /** The `{subject}` half of this resource's `{action}-{subject}` permissions. */
    protected static string $permissionSubject = 'fuel-price';

    protected static ?string $model = FuelPrice::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static string|UnitEnum|null $navigationGroup = 'Reference';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Fuel price';

    protected static ?string $pluralModelLabel = 'Fuel prices';

    public static function form(Schema $schema): Schema
    {
        return FuelPriceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FuelPricesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFuelPrices::route('/'),
            'create' => CreateFuelPrice::route('/create'),
            'edit' => EditFuelPrice::route('/{record}/edit'),
        ];
    }
}
