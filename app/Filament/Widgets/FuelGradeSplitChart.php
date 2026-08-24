<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\AuthorizesWidgetWithApiPermission;
use App\Filament\Support\DashboardPalette;
use App\Models\FillUp;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Number;

/**
 * Which grade drivers actually put in the tank.
 *
 * Counted rather than measured in litres: an electric charge is stored in the
 * same table but a litre reading means nothing for it, so counting fill-ups
 * keeps all three slices in the same unit. The slice colours are the colours
 * of the pumps themselves — 92 green, 95 red, electric blue — which is why
 * this chart is legible before anyone reads the legend.
 */
class FuelGradeSplitChart extends ChartWidget
{
    use AuthorizesWidgetWithApiPermission;

    protected static string $viewPermission = 'index-fill-up';

    protected static ?int $sort = 4;

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '280px';

    protected function getType(): string
    {
        return 'doughnut';
    }

    public function getHeading(): ?string
    {
        return 'Fill-ups by fuel grade';
    }

    public function getDescription(): ?string
    {
        return 'Every fill-up on record, '.Number::format(FillUp::query()->count()).' in total.';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $counts = FillUp::query()
            ->toBase()
            ->selectRaw('fuel_type, count(*) as aggregate')
            ->groupBy('fuel_type')
            ->pluck('aggregate', 'fuel_type')
            ->all();

        // Iterate the enum rather than the result set so the grades always
        // appear in the same order and the same colours, even in a window
        // where nobody bought 95.
        $labels = [];
        $values = [];
        $colors = [];

        foreach (array_keys(DashboardPalette::FUEL_GRADES) as $grade) {
            $labels[] = DashboardPalette::fuelGradeLabel($grade);
            $values[] = (int) ($counts[$grade] ?? 0);
            $colors[] = DashboardPalette::fuelGradeColor($grade);
        }

        // `fuel_type` is nullable — fill-ups recorded before the grade column
        // was added have none, and PHP stores that null key as ''. Only
        // surface the bucket if it is non-empty.
        $unrecorded = (int) ($counts[''] ?? 0);

        if ($unrecorded > 0) {
            $labels[] = DashboardPalette::fuelGradeLabel(null);
            $values[] = $unrecorded;
            $colors[] = DashboardPalette::SLATE;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Fill-ups',
                    'data' => $values,
                    'backgroundColor' => $colors,
                    'borderWidth' => 0,
                    'hoverOffset' => 6,
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
            'cutout' => '62%',
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'labels' => [
                        'usePointStyle' => true,
                        'boxWidth' => 8,
                        'boxHeight' => 8,
                        'padding' => 14,
                    ],
                ],
            ],
        ];
    }
}
