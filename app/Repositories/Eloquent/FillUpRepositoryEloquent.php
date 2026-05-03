<?php

namespace App\Repositories\Eloquent;

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
        $row = app($this->model())->newQuery()
            ->where('car_id', $carId)
            ->selectRaw('
                COUNT(*) as total_fill_ups,
                COALESCE(SUM(cost_egp), 0) as total_cost_egp,
                COALESCE(SUM(liters), 0) as total_liters,
                COALESCE(MAX(odometer), 0) as max_odometer,
                COALESCE(MIN(odometer), 0) as min_odometer
            ')
            ->first();

        $avg = $row->total_liters > 0
            ? round(($row->max_odometer - $row->min_odometer) / $row->total_liters, 2)
            : 0;

        return [
            'total_fill_ups'      => (int) $row->total_fill_ups,
            'total_cost_egp'      => number_format((float) $row->total_cost_egp, 2, '.', ''),
            'average_consumption' => number_format($avg, 2, '.', ''),
        ];
    }
}
