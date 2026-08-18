<?php

namespace App\Filament\Resources\ServiceCenters;

use App\Filament\Concerns\AuthorizesWithApiPermissions;
use App\Filament\Resources\ServiceCenters\Pages\CreateServiceCenter;
use App\Filament\Resources\ServiceCenters\Pages\EditServiceCenter;
use App\Filament\Resources\ServiceCenters\Pages\ListServiceCenters;
use App\Filament\Resources\ServiceCenters\Schemas\ServiceCenterForm;
use App\Filament\Resources\ServiceCenters\Tables\ServiceCentersTable;
use App\Models\ServiceCenter;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class ServiceCenterResource extends Resource
{
    use AuthorizesWithApiPermissions;

    /** The `{subject}` half of this resource's `{action}-{subject}` permissions. */
    protected static string $permissionSubject = 'service-center';

    protected static ?string $model = ServiceCenter::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static string|UnitEnum|null $navigationGroup = 'Maintenance';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Service center';

    protected static ?string $pluralModelLabel = 'Service centers';

    public static function form(Schema $schema): Schema
    {
        return ServiceCenterForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ServiceCentersTable::configure($table);
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
            'index' => ListServiceCenters::route('/'),
            'create' => CreateServiceCenter::route('/create'),
            'edit' => EditServiceCenter::route('/{record}/edit'),
        ];
    }
}
