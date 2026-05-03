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
        'start_time',
        'end_time',
        'start_lat',
        'start_lng',
        'end_lat',
        'end_lng',
        'total_distance_km',
    ];

    protected function casts(): array
    {
        return [
            'start_time'        => 'datetime',
            'end_time'          => 'datetime',
            'start_lat'         => 'decimal:8',
            'start_lng'         => 'decimal:8',
            'end_lat'           => 'decimal:8',
            'end_lng'           => 'decimal:8',
            'total_distance_km' => 'decimal:2',
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
