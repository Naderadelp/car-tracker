<?php

namespace App\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use App\Repositories\Contracts\RepositoryInterface;

abstract class EloquentRepository implements RepositoryInterface
{
    protected Model $model;

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

    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $this->model = $this->model->orderBy($column, $direction);

        return $this;
    }

    public function firstOrCreate(array $attributes, array $values = []): Model
    {
        return $this->model->firstOrCreate($attributes, $values);
    }

    public function pluck(string $column, ?string $key = null): mixed
    {
        return $this->model->pluck($column, $key);
    }
}
