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
    public function index(Brand $brand, \Illuminate\Http\Request $request): JsonResponse
    {
        $name = $request->query('name');

        if (is_string($name) && $name !== '') {
            $models = $brand->carModels()
                ->where('name', $name)
                ->whereNotNull('model_year')
                ->orderByDesc('model_year')
                ->get();

            return $this->success(CarModelResource::collection($models));
        }

        $models = $brand->carModels()->orderBy('name')->paginate($this->perPage());

        return $this->paginated($models, CarModelResource::class);
    }

    public function names(Brand $brand): JsonResponse
    {
        $names = $brand->carModels()
            ->whereNotNull('model_year')
            ->orderBy('name')
            ->distinct()
            ->pluck('name')
            ->map(fn (string $name) => ['name' => $name])
            ->values();

        return $this->success($names);
    }

    public function years(Brand $brand, \Illuminate\Http\Request $request): JsonResponse
    {
        $name = $request->query('name');

        if (!is_string($name) || $name === '') {
            return $this->error('The name query parameter is required.', 422);
        }

        $years = $brand->carModels()
            ->where('name', $name)
            ->whereNotNull('model_year')
            ->orderByDesc('model_year')
            ->distinct()
            ->pluck('model_year')
            ->map(fn (int $year) => ['year' => $year])
            ->values();

        return $this->success($years);
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
