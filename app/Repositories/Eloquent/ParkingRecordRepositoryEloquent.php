<?php

namespace App\Repositories\Eloquent;

use App\Models\ParkingRecord;
use App\Repositories\Contracts\ParkingRecordRepository;

class ParkingRecordRepositoryEloquent extends EloquentRepository implements ParkingRecordRepository
{
    protected array $allowedIncludes      = ['car'];
    protected array $allowedFilters       = ['name'];
    protected array $allowedFiltersExact  = ['car_id'];
    protected array $allowedSorts         = ['parked_at', 'created_at'];
    protected array $allowedDefaultSorts  = ['-parked_at'];

    public function model(): string
    {
        return ParkingRecord::class;
    }

    protected function scopeToUser(): void
    {
        if (auth()->check() && !auth()->user()->isAdmin()) {
            $this->model = $this->model->whereHas('car', function ($q) {
                $q->where('user_id', auth()->id());
            });
        }
    }

    public function current(int $carId): ?ParkingRecord
    {
        return app($this->model())->newQuery()
            ->where('car_id', $carId)
            ->orderByDesc('parked_at')
            ->first();
    }
}
