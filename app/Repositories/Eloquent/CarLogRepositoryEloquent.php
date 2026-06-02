<?php

namespace App\Repositories\Eloquent;

use App\Models\CarLog;
use App\Repositories\Contracts\CarLogRepository;

class CarLogRepositoryEloquent extends EloquentRepository implements CarLogRepository
{
    protected array $allowedIncludes     = ['car', 'service'];
    protected array $allowedFiltersExact = ['car_id', 'service_id'];
    protected array $allowedSorts        = ['performed_at', 'actual_cost', 'odometer_at_service'];
    protected array $allowedDefaultSorts = ['-performed_at'];

    public function model(): string
    {
        return CarLog::class;
    }

    protected function scopeToUser(): void
    {
        if (auth()->check() && !auth()->user()->isAdmin()) {
            $this->model = $this->model->whereHas('car', function ($q) {
                $q->where('user_id', auth()->id());
            });
        }
    }
}
