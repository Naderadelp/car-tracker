<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use App\Models\Traits\VehicleRelations;

class Vehicle extends Model
{
    use LogsActivity, SoftDeletes, VehicleRelations;

    public static $logAttributes = ['*'];
    protected static $logName = 'Vehicle';

    protected $table = 'vehicles';

    protected $fillable = [
        'user_id',
        'brand',
        'model',
        'year',
        'current_mileage',
        'has_warranty',
        'warranty_limit_km',
        'warranty_expiry_date',
    ];

    protected function casts(): array
    {
        return [
            'year'                 => 'integer',
            'current_mileage'      => 'integer',
            'has_warranty'         => 'boolean',
            'warranty_limit_km'    => 'integer',
            'warranty_expiry_date' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*']);
    }
}
