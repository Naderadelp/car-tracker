<?php

namespace App\Http\Controllers;

use App\Models\Car;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gap A3 — resale valuation, under decision D1.
 *
 * The gap report refused to answer this one: "decide first — this is a
 * market-data feature, not a logbook one". The decision taken was to derive a
 * figure from what the driver paid, the car's age and its mileage, with **no
 * external market-data provider**.
 *
 * That has a consequence the response must be honest about: this is an
 * estimate, not an appraisal, and the comparable listings the app currently
 * shows cannot be produced at all. Every response carries `basis` and `note`
 * so the client cannot present it as a market valuation by accident.
 */
class ValuationController extends BaseController
{
    /** Value lost in the first year, which is much steeper than later years. */
    private const FIRST_YEAR_DEPRECIATION = 0.15;

    /** Compounding annual rate applied after the first year. */
    private const SUBSEQUENT_YEAR_RATE = 0.10;

    /** Deduction per km above the expected allowance. */
    private const EXCESS_KM_RATE = 0.00002;

    /** Kilometres per year treated as normal use before a mileage penalty. */
    private const EXPECTED_KM_PER_YEAR = 20_000;

    /** A car is never valued below this share of what was paid for it. */
    private const RESIDUAL_FLOOR = 0.15;

    public function __invoke(Request $request, Car $car): JsonResponse
    {
        $this->authorize('view', $car);

        if ($car->purchase_price_egp === null || $car->purchased_at === null) {
            return $this->success([
                'purchase_price_egp'   => null,
                'purchased_at'         => null,
                'estimated_value_egp'  => null,
                'depreciation_egp'     => null,
                'depreciation_percent' => null,
                'basis'                => 'unavailable',
                'note'                 => 'Record what the car cost and when it was bought to see an estimated value.',
            ]);
        }

        $purchasePrice = (float) $car->purchase_price_egp;
        $years         = max(0.0, $car->purchased_at->floatDiffInYears(now()));

        $value = $this->depreciate($purchasePrice, $years);
        $value = $this->applyMileagePenalty($value, $purchasePrice, (int) $car->current_km, $years);
        $value = max($value, $purchasePrice * self::RESIDUAL_FLOOR);

        $depreciation = $purchasePrice - $value;

        return $this->success([
            'purchase_price_egp'   => number_format($purchasePrice, 2, '.', ''),
            'purchased_at'         => $car->purchased_at->toDateString(),
            'estimated_value_egp'  => number_format($value, 2, '.', ''),
            'depreciation_egp'     => number_format($depreciation, 2, '.', ''),
            'depreciation_percent' => $purchasePrice > 0
                ? round($depreciation / $purchasePrice * 100, 1)
                : 0.0,
            'basis' => 'estimate',
            'note'  => 'Derived from purchase price, age and mileage. Not a market appraisal.',
        ]);
    }

    private function depreciate(float $price, float $years): float
    {
        if ($years <= 0) {
            return $price;
        }

        // The first year is pro-rated so a six-month-old car does not take the
        // full first-year hit.
        $firstYearPortion = min($years, 1.0);
        $value = $price * (1 - self::FIRST_YEAR_DEPRECIATION * $firstYearPortion);

        if ($years > 1.0) {
            $value *= (1 - self::SUBSEQUENT_YEAR_RATE) ** ($years - 1.0);
        }

        return $value;
    }

    private function applyMileagePenalty(float $value, float $price, int $km, float $years): float
    {
        $expected = self::EXPECTED_KM_PER_YEAR * max($years, 1.0);
        $excess   = $km - $expected;

        if ($excess <= 0) {
            return $value;
        }

        return $value - ($price * self::EXCESS_KM_RATE * $excess);
    }
}
