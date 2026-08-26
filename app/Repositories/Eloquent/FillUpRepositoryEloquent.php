<?php

namespace App\Repositories\Eloquent;

use App\Models\Car;
use App\Models\FillUp;
use App\Repositories\Contracts\FillUpRepository;

class FillUpRepositoryEloquent extends EloquentRepository implements FillUpRepository
{
    protected array $allowedFiltersExact  = ['car_id'];
    protected array $allowedSorts         = ['fill_date', 'cost_egp', 'liters', 'odometer'];
    protected array $allowedDefaultSorts  = ['-fill_date'];

    public function model(): string
    {
        return FillUp::class;
    }

    protected function scopeToUser(): void
    {
        if (auth()->check() && !auth()->user()->isAdmin()) {
            $this->model = $this->model->whereHas('car', function ($q) {
                $q->where('user_id', auth()->id());
            });
        }
    }

    public function statistics(int $carId): array
    {
        $tankSize = (float) (app(Car::class)->newQuery()->whereKey($carId)->value('tank_size') ?? 0);

        $fills = app($this->model())->newQuery()
            ->where('car_id', $carId)
            ->orderBy('odometer')
            ->get(['odometer', 'liters', 'tank_percentage', 'cost_egp']);

        $totalFillUps = $fills->count();
        $totalCost    = (float) $fills->sum('cost_egp');
        $distance     = $totalFillUps > 0
            ? (int) $fills->max('odometer') - (int) $fills->min('odometer')
            : 0;

        // Consumption between consecutive fill-ups (ordered by odometer):
        //   consumed = liters_added_next + (level_prev - level_next) * tank_size
        // The tank-level correction applies only when both percentages and the
        // tank size are known; otherwise it is omitted. The first fill-up's
        // liters are intentionally excluded (no distance precedes it).
        $consumed = 0.0;
        for ($i = 1; $i < $totalFillUps; $i++) {
            $prev = $fills[$i - 1];
            $curr = $fills[$i];

            $consumed += (float) $curr->liters;

            if ($tankSize > 0 && $prev->tank_percentage !== null && $curr->tank_percentage !== null) {
                $consumed += ((float) $prev->tank_percentage - (float) $curr->tank_percentage) / 100 * $tankSize;
            }
        }

        $avg = ($totalFillUps >= 2 && $consumed > 0)
            ? round($distance / $consumed, 2)
            : 0;

        return [
            'total_fill_ups'      => $totalFillUps,
            'total_cost_egp'      => number_format($totalCost, 2, '.', ''),
            'average_consumption' => number_format($avg, 2, '.', ''),
            'total_distance_km'   => $distance,
        ];
    }

    /**
     * Gap A2 — the fuel chart plots km/L *per fill-up*, with best, worst and
     * average called out. Only one all-time average existed, so the client had
     * to walk the entire history to draw the chart.
     *
     * Reuses the same arithmetic as statistics() above, including the
     * tank-percentage correction, applied to a single record instead of the
     * whole series.
     *
     * The figure is **computed, never stored**: it depends on the preceding
     * fill-up, so a stored value would go stale the moment a record was
     * inserted between two others, or an odometer was corrected (decision D3).
     *
     * @return array<int, float|null> fill-up id => km per litre, null where undefined
     */
    public function efficiencySeries(int $carId): array
    {
        $tankSize = (float) (app(Car::class)->newQuery()->whereKey($carId)->value('tank_size') ?? 0);

        $fills = app($this->model())->newQuery()
            ->where('car_id', $carId)
            ->orderBy('odometer')
            ->get(['id', 'odometer', 'liters', 'tank_percentage']);

        // The first fill-up has no distance preceding it, so its efficiency is
        // undefined rather than zero — the chart must skip it, not plot it at
        // the origin.
        $series = $fills->isEmpty() ? [] : [$fills[0]->id => null];

        for ($i = 1; $i < $fills->count(); $i++) {
            $prev = $fills[$i - 1];
            $curr = $fills[$i];

            $distance = (int) $curr->odometer - (int) $prev->odometer;
            $consumed = (float) $curr->liters;

            if ($tankSize > 0 && $prev->tank_percentage !== null && $curr->tank_percentage !== null) {
                $consumed += ((float) $prev->tank_percentage - (float) $curr->tank_percentage) / 100 * $tankSize;
            }

            // A non-positive distance means the odometer was corrected
            // downwards between the two records; there is no meaningful figure
            // to report, and a negative one would be worse than none.
            $series[$curr->id] = ($distance > 0 && $consumed > 0)
                ? round($distance / $consumed, 2)
                : null;
        }

        return $series;
    }
}
