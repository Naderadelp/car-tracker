<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use App\Models\Traits\DocumentRelations;

class Document extends Model implements HasMedia
{
    use DocumentRelations, HasFactory, InteractsWithMedia, LogsActivity;

    public const TYPES = [
        'vehicle_license',
        'insurance_policy',
        'registration',
        'inspection_certificate',
        'driver_license',
        'finance_contract',
    ];

    public static $logAttributes = ['*'];
    protected static $logName = 'Document';

    protected $table = 'documents';

    protected $fillable = [
        'user_id',
        'car_id',
        'type',
        'expiry_date',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('vehicle_documents')
             ->singleFile()
             ->useDisk('local');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*']);
    }
}
