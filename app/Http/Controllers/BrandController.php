<?php

namespace App\Http\Controllers;

use App\Http\Requests\Brand\StoreBrandRequest;
use App\Http\Requests\Brand\UpdateBrandRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;

class BrandController extends BaseController
{
    public function index(): JsonResponse
    {
        $brands = Brand::orderBy('name')->get();

        return $this->success(BrandResource::collection($brands));
    }

    public function store(StoreBrandRequest $request): JsonResponse
    {
        $brand = Brand::create($request->validated());

        return $this->success(new BrandResource($brand), 201, 'Brand created successfully.');
    }

    public function show(Brand $brand): JsonResponse
    {
        return $this->success(new BrandResource($brand));
    }

    public function update(UpdateBrandRequest $request, Brand $brand): JsonResponse
    {
        $brand->update($request->validated());

        return $this->success(new BrandResource($brand));
    }

    public function destroy(Brand $brand): JsonResponse
    {
        $this->authorize('delete', $brand);

        $brand->delete();

        return $this->success([], 200, 'Brand deleted successfully.');
    }
}
