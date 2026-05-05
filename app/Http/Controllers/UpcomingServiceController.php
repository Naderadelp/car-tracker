<?php

namespace App\Http\Controllers;

use App\Http\Resources\ServiceResource;
use App\Models\Car;
use App\Models\Service;
use App\Repositories\Contracts\ServiceRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpcomingServiceController extends BaseController
{
    public function __construct(
        protected ServiceRepository $serviceRepository,
    ) {}

    public function index(Request $request, Car $car): JsonResponse
    {
        $this->authorize('viewAny', [Service::class, $car]);

        $services = $this->serviceRepository->upcomingForCar($car);

        return $this->success(ServiceResource::collection($services));
    }
}
