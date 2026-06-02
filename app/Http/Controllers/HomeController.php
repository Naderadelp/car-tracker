<?php

namespace App\Http\Controllers;

use App\Http\Resources\HomeResource;
use App\Repositories\Contracts\ServiceRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeController extends BaseController
{
    public function __construct(
        protected ServiceRepository $serviceRepository,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $car = $request->user()
            ->cars()
            ->with(['brand', 'carModel'])
            ->latest()
            ->firstOrFail();

        $trips = $car->trips()
            ->where('created_at', '>=', now()->subDays(7))
            ->latest()
            ->get();

        $upcoming = $this->serviceRepository->upcomingForCar($car)->take(3);

        return $this->success(HomeResource::make([
            'car'      => $car,
            'trips'    => $trips,
            'upcoming' => $upcoming,
        ]));
    }
}
