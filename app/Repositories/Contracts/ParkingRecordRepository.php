<?php

namespace App\Repositories\Contracts;

use App\Models\ParkingRecord;

interface ParkingRecordRepository extends RepositoryInterface
{
    public function current(int $carId): ?ParkingRecord;
}
