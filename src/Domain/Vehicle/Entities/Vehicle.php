<?php

namespace Src\Domain\Vehicle\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Src\Domain\Vehicle\Entities\Traits\VehicleAttributes;
use Src\Domain\Vehicle\Entities\Traits\VehicleRelations;

class Vehicle extends Model
{
    use LogsActivity, SoftDeletes, VehicleAttributes, VehicleRelations;

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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*']);
    }
}
