<?php

namespace App\Repositories\Eloquent;

use App\Models\Car;
use App\Models\Service;
use App\Repositories\Contracts\ServiceRepository;
use Illuminate\Support\Collection;

class ServiceRepositoryEloquent extends EloquentRepository implements ServiceRepository
{
    protected array $allowedIncludes      = ['carModel', 'carModel.brand', 'car', 'user', 'items'];
    protected array $allowedFiltersExact  = ['car_model_id', 'car_id', 'user_id'];
    protected array $allowedSorts         = ['km', 'price', 'created_at'];
    protected array $allowedDefaultSorts  = ['km'];

    public function model(): string
    {
        return Service::class;
    }

    protected function scopeToUser(): void
    {
        if (auth()->check() && !auth()->user()->isAdmin()) {
            $this->model = $this->model->where(function ($q) {
                $q->whereNull('user_id')->orWhere('user_id', auth()->id());
            });
        }
    }

    /**
     * @param bool $includePast Gap F2 — the Services grid shows the *whole*
     *                          schedule: completed intervals greyed, the next
     *                          one highlighted, later ones ahead of it. The
     *                          `km > current_km` filter made everything already
     *                          passed unreachable from this route.
     */
    public function upcomingForCar(Car $car, bool $includePast = false): Collection
    {
        $query = app($this->model())->newQuery()
            ->where(function ($q) use ($car) {
                $q->where(function ($qq) use ($car) {
                    $qq->where('car_model_id', $car->car_model_id)
                       ->whereNull('user_id')
                       ->whereNull('car_id');
                })->orWhere(function ($qq) use ($car) {
                    $qq->where('car_id', $car->id)
                       ->where('user_id', $car->user_id);
                });
            });

        if (! $includePast) {
            $query->where('km', '>', $car->current_km);
        }

        /*
         * Gap F1 — this used to be withCount('items'), which is exactly the
         * bare `items_count` the app complained about: tapping an interval
         * opens a checklist, and that screen *is* the Services tab, so a count
         * forced a second paginated request to render anything.
         *
         * The count is kept alongside for any existing consumer.
         */
        return $query
            ->with(['checklist.item'])
            ->withCount('items')
            ->orderBy('km')
            ->get();
    }
}
