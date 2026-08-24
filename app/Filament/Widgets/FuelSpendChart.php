<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\AuthorizesWidgetWithApiPermission;
use App\Filament\Support\DashboardPalette;
use App\Filament\Support\TimeSeries;
use App\Models\FillUp;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

/**
 * What the fleet spent on fuel, against how much fuel it actually took on.
 *
 * The two measures share an x axis but not a y axis: pounds and litres are
 * different units, and forcing them onto one scale would make the cheaper
 * grades look like a volume collapse. The volume line is dashed as well as
 * amber so the axis it belongs to survives a greyscale print or a colour
 * vision deficiency.
 */
class FuelSpendChart extends ChartWidget
{
    use AuthorizesWidgetWithApiPermission;

    protected static string $viewPermission = 'index-fill-up';

    protected static ?int $sort = 2;

    protected ?string $pollingInterval = null;

    protected int | string | array $columnSpan = 'full';

    protected ?string $maxHeight = '320px';

    public ?string $filter = '12m';

    protected function getType(): string
    {
        return 'line';
    }

    public function getHeading(): ?string
    {
        return 'Fuel spend and volume';
    }

    public function getDescription(): ?string
    {
        return match ($this->filter) {
            '7d' => 'Every fill-up recorded in the last seven days.',
            '30d' => 'Daily totals across the last thirty days.',
            default => 'Monthly totals across the last twelve months.',
        };
    }

    /**
     * @return array<string, string>
     */
    protected function getFilters(): ?array
    {
        return [
            '7d' => 'Last 7 days',
            '30d' => 'Last 30 days',
            '12m' => 'Last 12 months',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        [$start, $end, $unit] = $this->window();

        $keys = TimeSeries::keys($start, $end, $unit);

        $spend = TimeSeries::aggregate(
            $this->scopedFillUps($start, $end),
            'fill_date',
            $unit,
            'sum',
            'cost_egp',
        );

        $litres = TimeSeries::aggregate(
            $this->scopedFillUps($start, $end),
            'fill_date',
            $unit,
            'sum',
            'liters',
        );

        return [
            'labels' => TimeSeries::labels($keys, $unit),
            'datasets' => [
                [
                    'label' => 'Spend (EGP)',
                    'data' => TimeSeries::series($keys, $spend),
                    'borderColor' => DashboardPalette::PETROL,
                    'backgroundColor' => DashboardPalette::PETROL_FILL,
                    'borderWidth' => 2,
                    'fill' => true,
                    'tension' => 0.35,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Volume (litres)',
                    'data' => TimeSeries::series($keys, $litres),
                    'borderColor' => DashboardPalette::SIGNAL,
                    'backgroundColor' => DashboardPalette::SIGNAL_FILL,
                    'borderWidth' => 2,
                    'borderDash' => [5, 4],
                    'fill' => false,
                    'tension' => 0.35,
                    'yAxisID' => 'y1',
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
                'y' => [
                    'position' => 'left',
                    'beginAtZero' => true,
                    'title' => ['display' => true, 'text' => 'EGP'],
                ],
                'y1' => [
                    'position' => 'right',
                    'beginAtZero' => true,
                    'title' => ['display' => true, 'text' => 'Litres'],
                    // The second axis borrows the first one's gridlines rather
                    // than drawing a competing set over the plot area.
                    'grid' => ['drawOnChartArea' => false],
                ],
            ],
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<FillUp>
     */
    private function scopedFillUps(CarbonImmutable $start, CarbonImmutable $end): \Illuminate\Database\Eloquent\Builder
    {
        return FillUp::query()->whereBetween('fill_date', [
            $start->toDateString(),
            $end->toDateString(),
        ]);
    }

    /**
     * The window and bucket size the current filter asks for.
     *
     * @return array{CarbonImmutable, CarbonImmutable, string}
     */
    private function window(): array
    {
        $now = CarbonImmutable::now();

        return match ($this->filter) {
            '7d' => [$now->subDays(6)->startOfDay(), $now, TimeSeries::DAY],
            '30d' => [$now->subDays(29)->startOfDay(), $now, TimeSeries::DAY],
            default => [$now->startOfMonth()->subMonths(11), $now, TimeSeries::MONTH],
        };
    }
}
