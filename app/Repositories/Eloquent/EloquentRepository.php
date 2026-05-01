<?php

namespace App\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use App\Repositories\Contracts\RepositoryInterface;

abstract class EloquentRepository implements RepositoryInterface
{
    protected Model $model;

    private ?Builder $pendingQuery = null;

    public function __construct()
    {
        $this->model = app($this->model());
    }

    abstract public function model(): string;

    private function builder(): Builder
    {
        $query = $this->pendingQuery ?? $this->model->newQuery();
        $this->pendingQuery = null;

        return $query;
    }

    public function all(array $columns = ['*']): Collection
    {
        return $this->builder()->get($columns);
    }

    public function paginate(int $limit = 15, array $columns = ['*']): mixed
    {
        return $this->builder()->paginate($limit, $columns);
    }

    public function find(int|string $id, array $columns = ['*']): mixed
    {
        return $this->builder()->find($id, $columns);
    }

    public function findWhere(array $where, array $columns = ['*']): Collection
    {
        return $this->builder()->where($where)->get($columns);
    }

    public function findWhereFirst(array $where, array $columns = ['*']): mixed
    {
        return $this->builder()->where($where)->first($columns);
    }

    public function create(array $attributes): Model
    {
        return $this->model->create($attributes);
    }

    public function update(array $attributes, int|string $id): Model
    {
        $record = $this->model->newQuery()->findOrFail($id);
        $record->update($attributes);

        return $record;
    }

    public function delete(int|string $id): bool
    {
        return (bool) $this->model->newQuery()->findOrFail($id)->delete();
    }

    public function with(array|string $relations): static
    {
        $this->pendingQuery = ($this->pendingQuery ?? $this->model->newQuery())->with($relations);

        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $this->pendingQuery = ($this->pendingQuery ?? $this->model->newQuery())->orderBy($column, $direction);

        return $this;
    }

    public function firstOrCreate(array $attributes, array $values = []): Model
    {
        return $this->model->firstOrCreate($attributes, $values);
    }

    public function pluck(string $column, ?string $key = null): mixed
    {
        return $this->builder()->pluck($column, $key);
    }
}
