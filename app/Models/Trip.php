<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Trip extends Model
{
    use LogsActivity;

    public static $logAttributes = ['*'];
    protected static $logName = 'Trip';

    protected $table = 'trips';

    protected $fillable = [
        'car_id',
        'start_lat',
        'start_lng',
        'end_lat',
        'end_lng',
        'total_distance_km',
        // Gap F5 — duration and top speed were gone the moment a trip was posted.
        'started_at',
        'ended_at',
        'duration_seconds',
        'max_speed_kmh',
    ];

    protected function casts(): array
    {
        return [
            'start_lat'         => 'decimal:8',
            'start_lng'         => 'decimal:8',
            'end_lat'           => 'decimal:8',
            'end_lng'           => 'decimal:8',
            'total_distance_km' => 'decimal:2',
            'started_at'        => 'datetime',
            'ended_at'          => 'datetime',
            'duration_seconds'  => 'integer',
            'max_speed_kmh'     => 'decimal:2',
        ];
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*']);
    }
}
