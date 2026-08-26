<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Item extends Model
{
    use LogsActivity;

    public static $logAttributes = ['*'];
    protected static $logName = 'Item';

    protected $table = 'items';

    protected $fillable = [
        'name',
        // Gap F6 — the app switches locale without a refetch, so both variants
        // ship together.
        'name_ar',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_items')
            ->withPivot('car_id')
            ->withTimestamps();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*']);
    }
}
