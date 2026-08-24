<?php

namespace App\Filament\Resources\Reminders;

use App\Filament\Concerns\AuthorizesWithApiPermissions;
use App\Filament\Resources\Reminders\Pages\CreateReminder;
use App\Filament\Resources\Reminders\Pages\EditReminder;
use App\Filament\Resources\Reminders\Pages\ListReminders;
use App\Filament\Resources\Reminders\Schemas\ReminderForm;
use App\Filament\Resources\Reminders\Tables\RemindersTable;
use App\Models\Reminder;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class ReminderResource extends Resource
{
    use AuthorizesWithApiPermissions;

    /** The `{subject}` half of this resource's `{action}-{subject}` permissions. */
    protected static string $permissionSubject = 'reminder';

    protected static ?string $model = Reminder::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static string|UnitEnum|null $navigationGroup = 'Logbook';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return ReminderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RemindersTable::configure($table);
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
            'index' => ListReminders::route('/'),
            'create' => CreateReminder::route('/create'),
            'edit' => EditReminder::route('/{record}/edit'),
        ];
    }

    /**
     * How many reminders came due without going out.
     *
     * `notified_at` carries the whole meaning here: a reminder that already
     * fired is finished work, and counting it would train people to ignore
     * the badge. Filament resolves this only for a navigation item the user
     * can already see, which means viewAny — and therefore `index-reminder` —
     * has passed.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = Reminder::query()
            ->whereNull('notified_at')
            ->whereNotNull('remind_on')
            ->whereDate('remind_on', '<=', now())
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Due, and not yet sent';
    }
}
