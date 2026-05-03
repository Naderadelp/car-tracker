<?php

namespace App\Repositories\Contracts;

interface FillUpRepository extends RepositoryInterface
{
    public function statistics(int $carId): array;
}
