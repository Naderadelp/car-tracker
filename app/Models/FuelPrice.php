<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class FuelPrice extends Model
{
    use LogsActivity;

    protected $table = 'fuel_prices';

    protected $fillable = [
        'type',
        'price_per_unit',
        'effective_from',
    ];

    protected function casts(): array
    {
        return [
            'price_per_unit' => 'decimal:2',
            'effective_from' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*']);
    }
}
