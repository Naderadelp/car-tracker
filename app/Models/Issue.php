<?php

namespace App\Models;

use App\Models\Traits\IssueRelations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A recorded fault — gap B5.
 *
 * Photo handling mirrors Document exactly: a single file on the private
 * `local` disk, served through a StreamedResponse rather than a public URL
 * (constitution Principle VI).
 */
class Issue extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, IssueRelations, LogsActivity;

    public const SEVERITY_LOW    = 'low';
    public const SEVERITY_MEDIUM = 'medium';
    public const SEVERITY_HIGH   = 'high';

    /** Ordered least to most serious; `high` is what the attention list surfaces. */
    public const SEVERITIES = [
        self::SEVERITY_LOW,
        self::SEVERITY_MEDIUM,
        self::SEVERITY_HIGH,
    ];

    public const PHOTO_COLLECTION = 'issue_photo';

    public static $logAttributes = ['*'];
    protected static $logName = 'Issue';

    protected $table = 'issues';

    protected $fillable = [
        'car_id',
        'user_id',
        'occurred_at',
        'title',
        'severity',
        'summary',
        'solution',
        'note',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'date',
            'resolved_at' => 'datetime',
        ];
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    /**
     * FR-021 — what gets promoted onto the notifications screen alongside
     * overdue services.
     */
    public function scopeNeedingAttention(Builder $query): Builder
    {
        return $query->whereNull('resolved_at')
                     ->where('severity', self::SEVERITY_HIGH);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::PHOTO_COLLECTION)
             ->singleFile()
             ->useDisk('local');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*']);
    }
}
