<?php

namespace App\Filament\Resources\Documents;

use App\Filament\Concerns\AuthorizesWithApiPermissions;
use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Filament\Resources\Documents\Pages\EditDocument;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Filament\Resources\Documents\Schemas\DocumentForm;
use App\Filament\Resources\Documents\Tables\DocumentsTable;
use App\Models\Document;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class DocumentResource extends Resource
{
    use AuthorizesWithApiPermissions;

    /** The `{subject}` half of this resource's `{action}-{subject}` permissions. */
    protected static string $permissionSubject = 'document';

    protected static ?string $model = Document::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|UnitEnum|null $navigationGroup = 'Logbook';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return DocumentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DocumentsTable::configure($table);
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
            'index' => ListDocuments::route('/'),
            'create' => CreateDocument::route('/create'),
            'edit' => EditDocument::route('/{record}/edit'),
        ];
    }

    /**
     * How many documents have lapsed or are about to.
     *
     * A badge earns its place only when the number implies an action, so this
     * counts what needs chasing rather than how many rows the table holds.
     * Filament resolves it only for a navigation item the user can already
     * see, which means viewAny — and therefore `index-document` — has passed.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = static::lapsingQuery()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return static::lapsingQuery()
            ->whereDate('expiry_date', '<', now())
            ->exists()
            ? 'danger'
            : 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Expired, or expiring within 30 days';
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Document>
     */
    protected static function lapsingQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Document::query()
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', now()->addDays(30));
    }
}
