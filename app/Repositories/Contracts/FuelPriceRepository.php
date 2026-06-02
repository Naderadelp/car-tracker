<?php

namespace App\Repositories\Contracts;

use App\Models\FuelPrice;

interface FuelPriceRepository extends RepositoryInterface
{
    public function currentForType(string $type): ?FuelPrice;
}
