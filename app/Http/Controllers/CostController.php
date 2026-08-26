<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cost\StoreCostRequest;
use App\Http\Requests\Cost\UpdateCostRequest;
use App\Http\Resources\CostResource;
use App\Models\Car;
use App\Models\Cost;
use App\Repositories\Contracts\CostRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Gap B4 — the Costs tab.
 *
 * The ledger is unified (decision D2): manual entries sit alongside entries
 * carried across from fuel records and maintenance entries, so the tab shows
 * everything the car has cost without anyone typing it twice.
 */
class CostController extends BaseController
{
    public function __construct(
        protected CostRepository $costRepository,
    ) {}

    public function index(Request $request, Car $car): JsonResponse
    {
        $this->authorize('viewAny', [Cost::class, $car]);

        $costs = $this->costRepository
            ->where('car_id', $car->id)
            ->spatie()
            ->paginate();

        $response = $this->paginated($costs, CostResource::class);
        $data     = $response->getData(true);

        $data['totals'] = $this->costRepository->totalsForCar($car->id);

        return response()->json($data);
    }

    public function store(StoreCostRequest $request, Car $car): JsonResponse
    {
        try {
            DB::beginTransaction();

            $cost = $this->costRepository->create([
                ...$request->validated(),
                'car_id'  => $car->id,
                'user_id' => auth()->id(),
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error($e->getMessage(), 422);
        }

        return $this->success(new CostResource($cost), 201, 'Cost recorded successfully.');
    }

    public function show(Request $request, Car $car, Cost $cost): JsonResponse
    {
        $this->authorize('view', $cost);

        abort_if($cost->car_id !== $car->id, 404);

        return $this->success(new CostResource($cost));
    }

    /**
     * FR-045. Correcting the amount on a carried-across row makes the driver's
     * figure the authority: `amount_overridden` flips, and the observers stop
     * writing to this row so a later edit of the source cannot silently undo
     * the correction.
     */
    public function update(UpdateCostRequest $request, Car $car, Cost $cost): JsonResponse
    {
        abort_if($cost->car_id !== $car->id, 404);

        $attributes = $request->validated();

        if ($cost->isCarriedAcross() && array_key_exists('amount_egp', $attributes)) {
            $attributes['amount_overridden'] = true;
        }

        try {
            DB::beginTransaction();

            $cost = $this->costRepository->update($attributes, $cost->id);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error($e->getMessage(), 422);
        }

        return $this->success(new CostResource($cost), 200, 'Cost updated successfully.');
    }

    public function destroy(Request $request, Car $car, Cost $cost): JsonResponse
    {
        $this->authorize('delete', $cost);

        abort_if($cost->car_id !== $car->id, 404);

        $this->costRepository->delete($cost->id);

        return $this->success([], 200, 'Cost deleted successfully.');
    }
}
