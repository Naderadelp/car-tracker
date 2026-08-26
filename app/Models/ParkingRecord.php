<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ParkingRecord extends Model
{
    use LogsActivity;

    public static $logAttributes = ['*'];
    protected static $logName = 'ParkingRecord';

    protected $table = 'parking_records';

    protected $fillable = [
        'car_id',
        'name',
        'description',
        // Gap F7 — label, address and note are three distinct strings in the app.
        'address',
        'latitude',
        'longitude',
        'parked_at',
    ];

    protected function casts(): array
    {
        return [
            'parked_at' => 'datetime',
            'latitude'  => 'decimal:8',
            'longitude' => 'decimal:8',
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
