<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface ServiceCenterRepository extends RepositoryInterface
{
    public function nearby(int $brandId, float $lat, float $lng): Collection;
}
