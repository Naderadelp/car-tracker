<?php

namespace App\Models;

use App\Models\Traits\CostRelations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One item of spending on a car — gap B4.
 *
 * A row is either typed in by the driver (`source_type` null) or carried
 * across from a FillUp or CarLog (decision D2). A carried-across row stays in
 * step with its source until the driver overwrites the amount, at which point
 * `amount_overridden` flips and the observers leave it alone.
 */
class Cost extends Model
{
    use CostRelations, HasFactory, LogsActivity;

    /**
     * The six the mobile app already owns. Fuel and service are included
     * deliberately: decision D2 keeps one ledger for everything rather than
     * splitting manual from derived spending.
     */
    public const CATEGORIES = [
        'fuel',
        'service',
        'insurance',
        'tyres',
        'warranty',
        'other',
    ];

    public const SOURCE_FILL_UP = 'fill_up';
    public const SOURCE_CAR_LOG = 'car_log';

    public static $logAttributes = ['*'];
    protected static $logName = 'Cost';

    protected $table = 'costs';

    protected $fillable = [
        'car_id',
        'user_id',
        'spent_at',
        'title',
        'amount_egp',
        'category',
        'source_type',
        'source_id',
        'amount_overridden',
    ];

    /**
     * The column has a database default, but a model built with newInstance()
     * and saved does not know about it — the attribute would read `null` until
     * the row was refetched, so a freshly created cost serialised
     * `amount_overridden: null` instead of `false`.
     */
    protected $attributes = [
        'amount_overridden' => false,
    ];

    protected function casts(): array
    {
        return [
            'spent_at'          => 'date',
            'amount_egp'        => 'decimal:2',
            'amount_overridden' => 'boolean',
        ];
    }

    /**
     * True when this row was created by an observer rather than by a driver.
     */
    public function isCarriedAcross(): bool
    {
        return $this->source_type !== null;
    }

    /**
     * Whether an observer is still allowed to write to this row. Once a driver
     * has corrected the amount, their figure is the authority (FR-045/FR-046).
     */
    public function isObserverManaged(): bool
    {
        return $this->isCarriedAcross() && ! $this->amount_overridden;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*']);
    }
}
