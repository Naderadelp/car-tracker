<?php

namespace App\Http\Controllers;

use App\Http\Requests\ParkingRecord\StoreParkingRecordRequest;
use App\Http\Requests\ParkingRecord\UpdateParkingRecordRequest;
use App\Http\Resources\ParkingRecordResource;
use App\Models\Car;
use App\Models\ParkingRecord;
use App\Repositories\Contracts\ParkingRecordRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ParkingRecordController extends BaseController
{
    public function __construct(
        protected ParkingRecordRepository $parkingRepository,
    ) {}

    public function index(Request $request, Car $car): JsonResponse
    {
        $this->authorize('viewAny', [ParkingRecord::class, $car]);

        $records = $this->parkingRepository
            ->where('car_id', $car->id)
            ->spatie()
            ->paginate();

        return $this->paginated($records, ParkingRecordResource::class);
    }

    public function current(Request $request, Car $car): JsonResponse
    {
        $this->authorize('viewAny', [ParkingRecord::class, $car]);

        $record = $this->parkingRepository->current($car->id);

        abort_if(is_null($record), 404, 'No parking history found.');

        return $this->success(new ParkingRecordResource($record), 200);
    }

    public function store(StoreParkingRecordRequest $request, Car $car): JsonResponse
    {
        try {
            DB::beginTransaction();

            $record = $this->parkingRepository->create([
                'car_id'      => $car->id,
                'name'        => $request->name,
                'description' => $request->description,
                'address'     => $request->address,
                'latitude'    => $request->latitude,
                'longitude'   => $request->longitude,
                'parked_at'   => $request->parked_at,
            ]);

            DB::commit();

            return $this->success(
                new ParkingRecordResource($record),
                201,
                'Parking location recorded successfully.',
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Gap F7 — the driver edits the current location in place. Authorization
     * lives in UpdateParkingRecordRequest per Principle II.
     */
    public function update(UpdateParkingRecordRequest $request, Car $car, ParkingRecord $parkingRecord): JsonResponse
    {
        abort_if($parkingRecord->car_id !== $car->id, 404);

        try {
            DB::beginTransaction();

            $record = $this->parkingRepository->update($request->validated(), $parkingRecord->id);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error($e->getMessage(), 422);
        }

        return $this->success(
            new ParkingRecordResource($record),
            200,
            'Parking record updated successfully.',
        );
    }

    public function destroy(Request $request, Car $car, ParkingRecord $parkingRecord): JsonResponse
    {
        $this->authorize('delete', $parkingRecord);

        abort_if($parkingRecord->car_id !== $car->id, 404);

        $this->parkingRepository->delete($parkingRecord->id);

        return $this->success([], 200, 'Parking record deleted successfully.');
    }
}
