<?php

namespace App\Http\Controllers;

use App\Http\Requests\FuelPrice\StoreFuelPriceRequest;
use App\Http\Requests\FuelPrice\UpdateFuelPriceRequest;
use App\Http\Resources\FuelPriceResource;
use App\Models\FuelPrice;
use App\Repositories\Contracts\FuelPriceRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FuelPriceController extends BaseController
{
    public function __construct(
        protected FuelPriceRepository $fuelPriceRepository,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FuelPrice::class);

        $prices = $this->fuelPriceRepository->spatie()->paginate();

        return $this->paginated($prices, FuelPriceResource::class);
    }

    public function store(StoreFuelPriceRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $price = $this->fuelPriceRepository->create($request->validated());

            DB::commit();

            return $this->success(['data' => new FuelPriceResource($price)], 201, 'Fuel price created.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($e->getMessage(), 422);
        }
    }

    public function update(UpdateFuelPriceRequest $request, FuelPrice $fuelPrice): JsonResponse
    {
        try {
            DB::beginTransaction();

            $this->fuelPriceRepository->update($request->validated(), $fuelPrice->id);

            DB::commit();

            return $this->success(['data' => new FuelPriceResource($fuelPrice->refresh())]);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($e->getMessage(), 422);
        }
    }
}
