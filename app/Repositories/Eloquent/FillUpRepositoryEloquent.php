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
}
