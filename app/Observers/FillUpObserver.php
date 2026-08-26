<?php

namespace App\Observers;

use App\Models\Cost;
use App\Models\FillUp;
use App\Support\CostLedger;

/**
 * Decision D2 — fuel spending appears in the cost ledger without the driver
 * entering it twice.
 *
 * TripObserver is the precedent for observers mutating a second table in this
 * codebase.
 */
class FillUpObserver
{
    public function created(FillUp $fillUp): void
    {
        CostLedger::carryAcross(
            sourceType: Cost::SOURCE_FILL_UP,
            source: $fillUp,
            category: 'fuel',
            title: $this->title($fillUp),
            amount: (float) $fillUp->cost_egp,
            spentAt: $fillUp->fill_date,
        );
    }

    public function updated(FillUp $fillUp): void
    {
        CostLedger::syncFromSource(
            sourceType: Cost::SOURCE_FILL_UP,
            source: $fillUp,
            title: $this->title($fillUp),
            amount: (float) $fillUp->cost_egp,
            spentAt: $fillUp->fill_date,
        );
    }

    public function deleted(FillUp $fillUp): void
    {
        CostLedger::detachSource(Cost::SOURCE_FILL_UP, $fillUp->id);
    }

    private function title(FillUp $fillUp): string
    {
        return $fillUp->station_name
            ? "Fuel — {$fillUp->station_name}"
            : 'Fuel';
    }
}
