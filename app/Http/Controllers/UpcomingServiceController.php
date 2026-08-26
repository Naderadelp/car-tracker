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

        /*
         * Gap F2 — opt-in so the existing route keeps its current behaviour for
         * any client already calling it, while the Services grid can ask for
         * the whole schedule in one request.
         */
        $includePast = $request->boolean('include_past');

        $services = $this->serviceRepository->upcomingForCar($car, $includePast);

        return $this->success(ServiceResource::collection($services));
    }
}
