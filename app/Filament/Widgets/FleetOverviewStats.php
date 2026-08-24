<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\AuthorizesWidgetWithApiPermission;
use App\Filament\Support\TimeSeries;
use App\Models\Car;
use App\Models\FillUp;
use App\Models\Trip;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/**
 * The instrument cluster: six readings, each with its own six-month trace.
 *
 * Every stat is gated on the permission that guards the table it reads, not
 * just on the widget's own, so an account that may list cars but not users
 * sees the fleet readings without the driver count. The widget as a whole
 * needs `index-car`, which is the narrowest permission any of these readings
 * could plausibly hang from.
 */
class FleetOverviewStats extends StatsOverviewWidget
{
    use AuthorizesWidgetWithApiPermission;

    protected static string $viewPermission = 'index-car';

    protected static ?int $sort = 1;

    /**
     * These are six aggregate queries plus six grouped ones. Re-running them
     * every five seconds — the CanPoll default — buys nothing on a dataset
     * that moves a few rows a day.
     */
    protected ?string $pollingInterval = null;

    protected int | string | array $columnSpan = 'full';

    protected int | array | null $columns = 3;

    /** How many trailing months each sparkline covers. */
    private const TRACE_MONTHS = 6;

    protected function getHeading(): ?string
    {
        return 'Fleet at a glance';
    }

    protected function getDescription(): ?string
    {
        return 'Readings for '.CarbonImmutable::now()->format('F Y').', measured against the month before.';
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $now = CarbonImmutable::now();
        $monthStart = $now->startOfMonth();
        $previousMonthStart = $monthStart->subMonth();

        $stats = [];

        if (static::currentUserCan('index-user')) {
            $stats[] = $this->reading(
                label: 'Drivers',
                value: Number::format(User::query()->count()),
                current: (float) User::query()->where('created_at', '>=', $monthStart)->count(),
                previous: (float) User::query()
                    ->whereBetween('created_at', [$previousMonthStart, $monthStart])
                    ->count(),
                movement: 'joined this month',
                icon: 'heroicon-m-user-group',
                trace: $this->monthlyTrace(User::query(), 'created_at'),
            );
        }

        if (static::currentUserCan('index-car')) {
            $stats[] = $this->reading(
                label: 'Cars registered',
                value: Number::format(Car::query()->count()),
                current: (float) Car::query()->where('created_at', '>=', $monthStart)->count(),
                previous: (float) Car::query()
                    ->whereBetween('created_at', [$previousMonthStart, $monthStart])
                    ->count(),
                movement: 'added this month',
                icon: 'heroicon-m-truck',
                trace: $this->monthlyTrace(Car::query(), 'created_at'),
            );
        }

        if (static::currentUserCan('index-fill-up')) {
            $fillUpsThisMonth = (float) FillUp::query()
                ->whereDate('fill_date', '>=', $monthStart)
                ->count();

            $stats[] = $this->reading(
                label: 'Fill-ups this month',
                value: Number::format($fillUpsThisMonth),
                current: $fillUpsThisMonth,
                previous: (float) FillUp::query()
                    ->whereDate('fill_date', '>=', $previousMonthStart)
                    ->whereDate('fill_date', '<', $monthStart)
                    ->count(),
                movement: 'vs last month',
                icon: 'heroicon-m-beaker',
                trace: $this->monthlyTrace(FillUp::query(), 'fill_date'),
                asChange: true,
            );

            $litresThisMonth = (float) FillUp::query()
                ->whereDate('fill_date', '>=', $monthStart)
                ->sum('liters');

            $stats[] = $this->reading(
                label: 'Litres pumped',
                value: Number::format($litresThisMonth, 0).' L',
                current: $litresThisMonth,
                previous: (float) FillUp::query()
                    ->whereDate('fill_date', '>=', $previousMonthStart)
                    ->whereDate('fill_date', '<', $monthStart)
                    ->sum('liters'),
                movement: 'vs last month',
                icon: 'heroicon-m-fire',
                trace: $this->monthlyTrace(FillUp::query(), 'fill_date', 'sum', 'liters'),
                asChange: true,
            );

            $spendThisMonth = (float) FillUp::query()
                ->whereDate('fill_date', '>=', $monthStart)
                ->sum('cost_egp');

            $stats[] = $this->reading(
                label: 'Fuel spend',
                value: 'EGP '.Number::format($spendThisMonth, 0),
                current: $spendThisMonth,
                previous: (float) FillUp::query()
                    ->whereDate('fill_date', '>=', $previousMonthStart)
                    ->whereDate('fill_date', '<', $monthStart)
                    ->sum('cost_egp'),
                movement: 'vs last month',
                icon: 'heroicon-m-banknotes',
                trace: $this->monthlyTrace(FillUp::query(), 'fill_date', 'sum', 'cost_egp'),
                asChange: true,
            );
        }

        if (static::currentUserCan('index-trip')) {
            $distanceThisMonth = (float) Trip::query()
                ->where('created_at', '>=', $monthStart)
                ->sum('total_distance_km');

            $stats[] = $this->reading(
                label: 'Distance logged',
                value: Number::format((float) Trip::query()->sum('total_distance_km'), 0).' km',
                current: $distanceThisMonth,
                previous: (float) Trip::query()
                    ->whereBetween('created_at', [$previousMonthStart, $monthStart])
                    ->sum('total_distance_km'),
                movement: 'km this month',
                icon: 'heroicon-m-map',
                trace: $this->monthlyTrace(Trip::query(), 'created_at', 'sum', 'total_distance_km'),
                movementValue: Number::format($distanceThisMonth, 0),
            );
        }

        return $stats;
    }

    /**
     * One reading: a headline figure, a trend note, and a six-month trace.
     *
     * $asChange switches the trend note between "what moved" (e.g. 4 joined
     * this month) and "how much it moved" (e.g. +12% vs last month). A running
     * total like distance logged only makes sense as the former; a per-month
     * measure like spend reads better as the latter.
     *
     * @param  list<float>  $trace
     */
    private function reading(
        string $label,
        string $value,
        float $current,
        float $previous,
        string $movement,
        string $icon,
        array $trace,
        bool $asChange = false,
        ?string $movementValue = null,
    ): Stat {
        $direction = $current <=> $previous;

        if ($asChange) {
            $description = $previous > 0.0
                ? sprintf('%+.0f%% %s', (($current - $previous) / $previous) * 100, $movement)
                : ($current > 0.0 ? 'First activity on record' : 'No activity yet');
        } else {
            $description = trim(($movementValue ?? Number::format($current)).' '.$movement);
        }

        return Stat::make($label, $value)
            ->icon($icon)
            ->description($description)
            ->descriptionIcon(match ($direction) {
                1 => 'heroicon-m-arrow-trending-up',
                -1 => 'heroicon-m-arrow-trending-down',
                default => 'heroicon-m-minus-small',
            })
            ->descriptionColor(match ($direction) {
                1 => 'success',
                -1 => 'danger',
                default => 'gray',
            })
            ->chart($trace)
            ->chartColor(match ($direction) {
                1 => 'success',
                -1 => 'danger',
                default => 'gray',
            });
    }

    /**
     * A dense six-month series for the sparkline, zeros included.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  'count'|'sum'  $function
     * @return list<float>
     */
    private function monthlyTrace(
        \Illuminate\Database\Eloquent\Builder $query,
        string $dateColumn,
        string $function = 'count',
        ?string $valueColumn = null,
    ): array {
        $now = CarbonImmutable::now();
        $start = $now->startOfMonth()->subMonths(self::TRACE_MONTHS - 1);

        $keys = TimeSeries::keys($start, $now, TimeSeries::MONTH);

        $values = TimeSeries::aggregate(
            $query->where($dateColumn, '>=', $start),
            $dateColumn,
            TimeSeries::MONTH,
            $function,
            $valueColumn,
        );

        return TimeSeries::series($keys, $values, 0);
    }
}
