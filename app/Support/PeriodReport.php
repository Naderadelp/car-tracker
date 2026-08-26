<?php

namespace App\Support;

use App\Models\CarLog;
use App\Models\Cost;
use App\Models\FillUp;
use App\Models\Trip;
use Illuminate\Support\Carbon;

/**
 * Gap A1 — the monthly report.
 *
 * The app compares this month with last across spend, distance, fill-up count,
 * average fuel price and cost per kilometre, plus a four-week breakdown. `GET
 * /home` gives a fixed 7-day window and `fill-ups.statistics` is all-time and
 * fuel-only, so the client had to page through the entire history on every
 * screen open — slower for every driver, every month.
 *
 * Aggregation is done in SQL where it is cheap and in PHP where the arithmetic
 * needs the rows anyway. Nothing here walks a paginated endpoint.
 */
class PeriodReport
{
    /**
     * @return array{
     *     from: string, to: string,
     *     spend: array{fuel: string, service: string, other: string, total: string},
     *     distance_km: float, fill_up_count: int,
     *     average_fuel_price_per_liter: string|null, cost_per_km: string|null
     * }
     */
    public static function forCar(int $carId, Carbon $from, Carbon $to): array
    {
        $fillUps = FillUp::query()
            ->where('car_id', $carId)
            ->whereBetween('fill_date', [$from->toDateString(), $to->toDateString()])
            ->get(['liters', 'cost_egp']);

        $fuelSpend  = (float) $fillUps->sum('cost_egp');
        $liters     = (float) $fillUps->sum('liters');

        $serviceSpend = (float) CarLog::query()
            ->where('car_id', $carId)
            ->whereBetween('performed_at', [$from->toDateString(), $to->toDateString()])
            ->sum('actual_cost');

        /*
         * Only manual ledger entries outside fuel and service are added here.
         * Decision D2 carries fuel records and maintenance entries into the
         * same table, so summing the whole ledger on top of the two figures
         * above would double-count every one of them.
         */
        $otherSpend = (float) Cost::query()
            ->where('car_id', $carId)
            ->whereNull('source_type')
            ->whereNotIn('category', ['fuel', 'service'])
            ->whereBetween('spent_at', [$from->toDateString(), $to->toDateString()])
            ->sum('amount_egp');

        $distance = (float) Trip::query()
            ->where('car_id', $carId)
            ->whereBetween('created_at', [$from, $to])
            ->sum('total_distance_km');

        $total = $fuelSpend + $serviceSpend + $otherSpend;

        return [
            'from'          => $from->toDateString(),
            'to'            => $to->toDateString(),
            'spend'         => [
                'fuel'    => self::money($fuelSpend),
                'service' => self::money($serviceSpend),
                'other'   => self::money($otherSpend),
                'total'   => self::money($total),
            ],
            'distance_km'   => round($distance, 2),
            'fill_up_count' => $fillUps->count(),
            // Null rather than zero: "no fuel bought" is not "fuel was free".
            'average_fuel_price_per_liter' => $liters > 0 ? self::money($fuelSpend / $liters) : null,
            'cost_per_km'                  => $distance > 0 ? self::money($total / $distance) : null,
        ];
    }

    /**
     * FR-032 — the four-week breakdown of fuel vs service vs distance.
     *
     * Buckets are computed in PHP rather than with a database date function
     * because the test suite runs on sqlite while development and production
     * run on PostgreSQL, and week-numbering functions differ across both.
     *
     * @return list<array{from: string, to: string, fuel: string, service: string, distance_km: float}>
     */
    public static function weeklyBuckets(int $carId, Carbon $from, Carbon $to): array
    {
        $buckets = [];
        $cursor  = $from->copy();

        while ($cursor->lessThanOrEqualTo($to)) {
            $bucketEnd = $cursor->copy()->addDays(6)->min($to);

            $fuel = (float) FillUp::query()
                ->where('car_id', $carId)
                ->whereBetween('fill_date', [$cursor->toDateString(), $bucketEnd->toDateString()])
                ->sum('cost_egp');

            $service = (float) CarLog::query()
                ->where('car_id', $carId)
                ->whereBetween('performed_at', [$cursor->toDateString(), $bucketEnd->toDateString()])
                ->sum('actual_cost');

            $distance = (float) Trip::query()
                ->where('car_id', $carId)
                ->whereBetween('created_at', [$cursor->copy()->startOfDay(), $bucketEnd->copy()->endOfDay()])
                ->sum('total_distance_km');

            $buckets[] = [
                'from'        => $cursor->toDateString(),
                'to'          => $bucketEnd->toDateString(),
                'fuel'        => self::money($fuel),
                'service'     => self::money($service),
                'distance_km' => round($distance, 2),
            ];

            $cursor = $bucketEnd->copy()->addDay();
        }

        return $buckets;
    }

    private static function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
