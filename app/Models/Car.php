<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use App\Models\Traits\CarRelations;

class Car extends Model
{
    use LogsActivity, SoftDeletes, CarRelations;

    public static $logAttributes = ['*'];
    protected static $logName = 'Car';

    protected $table = 'cars';

    protected $fillable = [
        'user_id',
        'brand_id',
        'car_model_id',
        'current_km',
        'has_warranty',
        'warranty_limit_km',
        'warranty_expiry_date',
    ];

    protected function casts(): array
    {
        return [
            'current_km'           => 'integer',
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
