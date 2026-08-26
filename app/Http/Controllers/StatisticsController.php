<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\FillUp;
use App\Support\PeriodReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Gap A1 — the monthly report, in one request.
 */
class StatisticsController extends BaseController
{
    public function __invoke(Request $request, Car $car): JsonResponse
    {
        $this->authorize('viewAny', [FillUp::class, $car]);

        $period = $request->query('period', 'month');

        [$from, $to] = $this->resolvePeriod($period);

        $current = PeriodReport::forCar($car->id, $from, $to);

        $payload = [
            'period'  => $period,
            'current' => $current,
            'weekly'  => PeriodReport::weeklyBuckets($car->id, $from, $to),
        ];

        if ($request->query('compare') === 'previous') {
            [$prevFrom, $prevTo] = $this->previousPeriod($period, $from);

            /*
             * A driver in their first month has no previous period to compare
             * against — the car did not exist yet, so returning a row of zeroes
             * would read as "you spent nothing last month" rather than "there
             * was no last month".
             *
             * The key is still present and explicitly null rather than absent,
             * so the client has one shape to decode.
             */
            $carExistedBefore = $car->created_at !== null
                && $car->created_at->lessThanOrEqualTo($prevTo);

            $payload['previous'] = $carExistedBefore
                ? PeriodReport::forCar($car->id, $prevFrom, $prevTo)
                : null;
        }

        return $this->success($payload);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolvePeriod(string $period): array
    {
        return match ($period) {
            'week'  => [now()->startOfWeek(), now()->endOfWeek()],
            'year'  => [now()->startOfYear(), now()->endOfYear()],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function previousPeriod(string $period, Carbon $from): array
    {
        return match ($period) {
            'week'  => [$from->copy()->subWeek()->startOfWeek(), $from->copy()->subWeek()->endOfWeek()],
            'year'  => [$from->copy()->subYear()->startOfYear(), $from->copy()->subYear()->endOfYear()],
            default => [$from->copy()->subMonth()->startOfMonth(), $from->copy()->subMonth()->endOfMonth()],
        };
    }
}
