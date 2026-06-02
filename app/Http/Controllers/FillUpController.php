<?php

namespace App\Http\Controllers;

use App\Http\Requests\FillUp\QuickFillUpRequest;
use App\Http\Requests\FillUp\StoreFillUpRequest;
use App\Http\Resources\FillUpResource;
use App\Models\Car;
use App\Models\FillUp;
use App\Repositories\Contracts\FillUpRepository;
use App\Repositories\Contracts\FuelPriceRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FillUpController extends BaseController
{
    public function __construct(
        protected FillUpRepository $fillUpRepository,
        protected FuelPriceRepository $fuelPriceRepository,
    ) {}

    public function index(Request $request, Car $car): JsonResponse
    {
        $this->authorize('viewAny', [FillUp::class, $car]);

        $fillUps = $this->fillUpRepository
            ->where('car_id', $car->id)
            ->spatie()
            ->paginate();

        $statistics = $this->fillUpRepository->statistics($car->id);

        $response = $this->paginated($fillUps, FillUpResource::class);
        $data     = $response->getData(true);

        $data['statistics'] = $statistics;

        return response()->json($data);
    }

    public function store(StoreFillUpRequest $request, Car $car): JsonResponse
    {
        try {
            DB::beginTransaction();

            $fillUp = $this->fillUpRepository->create([
                'car_id'    => $car->id,
                'liters'    => $request->liters,
                'tank_percentage' => $request->tank_percentage,
                'odometer'  => $car->current_km,
                'cost_egp'  => $request->cost_egp,
                'fill_date' => $request->fill_date,
            ]);

            DB::commit();

            return $this->success(new FillUpResource($fillUp), 201, 'Fill-up recorded successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error($e->getMessage(), 422);
        }
    }

    public function destroy(Request $request, Car $car, FillUp $fillUp): JsonResponse
    {
        $this->authorize('delete', $fillUp);

        abort_if($fillUp->car_id !== $car->id, 404);

        $this->fillUpRepository->delete($fillUp->id);

        return $this->success([], 200, 'Fill-up deleted successfully.');
    }

    public function quick(QuickFillUpRequest $request, Car $car): JsonResponse
    {
        $liters = $request->liters;

        if ($liters === null) {
            $price  = $this->fuelPriceRepository->currentForType($request->fuel_type);
            $liters = $price ? round($request->amount_paid / $price->price_per_unit, 2) : null;
        }

        try {
            DB::beginTransaction();

            $fillUp = $this->fillUpRepository->create([
                'car_id'      => $car->id,
                'liters'      => $liters,
                'tank_percentage' => $request->tank_percentage,
                'odometer'    => $car->current_km,
                'cost_egp'    => $request->amount_paid,
                'fill_date'   => now()->toDateString(),
                'fuel_type'   => $request->fuel_type,
                'station_lat' => $request->station_lat,
                'station_lng' => $request->station_lng,
            ]);

            DB::commit();

            return $this->success(
                ['message' => 'Fill-up recorded successfully.', 'data' => new FillUpResource($fillUp)],
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($e->getMessage(), 422);
        }
    }
}
