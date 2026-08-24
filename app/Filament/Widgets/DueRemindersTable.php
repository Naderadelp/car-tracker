<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\AuthorizesWidgetWithApiPermission;
use App\Filament\Resources\Reminders\ReminderResource;
use App\Models\Reminder;
use Carbon\CarbonImmutable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Reminders that came due and nobody has been told about.
 *
 * `notified_at` is the whole point of the filter: a reminder whose date has
 * passed but which has already gone out is finished work, and putting it on
 * the dashboard would train people to ignore the list. Only unsent, due, or
 * nearly-due rows appear.
 */
class DueRemindersTable extends TableWidget
{
    use AuthorizesWidgetWithApiPermission;

    protected static string $viewPermission = 'index-reminder';

    protected static ?int $sort = 7;

    protected int | string | array $columnSpan = 'full';

    /** How far ahead a reminder counts as worth flagging. */
    private const HORIZON_DAYS = 14;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Reminder::query()
                    ->with(['car.brand', 'car.carModel', 'car.user'])
                    ->whereNull('notified_at')
                    ->whereNotNull('remind_on')
                    ->whereDate('remind_on', '<=', CarbonImmutable::now()->addDays(self::HORIZON_DAYS))
                    ->orderBy('remind_on')
            )
            ->heading('Reminders waiting to go out')
            ->description('Due now, or within '.self::HORIZON_DAYS.' days, and not yet sent.')
            ->columns([
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Reminder $record): string => $this->countdown($record->remind_on))
                    ->color(fn (Reminder $record): string => $this->urgencyColor($record->remind_on)),
                TextColumn::make('title')
                    ->label('Reminder')
                    ->placeholder('Untitled')
                    ->wrap(),
                TextColumn::make('car')
                    ->label('Car')
                    ->state(fn (Reminder $record): string => $this->carLabel($record))
                    ->description(fn (Reminder $record): ?string => $record->car?->user?->name),
                TextColumn::make('remind_at_km')
                    ->label('Odometer target')
                    ->numeric()
                    ->suffix(' km')
                    ->placeholder('Date only')
                    ->alignEnd(),
                TextColumn::make('remind_on')
                    ->label('Due')
                    ->date('j M Y')
                    ->alignEnd(),
            ])
            ->recordUrl(fn (Reminder $record): ?string => ReminderResource::canEdit($record)
                ? ReminderResource::getUrl('edit', ['record' => $record])
                : null)
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateIcon('heroicon-o-bell-slash')
            ->emptyStateHeading('Nothing waiting')
            ->emptyStateDescription('No unsent reminder falls due in the next '.self::HORIZON_DAYS.' days.');
    }

    /**
     * Whole days from today until the date, negative once it has passed.
     */
    private function daysLeft(mixed $due): ?int
    {
        if (! $due instanceof \DateTimeInterface) {
            return null;
        }

        return (int) CarbonImmutable::now()->startOfDay()->diffInDays(
            CarbonImmutable::instance($due)->startOfDay(),
            absolute: false,
        );
    }

    private function countdown(mixed $due): string
    {
        $days = $this->daysLeft($due);

        return match (true) {
            $days === null => 'No date',
            $days < 0 => abs($days).' days overdue',
            $days === 0 => 'Due today',
            $days === 1 => 'Due tomorrow',
            default => 'In '.$days.' days',
        };
    }

    private function urgencyColor(mixed $due): string
    {
        $days = $this->daysLeft($due);

        return match (true) {
            $days === null => 'gray',
            $days < 0 => 'danger',
            $days <= 3 => 'warning',
            default => 'gray',
        };
    }

    /**
     * A car has no name of its own — it is identified by its marque and model.
     */
    private function carLabel(Reminder $record): string
    {
        $car = $record->car;

        if ($car === null) {
            return 'No car linked';
        }

        $name = trim(($car->brand?->name ?? '').' '.($car->carModel?->name ?? ''));

        return $name !== '' ? $name : 'Car #'.$car->getKey();
    }
}
