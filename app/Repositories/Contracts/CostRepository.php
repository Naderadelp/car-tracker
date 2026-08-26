<?php

namespace App\Repositories\Contracts;

interface CostRepository extends RepositoryInterface
{
    /**
     * Total spend and the share of each category, for the Costs tab header.
     *
     * @return array{total_egp: string, by_category: array<string, string>}
     */
    public function totalsForCar(int $carId): array;
}
