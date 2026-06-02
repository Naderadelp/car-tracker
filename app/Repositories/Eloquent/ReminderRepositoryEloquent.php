<?php

namespace App\Repositories\Eloquent;

use App\Models\Reminder;
use App\Repositories\Contracts\ReminderRepository;

class ReminderRepositoryEloquent extends EloquentRepository implements ReminderRepository
{
    protected array $allowedIncludes     = ['car'];
    protected array $allowedFiltersExact = ['car_id'];
    protected array $allowedSorts        = ['remind_on', 'remind_at_km', 'created_at'];
    protected array $allowedDefaultSorts = ['-created_at'];

    public function model(): string
    {
        return Reminder::class;
    }

    protected function scopeToUser(): void
    {
        if (auth()->check() && !auth()->user()->isAdmin()) {
            $this->model = $this->model->whereHas('car', function ($q) {
                $q->where('user_id', auth()->id());
            });
        }
    }
}
