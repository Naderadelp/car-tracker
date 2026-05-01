<?php

namespace App\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder as SpatieQueryBuilder;
use App\Repositories\Contracts\RepositoryInterface;

abstract class EloquentRepository implements RepositoryInterface
{
    protected Model $model;

    protected array $include = [];

    protected array $allowedIncludes = [];

    protected array $allowedFilters = [];

    protected array $allowedFiltersExact = [];

    protected array $allowedFilterScopes = [];

    protected array $allowedSorts = [];

    protected array $allowedDefaultSorts = [];

    public function __construct()
    {
        $this->makeModel();
        $this->boot();
    }

    abstract public function model(): string;

    public function boot(): void {}

    public function makeModel(): void
    {
        $instance = app($this->model());

        $this->model = empty($this->include)
            ? $instance
            : $instance->with($this->include);
    }

    public function resetModel(): void
    {
        $this->makeModel();
    }

    public function spatie(): static
    {
        $partialFilters = array_map(
            fn ($f) => is_string($f) ? AllowedFilter::partial($f) : $f,
            $this->allowedFilters,
        );

        $exactFilters = array_map(
            fn ($f) => is_string($f) ? AllowedFilter::exact($f) : $f,
            $this->allowedFiltersExact,
        );

        $scopeFilters = array_map(
            fn ($f) => is_string($f) ? AllowedFilter::scope($f) : $f,
            $this->allowedFilterScopes,
        );

        $allFilters = array_merge($partialFilters, $exactFilters, $scopeFilters);

        $query = SpatieQueryBuilder::for($this->model);

        if (!empty($this->allowedIncludes)) {
            $query->allowedIncludes($this->allowedIncludes);
        }

        if (!empty($allFilters)) {
            $query->allowedFilters($allFilters);
        }

        if (!empty($this->allowedSorts)) {
            $query->allowedSorts($this->allowedSorts);
        }

        if (!empty($this->allowedDefaultSorts)) {
            $query->defaultSort(...$this->allowedDefaultSorts);
        }

        $this->model = $query;

        return $this;
    }

    public function all(array $columns = ['*']): Collection
    {
        $results = $this->model->get($columns);
        $this->resetModel();

        return $results;
    }

    public function paginate(int $limit = 15, array $columns = ['*']): mixed
    {
        $results = $this->model->paginate($limit, $columns);
        $this->resetModel();

        return $results;
    }

    public function find(int|string $id, array $columns = ['*']): mixed
    {
        $result = $this->model->findOrFail($id, $columns);
        $this->resetModel();

        return $result;
    }

    public function findWhere(array $where, array $columns = ['*']): Collection
    {
        $result = $this->model->where($where)->get($columns);
        $this->resetModel();

        return $result;
    }

    public function findWhereFirst(array $where, array $columns = ['*']): mixed
    {
        $result = $this->model->where($where)->first($columns);
        $this->resetModel();

        return $result;
    }

    public function create(array $attributes): Model
    {
        $model = $this->model->newInstance($attributes);
        $model->save();
        $this->resetModel();

        return $model;
    }

    public function update(array $attributes, int|string $id): Model
    {
        $record = $this->model->findOrFail($id);
        $record->fill($attributes);
        $record->save();
        $this->resetModel();

        return $record;
    }

    public function delete(int|string $id): bool
    {
        $record = $this->model->findOrFail($id);
        $this->resetModel();

        return (bool) $record->delete();
    }

    public function where(string $column, mixed $value): static
    {
        $this->model = $this->model->where($column, $value);

        return $this;
    }

    public function with(array|string $relations): static
    {
        $this->model = $this->model->with($relations);

        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $this->model = $this->model->orderBy($column, $direction);

        return $this;
    }

    public function firstOrCreate(array $attributes, array $values = []): Model
    {
        $result = $this->model->firstOrCreate($attributes, $values);
        $this->resetModel();

        return $result;
    }

    public function pluck(string $column, ?string $key = null): mixed
    {
        $result = $this->model->pluck($column, $key);
        $this->resetModel();

        return $result;
    }
}
