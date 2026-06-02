<?php

namespace App\Http\Controllers;

use App\Events\GasStationCheckIn;
use App\Models\Car;
use App\Models\FillUp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GasStationCheckInController extends BaseController
{
    public function __invoke(Request $request, Car $car): JsonResponse
    {
        $this->authorize('create', [FillUp::class, $car]);

        event(new GasStationCheckIn($car));

        return $this->success([], 200, 'Notification sent.');
    }
}
