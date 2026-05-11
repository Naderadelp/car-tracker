<?php

namespace App\Repositories\Eloquent;

use App\Models\ServiceCenter;
use App\Repositories\Contracts\ServiceCenterRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ServiceCenterRepositoryEloquent extends EloquentRepository implements ServiceCenterRepository
{
    protected array $allowedIncludes      = ['brand'];
    protected array $allowedFilters       = ['name', 'address'];
    protected array $allowedFiltersExact  = ['brand_id'];
    protected array $allowedSorts         = ['name', 'created_at'];
    protected array $allowedDefaultSorts  = ['-id'];

    public function model(): string
    {
        return ServiceCenter::class;
    }

    public function nearby(int $brandId, float $lat, float $lng): LengthAwarePaginator
    {
        return app($this->model())->newQuery()
            ->where('brand_id', $brandId)
            ->selectRaw('
                service_centers.*,
                (6371 * 2 * ASIN(SQRT(
                    POW(SIN(RADIANS((? - lat) / 2)), 2)
                    + COS(RADIANS(?)) * COS(RADIANS(lat)) *
                      POW(SIN(RADIANS((? - lng) / 2)), 2)
                ))) AS distance_km
            ', [$lat, $lat, $lng])
            ->orderBy('distance_km')
            ->paginate();
    }
}
