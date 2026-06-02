<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Reminder extends Model
{
    use LogsActivity;

    protected $table = 'reminders';

    protected $fillable = [
        'car_id',
        'remind_on',
        'remind_at_km',
        'title',
        'description',
        'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'remind_on'    => 'date',
            'remind_at_km' => 'integer',
            'notified_at'  => 'datetime',
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
