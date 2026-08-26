<?php

namespace App\Repositories\Eloquent;

use App\Models\Cost;
use App\Repositories\Contracts\CostRepository;

class CostRepositoryEloquent extends EloquentRepository implements CostRepository
{
    protected array $allowedFilters      = ['title'];
    protected array $allowedFiltersExact = ['category', 'car_id', 'source_type'];
    protected array $allowedSorts        = ['spent_at', 'amount_egp', 'category', 'created_at'];

    /**
     * `-spent_at` rather than the usual `-id`. The constitution permits
     * overriding the default when the domain has a stronger natural order, and
     * a ledger is read by when the money was spent, not by insertion order —
     * carried-across rows arrive whenever their source record is filed, which
     * has nothing to do with the date on them.
     */
    protected array $allowedDefaultSorts = ['-spent_at'];

    public function model(): string
    {
        return Cost::class;
    }

    protected function scopeToUser(): void
    {
        if (auth()->check() && !auth()->user()->isAdmin()) {
            $this->model = $this->model->where('user_id', auth()->id());
        }
    }

    public function totalsForCar(int $carId): array
    {
        $rows = app($this->model())->newQuery()
            ->where('car_id', $carId)
            ->selectRaw('category, SUM(amount_egp) AS total')
            ->groupBy('category')
            ->pluck('total', 'category');

        $byCategory = [];
        $total = '0.00';

        foreach ($rows as $category => $categoryTotal) {
            $byCategory[$category] = number_format((float) $categoryTotal, 2, '.', '');
            $total = number_format((float) $total + (float) $categoryTotal, 2, '.', '');
        }

        return [
            'total_egp'   => $total,
            'by_category' => $byCategory,
        ];
    }
}
