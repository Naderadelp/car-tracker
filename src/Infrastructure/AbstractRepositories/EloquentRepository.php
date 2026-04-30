<?php

namespace Src\Infrastructure\AbstractRepositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Src\Infrastructure\l5\Contracts\RepositoryInterface;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

abstract class EloquentRepository implements RepositoryInterface
{
    protected Model $model;

    protected array $allowedIncludes = [];
    protected array $allowedFilters = [];
    protected array $allowedFiltersExact = [];
    protected array $allowedFilterScopes = [];
    protected array $allowedSorts = [];
    protected array $allowedDefaultSorts = [];

    protected ?\Closure $scopeQuery = null;

    public function __construct()
    {
        $this->model = app($this->model());
    }

    abstract public function model(): string;

    public function all(array $columns = ['*']): Collection
    {
        return $this->model->get($columns);
    }

    public function paginate(int $limit = 15, array $columns = ['*']): mixed
    {
        return $this->model->paginate($limit, $columns);
    }

    public function find(int|string $id, array $columns = ['*']): mixed
    {
        return $this->model->find($id, $columns);
    }

    public function findWhere(array $where, array $columns = ['*']): Collection
    {
        return $this->model->where($where)->get($columns);
    }

    public function findWhereFirst(array $where, array $columns = ['*']): mixed
    {
        return $this->model->where($where)->first($columns);
    }

    public function findWhereIn(string $field, array $values, array $columns = ['*']): Collection
    {
        return $this->model->whereIn($field, $values)->get($columns);
    }

    public function create(array $attributes): Model
    {
        return $this->model->create($attributes);
    }

    public function update(array $attributes, int|string $id): Model
    {
        $model = $this->model->findOrFail($id);
        $model->update($attributes);

        return $model;
    }

    public function delete(int|string $id): bool
    {
        return (bool) $this->model->findOrFail($id)->delete();
    }

    public function with(array|string $relations): static
    {
        $this->model = $this->model->with($relations);

        return $this;
    }

    public function scopeQuery(\Closure $scope): static
    {
        $this->scopeQuery = $scope;

        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $this->model = $this->model->orderBy($column, $direction);

        return $this;
    }

    public function firstOrCreate(array $attributes, array $values = []): Model
    {
        return $this->model->firstOrCreate($attributes, $values);
    }

    public function firstOrNew(array $attributes, array $values = []): Model
    {
        return $this->model->firstOrNew($attributes, $values);
    }

    public function pluck(string $column, ?string $key = null): mixed
    {
        return $this->model->pluck($column, $key);
    }

    public function withCount(array|string $relations): static
    {
        $this->model = $this->model->withCount($relations);

        return $this;
    }

    public function spatie(array $queryParams = []): static
    {
        $query = QueryBuilder::for($this->model()->class ?? $this->model()->getMorphClass())
            ->allowedIncludes($this->allowedIncludes)
            ->allowedSorts($this->allowedSorts)
            ->defaultSort(...$this->allowedDefaultSorts);

        if (! empty($this->allowedFilters)) {
            $partialFilters = array_map(
                fn ($f) => AllowedFilter::partial($f),
                $this->allowedFilters
            );
            $query->allowedFilters($partialFilters);
        }

        if (! empty($this->allowedFiltersExact)) {
            $exactFilters = array_map(
                fn ($f) => AllowedFilter::exact($f),
                $this->allowedFiltersExact
            );
            $query->allowedFilters($exactFilters);
        }

        if (! empty($queryParams)) {
            request()->merge($queryParams);
        }

        $this->model = $query;

        return $this;
    }
}
