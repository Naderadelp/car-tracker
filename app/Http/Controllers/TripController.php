<?php

namespace App\Http\Controllers;

use App\Http\Requests\Trip\StoreTripRequest;
use App\Http\Resources\TripResource;
use App\Models\Car;
use App\Models\Trip;
use App\Repositories\Contracts\TripRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TripController extends BaseController
{
    public function __construct(
        protected TripRepository $tripRepository,
    ) {}

    public function index(Request $request, Car $car): JsonResponse
    {
        $this->authorize('viewAny', [Trip::class, $car]);

        $trips = $this->tripRepository
            ->where('car_id', $car->id)
            ->spatie()
            ->paginate();

        return $this->paginated($trips, TripResource::class);
    }

    public function store(StoreTripRequest $request, Car $car): JsonResponse
    {
        try {
            DB::beginTransaction();

            $coordinates      = $request->coordinates;
            $totalDistanceKm  = 0.0;

            for ($i = 1; $i < count($coordinates); $i++) {
                $totalDistanceKm += $this->haversineKm(
                    (float) $coordinates[$i - 1]['lat'],
                    (float) $coordinates[$i - 1]['lng'],
                    (float) $coordinates[$i]['lat'],
                    (float) $coordinates[$i]['lng'],
                );
            }

            $first = $coordinates[0];
            $last  = $coordinates[array_key_last($coordinates)];

            $trip = $this->tripRepository->create([
                'car_id'            => $car->id,
                'start_lat'         => $first['lat'],
                'start_lng'         => $first['lng'],
                'end_lat'           => $last['lat'],
                'end_lng'           => $last['lng'],
                'total_distance_km' => round($totalDistanceKm, 2),
            ]);

            DB::commit();

            return $this->success(new TripResource($trip), 201, 'Trip recorded successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error($e->getMessage(), 422);
        }
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r    = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) ** 2
              + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
