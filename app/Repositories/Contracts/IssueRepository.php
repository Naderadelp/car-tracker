<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface IssueRepository extends RepositoryInterface
{
    /**
     * Unresolved high-severity faults for a car — FR-021. These are promoted
     * onto the notifications screen alongside overdue services.
     */
    public function needingAttentionForCar(int $carId): Collection;
}
