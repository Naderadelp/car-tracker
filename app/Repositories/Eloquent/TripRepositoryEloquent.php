<?php

namespace App\Repositories\Eloquent;

use App\Models\Trip;
use App\Repositories\Contracts\TripRepository;

class TripRepositoryEloquent extends EloquentRepository implements TripRepository
{
    protected array $allowedFiltersExact = ['car_id'];
    protected array $allowedSorts        = ['start_time', 'total_distance_km'];
    protected array $allowedDefaultSorts = ['-start_time'];

    public function model(): string
    {
        return Trip::class;
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
