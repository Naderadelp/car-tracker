<?php

namespace App\Support;

use App\Models\Cost;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The carry-across rules for the unified cost ledger (decision D2), in one
 * place so FillUpObserver and CarLogObserver cannot drift apart.
 *
 * Three rules govern every method here:
 *
 *   1. A source record produces at most one ledger row. The database enforces
 *      it with a unique index on (source_type, source_id); this class simply
 *      never tries to create a second.
 *   2. Once a driver overwrites the amount (`amount_overridden`), their figure
 *      is the authority and a source edit must not undo it (FR-046).
 *   3. Deleting a source removes its row — unless the driver overrode it, in
 *      which case the row survives as a manual entry, because the corrected
 *      figure is deliberate data rather than a copy.
 */
class CostLedger
{
    public static function carryAcross(
        string $sourceType,
        Model $source,
        string $category,
        string $title,
        float $amount,
        Carbon|string|null $spentAt,
    ): void {
        $car = $source->car;

        // A source with no car cannot be attributed to a driver's ledger.
        if ($car === null || $car->user_id === null) {
            return;
        }

        Cost::query()->updateOrCreate(
            [
                'source_type' => $sourceType,
                'source_id'   => $source->id,
            ],
            [
                'car_id'     => $car->id,
                'user_id'    => $car->user_id,
                'spent_at'   => $spentAt ?? now()->toDateString(),
                'title'      => $title,
                'amount_egp' => $amount,
                'category'   => $category,
            ],
        );
    }

    public static function syncFromSource(
        string $sourceType,
        Model $source,
        string $title,
        float $amount,
        Carbon|string|null $spentAt,
    ): void {
        $cost = Cost::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $source->id)
            ->first();

        if ($cost === null) {
            return;
        }

        // FR-046 — the driver's correction wins over a later source edit.
        if (! $cost->isObserverManaged()) {
            return;
        }

        $cost->fill([
            'title'      => $title,
            'amount_egp' => $amount,
            'spent_at'   => $spentAt ?? $cost->spent_at,
        ])->save();
    }

    public static function detachSource(string $sourceType, int $sourceId): void
    {
        $cost = Cost::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->first();

        if ($cost === null) {
            return;
        }

        /*
         * The edge case named in the spec. If the driver corrected the amount,
         * that figure is theirs and outlives the record it came from — the row
         * becomes an ordinary manual entry rather than an orphan pointing at a
         * source that no longer exists.
         */
        if ($cost->amount_overridden) {
            $cost->fill([
                'source_type' => null,
                'source_id'   => null,
            ])->save();

            return;
        }

        $cost->delete();
    }
}
