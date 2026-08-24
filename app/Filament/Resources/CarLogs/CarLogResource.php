<?php

namespace App\Filament\Resources\CarLogs;

use App\Filament\Concerns\AuthorizesWithApiPermissions;
use App\Filament\Resources\CarLogs\Pages\CreateCarLog;
use App\Filament\Resources\CarLogs\Pages\EditCarLog;
use App\Filament\Resources\CarLogs\Pages\ListCarLogs;
use App\Filament\Resources\CarLogs\Schemas\CarLogForm;
use App\Filament\Resources\CarLogs\Tables\CarLogsTable;
use App\Models\CarLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class CarLogResource extends Resource
{
    use AuthorizesWithApiPermissions;

    /** The `{subject}` half of this resource's `{action}-{subject}` permissions. */
    protected static string $permissionSubject = 'car-log';

    protected static ?string $model = CarLog::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Logbook';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'Car log';

    protected static ?string $pluralModelLabel = 'Car logs';

    public static function form(Schema $schema): Schema
    {
        return CarLogForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CarLogsTable::configure($table);
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
            'index' => ListCarLogs::route('/'),
            'create' => CreateCarLog::route('/create'),
            'edit' => EditCarLog::route('/{record}/edit'),
        ];
    }
}
