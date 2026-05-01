<?php

namespace App\Repositories\Contracts;

interface RepositoryInterface
{
    public function all(array $columns = ['*']): mixed;

    public function paginate(int $limit = 15, array $columns = ['*']): mixed;

    public function find(int|string $id, array $columns = ['*']): mixed;

    public function findWhere(array $where, array $columns = ['*']): mixed;

    public function findWhereFirst(array $where, array $columns = ['*']): mixed;

    public function create(array $attributes): mixed;

    public function update(array $attributes, int|string $id): mixed;

    public function delete(int|string $id): bool;

    public function with(array|string $relations): static;

    public function orderBy(string $column, string $direction = 'asc'): static;

    public function firstOrCreate(array $attributes, array $values = []): mixed;

    public function pluck(string $column, ?string $key = null): mixed;
}
