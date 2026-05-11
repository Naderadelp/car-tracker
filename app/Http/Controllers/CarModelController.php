<?php

namespace App\Http\Controllers;

use App\Http\Requests\CarModel\StoreCarModelRequest;
use App\Http\Requests\CarModel\UpdateCarModelRequest;
use App\Http\Resources\CarModelResource;
use App\Models\Brand;
use App\Models\CarModel;
use Illuminate\Http\JsonResponse;

class CarModelController extends BaseController
{
    public function index(Brand $brand): JsonResponse
    {
        $models = $brand->carModels()->orderBy('name')->paginate();

        return $this->paginated($models, CarModelResource::class);
    }

    public function store(StoreCarModelRequest $request, Brand $brand): JsonResponse
    {
        $carModel = $brand->carModels()->create($request->only(['name', 'model_year']));

        return $this->success(new CarModelResource($carModel), 201, 'Car model created successfully.');
    }

    public function update(UpdateCarModelRequest $request, Brand $brand, CarModel $carModel): JsonResponse
    {
        abort_if($carModel->brand_id !== $brand->id, 404);

        $carModel->update($request->only(['name', 'model_year']));

        return $this->success(new CarModelResource($carModel));
    }

    public function destroy(Brand $brand, CarModel $carModel): JsonResponse
    {
        $this->authorize('delete', $carModel);

        abort_if($carModel->brand_id !== $brand->id, 404);

        $carModel->delete();

        return $this->success([], 200, 'Car model deleted successfully.');
    }
}
