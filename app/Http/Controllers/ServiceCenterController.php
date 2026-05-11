<?php

namespace App\Http\Controllers;

use App\Http\Requests\ServiceCenter\NearbyServiceCentersRequest;
use App\Http\Requests\ServiceCenter\StoreServiceCenterRequest;
use App\Http\Requests\ServiceCenter\UpdateServiceCenterRequest;
use App\Http\Resources\ServiceCenterResource;
use App\Models\Brand;
use App\Models\ServiceCenter;
use App\Repositories\Contracts\ServiceCenterRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceCenterController extends BaseController
{
    public function __construct(
        protected ServiceCenterRepository $serviceCenterRepository,
    ) {}

    public function index(NearbyServiceCentersRequest $request, Brand $brand): JsonResponse
    {
        $centers = $this->serviceCenterRepository->nearby(
            $brand->id,
            (float) $request->lat,
            (float) $request->lng,
        );

        return $this->paginated($centers, ServiceCenterResource::class);
    }

    public function show(Request $request, Brand $brand, ServiceCenter $serviceCenter): JsonResponse
    {
        $this->authorize('view', $serviceCenter);

        abort_if($serviceCenter->brand_id !== $brand->id, 404);

        $serviceCenter->load('brand');

        return $this->success(new ServiceCenterResource($serviceCenter));
    }

    public function store(StoreServiceCenterRequest $request, Brand $brand): JsonResponse
    {
        try {
            DB::beginTransaction();

            $serviceCenter = $this->serviceCenterRepository->create([
                ...$request->validated(),
                'brand_id' => $brand->id,
            ]);

            DB::commit();

            return $this->success(
                new ServiceCenterResource($serviceCenter),
                201,
                'Service center created.',
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error($e->getMessage(), 422);
        }
    }

    public function update(UpdateServiceCenterRequest $request, Brand $brand, ServiceCenter $serviceCenter): JsonResponse
    {
        abort_if($serviceCenter->brand_id !== $brand->id, 404);

        try {
            DB::beginTransaction();

            $updated = $this->serviceCenterRepository->update($request->validated(), $serviceCenter->id);

            DB::commit();

            return $this->success(new ServiceCenterResource($updated));
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error($e->getMessage(), 422);
        }
    }

    public function destroy(Request $request, Brand $brand, ServiceCenter $serviceCenter): JsonResponse
    {
        $this->authorize('delete', $serviceCenter);

        abort_if($serviceCenter->brand_id !== $brand->id, 404);

        $this->serviceCenterRepository->delete($serviceCenter->id);

        return $this->success([], 200, 'Service center deleted.');
    }
}
