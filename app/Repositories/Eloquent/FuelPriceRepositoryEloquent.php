<?php

namespace App\Repositories\Eloquent;

use App\Models\FuelPrice;
use App\Repositories\Contracts\FuelPriceRepository;

class FuelPriceRepositoryEloquent extends EloquentRepository implements FuelPriceRepository
{
    protected array $allowedFiltersExact = ['type'];
    protected array $allowedSorts        = ['effective_from', 'type'];
    protected array $allowedDefaultSorts = ['-effective_from'];

    public function model(): string
    {
        return FuelPrice::class;
    }

    public function currentForType(string $type): ?FuelPrice
    {
        $result = app($this->model())->newQuery()
            ->where('type', $type)
            ->orderByDesc('effective_from')
            ->first();
        $this->resetModel();
        return $result;
    }
}
