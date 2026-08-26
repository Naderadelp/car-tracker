<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class CarLog extends Model
{
    use LogsActivity;

    protected $table = 'car_logs';

    protected $fillable = [
        'car_id',
        'service_id',
        // Gap F4 — ad-hoc work (service_id null) used to record a cost with no
        // description at all.
        'title',
        'workshop',
        'category',
        'odometer_at_service',
        'actual_cost',
        'performed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'odometer_at_service' => 'integer',
            'actual_cost'         => 'decimal:2',
            'performed_at'        => 'date',
        ];
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*']);
    }
}
