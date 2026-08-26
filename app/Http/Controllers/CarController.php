<?php

namespace App\Http\Controllers;

use App\Events\OdometerAdvanced;
use App\Http\Requests\Car\UpdateCarRequest;
use App\Http\Resources\CarResource;
use App\Models\Car;
use App\Repositories\Contracts\CarRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Gap B1, plus F8 and F9.
 *
 * Before this feature a car was written once, at registration, and could never
 * be corrected — not its mileage, not its warranty, not its colour. Mileage is
 * the single most-written value in the mobile app and the input to fuel
 * consumption, the service schedule and warranty state, so every one of those
 * drifted permanently.
 */
class CarController extends BaseController
{
    public function __construct(
        protected CarRepository $carRepository,
    ) {}

    public function show(Request $request, Car $car): JsonResponse
    {
        $this->authorize('view', $car);

        $car->load(['brand', 'carModel']);

        return $this->success(new CarResource($car));
    }

    /**
     * Authorization lives in UpdateCarRequest, not here — constitution
     * Principle II puts store/update authorization in the Form Request.
     */
    public function update(UpdateCarRequest $request, Car $car): JsonResponse
    {
        $validated = $request->validated();
        $previousKm = (int) $car->current_km;

        try {
            DB::beginTransaction();

            $car = $this->carRepository->update($validated, $car->id);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error($e->getMessage(), 422);
        }

        /*
         * Only fire when the odometer actually moved forward. The listeners on
         * this event check for due services and reminders; a downward
         * correction (decision D3) has not passed any threshold, and firing
         * there would push a "service due" notification for distance the car
         * never travelled.
         */
        $newKm = (int) $car->current_km;

        if (array_key_exists('current_km', $validated) && $newKm > $previousKm) {
            event(new OdometerAdvanced($car, $newKm));
        }

        $car->load(['brand', 'carModel']);

        return $this->success(new CarResource($car), 200, 'Car updated successfully.');
    }
}
