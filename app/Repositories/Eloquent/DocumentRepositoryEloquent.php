<?php

namespace App\Repositories\Eloquent;

use App\Models\Document;
use App\Repositories\Contracts\DocumentRepository;

class DocumentRepositoryEloquent extends EloquentRepository implements DocumentRepository
{
    protected array $allowedFiltersExact = ['type'];

    protected array $allowedSorts = ['created_at'];

    protected array $include = ['user'];

    public function model(): string
    {
        return Document::class;
    }

    protected function scopeToUser(): void
    {
        if (auth()->check() && !auth()->user()->isAdmin()) {
            $this->model = $this->model->where('user_id', auth()->id());
        }
    }

    public function spatie(): static
    {
        // Spec FR-009: non-expiring documents listed last, soonest expiring first
        $this->model = $this->model->orderByRaw('ISNULL(expiry_date) ASC, expiry_date ASC');

        return parent::spatie();
    }
}
