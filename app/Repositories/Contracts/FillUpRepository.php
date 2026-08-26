<?php

namespace App\Repositories\Contracts;

interface FillUpRepository extends RepositoryInterface
{
    public function statistics(int $carId): array;

    /**
     * Gap A2 — km/L per fill-up, so the fuel chart needs one request instead of
     * the whole history.
     *
     * @return array<int, float|null> fill-up id => km per litre, null where undefined
     */
    public function efficiencySeries(int $carId): array;
}
