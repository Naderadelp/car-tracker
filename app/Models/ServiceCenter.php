<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ServiceCenter extends Model
{
    use LogsActivity;

    public static $logAttributes = ['*'];
    protected static $logName = 'ServiceCenter';

    protected $table = 'service_centers';

    protected $fillable = [
        'brand_id',
        'name',
        // Gap F6
        'name_ar',
        'address',
        'address_ar',
        'open_at',
        'close_at',
        'mobile',
        'lat',
        'lng',
    ];

    protected $appends = ['is_open'];

    protected function casts(): array
    {
        return [
            'open_at'  => 'datetime:H:i:s',
            'close_at' => 'datetime:H:i:s',
            'lat'      => 'decimal:8',
            'lng'      => 'decimal:8',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function getIsOpenAttribute(): bool
    {
        if (!$this->open_at || !$this->close_at) {
            return false;
        }

        $now   = now()->format('H:i:s');
        $open  = $this->open_at->format('H:i:s');
        $close = $this->close_at->format('H:i:s');

        return $now >= $open && $now < $close;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*']);
    }
}
