<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Service extends Model
{
    use LogsActivity;

    public static $logAttributes = ['*'];
    protected static $logName = 'Service';

    protected $table = 'services';

    protected $fillable = [
        'car_model_id',
        'car_id',
        'user_id',
        'km',
        'price',
    ];

    protected $appends = ['is_catalogue'];

    protected function casts(): array
    {
        return [
            'km'    => 'integer',
            'price' => 'decimal:2',
        ];
    }

    public function carModel(): BelongsTo
    {
        return $this->belongsTo(CarModel::class, 'car_model_id');
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'service_items')
            ->withPivot('car_id', 'name', 'price')
            ->withTimestamps();
    }

    /**
     * Every checklist line, including a driver's own (gap F3).
     *
     * `items()` above is a belongsToMany joining on `item_id`, so a custom line
     * — which has none — never appears through it. This relation is what the
     * API returns; `items()` remains for the admin panel, which only ever deals
     * in catalogue entries.
     */
    public function checklist(): HasMany
    {
        return $this->hasMany(ServiceItem::class, 'service_id');
    }

    public function getIsCatalogueAttribute(): bool
    {
        return is_null($this->user_id);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*']);
    }
}
