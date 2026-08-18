<?php

namespace App\Filament\Resources\FillUps;

use App\Filament\Concerns\AuthorizesWithApiPermissions;
use App\Filament\Resources\FillUps\Pages\CreateFillUp;
use App\Filament\Resources\FillUps\Pages\EditFillUp;
use App\Filament\Resources\FillUps\Pages\ListFillUps;
use App\Filament\Resources\FillUps\Schemas\FillUpForm;
use App\Filament\Resources\FillUps\Tables\FillUpsTable;
use App\Models\FillUp;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class FillUpResource extends Resource
{
    use AuthorizesWithApiPermissions;

    /** The `{subject}` half of this resource's `{action}-{subject}` permissions. */
    protected static string $permissionSubject = 'fill-up';

    protected static ?string $model = FillUp::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-beaker';

    protected static string|UnitEnum|null $navigationGroup = 'Activity';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Fill-up';

    protected static ?string $pluralModelLabel = 'Fill-ups';

    public static function form(Schema $schema): Schema
    {
        return FillUpForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FillUpsTable::configure($table);
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
            'index' => ListFillUps::route('/'),
            'create' => CreateFillUp::route('/create'),
            'edit' => EditFillUp::route('/{record}/edit'),
        ];
    }
}
