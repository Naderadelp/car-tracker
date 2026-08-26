<?php

namespace App\Repositories\Eloquent;

use App\Models\Issue;
use App\Repositories\Contracts\IssueRepository;
use Illuminate\Support\Collection;

class IssueRepositoryEloquent extends EloquentRepository implements IssueRepository
{
    /**
     * Media is eager-loaded so a fault's photo metadata is always available
     * without an extra query (constitution Principle I).
     */
    protected array $include             = ['media'];
    protected array $allowedIncludes     = ['media', 'car'];
    protected array $allowedFilters      = ['title'];
    protected array $allowedFiltersExact = ['severity', 'car_id'];
    protected array $allowedSorts        = ['occurred_at', 'severity', 'created_at'];

    /**
     * A fault log is read by when the fault happened, not by insertion order.
     */
    protected array $allowedDefaultSorts = ['-occurred_at'];

    public function model(): string
    {
        return Issue::class;
    }

    protected function scopeToUser(): void
    {
        if (auth()->check() && !auth()->user()->isAdmin()) {
            $this->model = $this->model->where('user_id', auth()->id());
        }
    }

    public function needingAttentionForCar(int $carId): Collection
    {
        return app($this->model())->newQuery()
            ->where('car_id', $carId)
            ->needingAttention()
            ->orderByDesc('occurred_at')
            ->get();
    }
}
