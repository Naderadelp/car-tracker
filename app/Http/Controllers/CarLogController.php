<?php

namespace App\Http\Controllers;

use App\Http\Requests\CarLog\StoreCarLogRequest;
use App\Http\Requests\CarLog\UpdateCarLogRequest;
use App\Http\Resources\CarLogResource;
use App\Events\CarLogCreated;
use App\Models\Car;
use App\Models\CarLog;
use App\Repositories\Contracts\CarLogRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CarLogController extends BaseController
{
    public function __construct(
        protected CarLogRepository $carLogRepository,
    ) {}

    public function index(Request $request, Car $car): JsonResponse
    {
        $this->authorize('viewAny', [CarLog::class, $car]);

        $logs = $this->carLogRepository
            ->where('car_id', $car->id)
            ->spatie()
            ->paginate($this->perPage());

        return $this->paginated($logs, CarLogResource::class);
    }

    public function store(StoreCarLogRequest $request, Car $car): JsonResponse
    {
        try {
            DB::beginTransaction();

            $log = $this->carLogRepository->create([
                ...$request->validated(),
                'car_id' => $car->id,
            ]);

            event(new CarLogCreated($log));

            DB::commit();

            return $this->success(new CarLogResource($log), 201, 'Service log created.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($e->getMessage(), 422);
        }
    }

    public function show(Request $request, Car $car, CarLog $log): JsonResponse
    {
        $this->authorize('view', $log);
        abort_if($log->car_id !== $car->id, 404);

        return $this->success(new CarLogResource($log));
    }

    public function update(UpdateCarLogRequest $request, Car $car, CarLog $log): JsonResponse
    {
        abort_if($log->car_id !== $car->id, 404);
        $this->authorize('update', $log);

        try {
            DB::beginTransaction();

            $this->carLogRepository->update($request->validated(), $log->id);

            DB::commit();

            return $this->success(new CarLogResource($log->refresh()));
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($e->getMessage(), 422);
        }
    }

    public function destroy(Request $request, Car $car, CarLog $log): JsonResponse
    {
        abort_if($log->car_id !== $car->id, 404);
        $this->authorize('delete', $log);

        $this->carLogRepository->delete($log->id);

        return $this->success([], 200, 'Service log deleted.');
    }
}
