<?php

namespace App\Http\Controllers;

use App\Http\Requests\Reminder\StoreReminderRequest;
use App\Http\Requests\Reminder\UpdateReminderRequest;
use App\Http\Resources\ReminderResource;
use App\Models\Car;
use App\Models\Reminder;
use App\Repositories\Contracts\ReminderRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReminderController extends BaseController
{
    public function __construct(
        protected ReminderRepository $reminderRepository,
    ) {}

    public function index(Request $request, Car $car): JsonResponse
    {
        $this->authorize('viewAny', [Reminder::class, $car]);

        $reminders = $this->reminderRepository
            ->where('car_id', $car->id)
            ->spatie()
            ->paginate();

        return $this->paginated($reminders, ReminderResource::class);
    }

    public function store(StoreReminderRequest $request, Car $car): JsonResponse
    {
        try {
            DB::beginTransaction();

            $reminder = $this->reminderRepository->create([
                ...$request->validated(),
                'car_id' => $car->id,
            ]);

            DB::commit();

            return $this->success(new ReminderResource($reminder), 201, 'Reminder created.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($e->getMessage(), 422);
        }
    }

    public function show(Request $request, Car $car, Reminder $reminder): JsonResponse
    {
        $this->authorize('view', $reminder);
        abort_if($reminder->car_id !== $car->id, 404);

        return $this->success(new ReminderResource($reminder));
    }

    public function update(UpdateReminderRequest $request, Car $car, Reminder $reminder): JsonResponse
    {
        abort_if($reminder->car_id !== $car->id, 404);
        $this->authorize('update', $reminder);

        try {
            DB::beginTransaction();

            $this->reminderRepository->update($request->validated(), $reminder->id);

            DB::commit();

            return $this->success(new ReminderResource($reminder->refresh()));
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($e->getMessage(), 422);
        }
    }

    public function destroy(Request $request, Car $car, Reminder $reminder): JsonResponse
    {
        abort_if($reminder->car_id !== $car->id, 404);
        $this->authorize('delete', $reminder);

        $this->reminderRepository->delete($reminder->id);

        return $this->success([], 200, 'Reminder deleted.');
    }
}
