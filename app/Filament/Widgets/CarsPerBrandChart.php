<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\AuthorizesWidgetWithApiPermission;
use App\Filament\Support\DashboardPalette;
use App\Models\Car;
use Filament\Widgets\ChartWidget;

/**
 * The ten marques the fleet is actually made of.
 *
 * Bars run horizontally because brand names are words, and words read better
 * along a baseline than rotated under one. One colour throughout: this is a
 * ranking of a single quantity, and giving each bar its own hue would imply a
 * categorical difference that is not in the data.
 */
class CarsPerBrandChart extends ChartWidget
{
    use AuthorizesWidgetWithApiPermission;

    protected static string $viewPermission = 'index-car';

    protected static ?int $sort = 5;

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '280px';

    /** How many marques the chart will show before it stops. */
    private const LIMIT = 10;

    protected function getType(): string
    {
        return 'bar';
    }

    public function getHeading(): ?string
    {
        return 'Cars by brand';
    }

    public function getDescription(): ?string
    {
        $unassigned = Car::query()->whereNull('brand_id')->count();

        return $unassigned > 0
            ? sprintf('Top %d marques. %d car(s) have no brand recorded.', self::LIMIT, $unassigned)
            : sprintf('The %d most common marques in the fleet.', self::LIMIT);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $rows = Car::query()
            ->join('brands', 'brands.id', '=', 'cars.brand_id')
            ->toBase()
            ->selectRaw('brands.name as brand, count(*) as aggregate')
            ->groupBy('brands.name')
            ->orderByDesc('aggregate')
            ->orderBy('brands.name')
            ->limit(self::LIMIT)
            ->get();

        return [
            'labels' => $rows->pluck('brand')->all(),
            'datasets' => [
                [
                    'label' => 'Cars',
                    'data' => $rows->pluck('aggregate')->map(fn ($count): int => (int) $count)->all(),
                    'backgroundColor' => DashboardPalette::PETROL,
                    'borderRadius' => 3,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                ],
                'y' => [
                    'grid' => ['display' => false],
                ],
            ],
        ];
    }
}
