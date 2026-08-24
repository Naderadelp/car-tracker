<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\AuthorizesWidgetWithApiPermission;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Documents\Schemas\DocumentForm;
use App\Models\Document;
use Carbon\CarbonImmutable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Papers that have run out, or are about to.
 *
 * Sorted by how little time is left rather than by anything the admin chose,
 * because the only question this table answers is what to deal with first.
 * Rows already past their date sort to the top with a red badge; the rest
 * shade from amber to grey as the deadline recedes.
 */
class LapsingDocumentsTable extends TableWidget
{
    use AuthorizesWidgetWithApiPermission;

    protected static string $viewPermission = 'index-document';

    protected static ?int $sort = 6;

    protected int | string | array $columnSpan = 'full';

    /** How far ahead counts as "about to lapse". */
    private const HORIZON_DAYS = 60;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Document::query()
                    ->with(['user', 'car.brand', 'car.carModel'])
                    ->whereNotNull('expiry_date')
                    ->whereDate('expiry_date', '<=', CarbonImmutable::now()->addDays(self::HORIZON_DAYS))
                    ->orderBy('expiry_date')
            )
            ->heading('Papers running out')
            ->description('Vehicle documents already expired, or lapsing within '.self::HORIZON_DAYS.' days.')
            ->columns([
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Document $record): string => $this->countdown($record->expiry_date))
                    ->color(fn (Document $record): string => $this->urgencyColor($record->expiry_date)),
                TextColumn::make('type')
                    ->label('Document')
                    ->formatStateUsing(fn (?string $state): string => DocumentForm::typeOptions()[$state] ?? (string) $state)
                    ->wrap(),
                TextColumn::make('user.name')
                    ->label('Owner')
                    ->placeholder('Unassigned'),
                TextColumn::make('car')
                    ->label('Car')
                    ->state(fn (Document $record): string => $this->carLabel($record))
                    ->color('gray'),
                TextColumn::make('expiry_date')
                    ->label('Expires')
                    ->date('j M Y')
                    ->alignEnd(),
            ])
            // Only offer the row as a link to an account that may actually open
            // it; DocumentResource::canEdit() runs the same api-guard check the
            // edit page itself would.
            ->recordUrl(fn (Document $record): ?string => DocumentResource::canEdit($record)
                ? DocumentResource::getUrl('edit', ['record' => $record])
                : null)
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateIcon('heroicon-o-check-badge')
            ->emptyStateHeading('Nothing lapsing')
            ->emptyStateDescription('No document on file expires in the next '.self::HORIZON_DAYS.' days.');
    }

    /**
     * Whole days from today until the date, negative once it has passed.
     */
    private function daysLeft(mixed $expiry): ?int
    {
        if (! $expiry instanceof \DateTimeInterface) {
            return null;
        }

        return (int) CarbonImmutable::now()->startOfDay()->diffInDays(
            CarbonImmutable::instance($expiry)->startOfDay(),
            absolute: false,
        );
    }

    /**
     * How long is left, in the shortest phrase that is still exact enough to act on.
     */
    private function countdown(mixed $expiry): string
    {
        $days = $this->daysLeft($expiry);

        return match (true) {
            $days === null => 'No date',
            $days < 0 => abs($days).' days ago',
            $days === 0 => 'Today',
            $days === 1 => 'Tomorrow',
            default => $days.' days left',
        };
    }

    /**
     * Red once the date has passed, amber inside a fortnight, grey beyond it.
     */
    private function urgencyColor(mixed $expiry): string
    {
        $days = $this->daysLeft($expiry);

        return match (true) {
            $days === null => 'gray',
            $days < 0 => 'danger',
            $days <= 14 => 'warning',
            default => 'gray',
        };
    }

    /**
     * A car has no name of its own — it is identified by its marque and model.
     */
    private function carLabel(Document $record): string
    {
        $car = $record->car;

        if ($car === null) {
            return 'No car linked';
        }

        $name = trim(($car->brand?->name ?? '').' '.($car->carModel?->name ?? ''));

        return $name !== '' ? $name : 'Car #'.$car->getKey();
    }
}
