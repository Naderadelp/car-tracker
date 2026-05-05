<?php

namespace App\Repositories\Eloquent;

use App\Models\Item;
use App\Repositories\Contracts\ItemRepository;

class ItemRepositoryEloquent extends EloquentRepository implements ItemRepository
{
    protected array $allowedIncludes      = ['services'];
    protected array $allowedFilters       = ['name'];
    protected array $allowedSorts         = ['name', 'price', 'created_at'];
    protected array $allowedDefaultSorts  = ['-id'];

    public function model(): string
    {
        return Item::class;
    }
}
