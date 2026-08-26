<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class FillUp extends Model
{
    use LogsActivity;

    protected $table = 'fill_ups';

    protected $fillable = [
        'car_id',
        'liters',
        'tank_percentage',
        'odometer',
        'cost_egp',
        'fill_date',
        'fuel_type',
        'station_name',
        'station_lat',
        'station_lng',
    ];

    protected function casts(): array
    {
        return [
            'fill_date' => 'date',
            'liters'    => 'decimal:2',
            'tank_percentage' => 'decimal:2',
            'cost_egp'  => 'decimal:2',
            'fuel_type' => 'string',
            'station_lat' => 'decimal:8',
            'station_lng' => 'decimal:8',
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
