<?php

namespace App\Http\Controllers;

use App\Http\Resources\HomeResource;
use App\Repositories\Contracts\IssueRepository;
use App\Repositories\Contracts\ServiceRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeController extends BaseController
{
    public function __construct(
        protected ServiceRepository $serviceRepository,
        protected IssueRepository $issueRepository,
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

        // FR-021 — serious unresolved faults are promoted onto the attention
        // list alongside overdue services, which is what the app's
        // notifications screen renders.
        $attention = $this->issueRepository->needingAttentionForCar($car->id);

        return $this->success(HomeResource::make([
            'car'      => $car,
            'trips'    => $trips,
            'upcoming' => $upcoming,
            'issues'   => $attention,
        ]));
    }
}
