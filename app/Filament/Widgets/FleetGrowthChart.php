<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\AuthorizesWidgetWithApiPermission;
use App\Filament\Support\DashboardPalette;
use App\Filament\Support\TimeSeries;
use App\Models\Car;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

/**
 * Sign-ups against cars registered.
 *
 * The two bars are worth reading together because the product allows one car
 * per account: the gap between them is the population of drivers who signed up
 * and never got as far as entering a vehicle. Cars are drawn in slate rather
 * than a second saturated hue so the driver bar stays the primary read.
 */
class FleetGrowthChart extends ChartWidget
{
    use AuthorizesWidgetWithApiPermission;

    protected static string $viewPermission = 'index-user';

    protected static ?int $sort = 3;

    protected ?string $pollingInterval = null;

    protected int | string | array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    public ?string $filter = '12m';

    protected function getType(): string
    {
        return 'bar';
    }

    public function getHeading(): ?string
    {
        return 'Drivers and cars joining';
    }

    public function getDescription(): ?string
    {
        return match ($this->filter) {
            '30d' => 'New accounts and vehicles per day, last thirty days.',
            '24m' => 'New accounts and vehicles per month, last two years.',
            default => 'New accounts and vehicles per month, last twelve months.',
        };
    }

    /**
     * @return array<string, string>
     */
    protected function getFilters(): ?array
    {
        return [
            '30d' => 'Last 30 days',
            '12m' => 'Last 12 months',
            '24m' => 'Last 24 months',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        [$start, $end, $unit] = $this->window();

        $keys = TimeSeries::keys($start, $end, $unit);

        $drivers = TimeSeries::aggregate(
            User::query()->whereBetween('created_at', [$start, $end]),
            'created_at',
            $unit,
        );

        $datasets = [
            [
                'label' => 'Drivers',
                'data' => TimeSeries::series($keys, $drivers, 0),
                'backgroundColor' => DashboardPalette::PETROL,
                'borderRadius' => 3,
            ],
        ];

        // Cars come from a different subject, so they are only charted for an
        // account that is allowed to list them.
        if (static::currentUserCan('index-car')) {
            $cars = TimeSeries::aggregate(
                Car::query()->whereBetween('created_at', [$start, $end]),
                'created_at',
                $unit,
            );

            $datasets[] = [
                'label' => 'Cars',
                'data' => TimeSeries::series($keys, $cars, 0),
                'backgroundColor' => DashboardPalette::SLATE,
                'borderRadius' => 3,
            ];
        }

        return [
            'labels' => TimeSeries::labels($keys, $unit),
            'datasets' => $datasets,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'interaction' => [
                'mode' => 'index',
                'intersect' => false,
            ],
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'labels' => [
                        'usePointStyle' => true,
                        'boxWidth' => 8,
                        'boxHeight' => 8,
                    ],
                ],
            ],
            'scales' => [
                'x' => [
                    'grid' => ['display' => false],
                ],
                'y' => [
                    'beginAtZero' => true,
                    // Whole accounts only; a half a driver is not a thing.
                    'ticks' => ['precision' => 0],
                ],
            ],
        ];
    }

    /**
     * @return array{CarbonImmutable, CarbonImmutable, string}
     */
    private function window(): array
    {
        $now = CarbonImmutable::now();

        return match ($this->filter) {
            '30d' => [$now->subDays(29)->startOfDay(), $now, TimeSeries::DAY],
            '24m' => [$now->startOfMonth()->subMonths(23), $now, TimeSeries::MONTH],
            default => [$now->startOfMonth()->subMonths(11), $now, TimeSeries::MONTH],
        };
    }
}
