<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single line on a service interval's checklist — gap F3.
 *
 * The `service_items` pivot needed to become a model of its own because a
 * driver's own line has no `item_id`, and `Service::items()` is a
 * belongsToMany that joins on exactly that column: a custom line would be
 * invisible through it. This relation returns every line, catalogue-linked or
 * not.
 *
 * Resolution is override-then-catalogue: `name` and `price` on the pivot win
 * when set, otherwise the linked Item supplies them.
 */
class ServiceItem extends Model
{
    protected $table = 'service_items';

    protected $fillable = [
        'service_id',
        'item_id',
        'car_id',
        'name',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function resolvedName(): ?string
    {
        return $this->name ?? $this->item?->name;
    }

    public function resolvedPrice(): ?string
    {
        return $this->price ?? $this->item?->price;
    }

    /**
     * True when a driver added this line rather than the catalogue.
     */
    public function isCustom(): bool
    {
        return $this->item_id === null;
    }
}
