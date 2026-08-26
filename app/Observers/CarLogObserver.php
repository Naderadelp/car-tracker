<?php

namespace App\Observers;

use App\Models\CarLog;
use App\Models\Cost;
use App\Support\CostLedger;

/**
 * Decision D2 — maintenance spending appears in the cost ledger without the
 * driver entering it twice.
 */
class CarLogObserver
{
    public function created(CarLog $log): void
    {
        CostLedger::carryAcross(
            sourceType: Cost::SOURCE_CAR_LOG,
            source: $log,
            category: 'service',
            title: $this->title($log),
            amount: (float) $log->actual_cost,
            spentAt: $log->performed_at,
        );
    }

    public function updated(CarLog $log): void
    {
        CostLedger::syncFromSource(
            sourceType: Cost::SOURCE_CAR_LOG,
            source: $log,
            title: $this->title($log),
            amount: (float) $log->actual_cost,
            spentAt: $log->performed_at,
        );
    }

    public function deleted(CarLog $log): void
    {
        CostLedger::detachSource(Cost::SOURCE_CAR_LOG, $log->id);
    }

    /**
     * `title` and `workshop` arrive with US7 (gap F4). Before that, ad-hoc work
     * recorded a cost with no description at all, so the fallback matters.
     */
    private function title(CarLog $log): string
    {
        $parts = array_filter([
            $log->title ?: 'Service',
            $log->workshop,
        ]);

        return implode(' — ', $parts);
    }
}
