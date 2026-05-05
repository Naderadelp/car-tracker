<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
            ->withPivot('car_id')
            ->withTimestamps();
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
