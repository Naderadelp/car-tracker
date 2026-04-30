<?php

namespace Src\Infrastructure\l5\Contracts;

interface RepositoryInterface
{
    public function all(array $columns = ['*']): mixed;

    public function paginate(int $limit = 15, array $columns = ['*']): mixed;

    public function find(int|string $id, array $columns = ['*']): mixed;

    public function findWhere(array $where, array $columns = ['*']): mixed;

    public function findWhereFirst(array $where, array $columns = ['*']): mixed;

    public function findWhereIn(string $field, array $values, array $columns = ['*']): mixed;

    public function create(array $attributes): mixed;

    public function update(array $attributes, int|string $id): mixed;

    public function delete(int|string $id): bool;

    public function with(array|string $relations): static;

    public function scopeQuery(\Closure $scope): static;

    public function spatie(array $queryParams = []): static;

    public function orderBy(string $column, string $direction = 'asc'): static;

    public function firstOrCreate(array $attributes, array $values = []): mixed;

    public function firstOrNew(array $attributes, array $values = []): mixed;

    public function pluck(string $column, ?string $key = null): mixed;

    public function withCount(array|string $relations): static;
}
