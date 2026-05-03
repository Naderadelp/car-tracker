<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class FillUp extends Model
{
    use LogsActivity;

    public static $logAttributes = ['*'];
    protected static $logName = 'FillUp';

    protected $table = 'fill_ups';

    protected $fillable = [
        'car_id',
        'liters',
        'odometer',
        'cost_egp',
        'fill_date',
    ];

    protected function casts(): array
    {
        return [
            'fill_date' => 'date',
            'liters'    => 'decimal:2',
            'cost_egp'  => 'decimal:2',
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
