<?php

namespace App\Http\Controllers;

use App\Http\Requests\Item\StoreItemRequest;
use App\Http\Requests\Item\UpdateItemRequest;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use App\Repositories\Contracts\ItemRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ItemController extends BaseController
{
    public function __construct(
        protected ItemRepository $itemRepository,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Item::class);

        $items = $this->itemRepository->spatie()->paginate();

        return $this->paginated($items, ItemResource::class);
    }

    public function show(Request $request, Item $item): JsonResponse
    {
        $this->authorize('view', $item);

        return $this->success(new ItemResource($item));
    }

    public function store(StoreItemRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $item = $this->itemRepository->create($request->validated());

            DB::commit();

            return $this->success(
                new ItemResource($item),
                201,
                'Item created successfully.',
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error($e->getMessage(), 422);
        }
    }

    public function update(UpdateItemRequest $request, Item $item): JsonResponse
    {
        try {
            DB::beginTransaction();

            $updated = $this->itemRepository->update($request->validated(), $item->id);

            DB::commit();

            return $this->success(new ItemResource($updated));
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error($e->getMessage(), 422);
        }
    }

    public function destroy(Request $request, Item $item): JsonResponse
    {
        $this->authorize('delete', $item);

        $this->itemRepository->delete($item->id);

        return $this->success([], 200, 'Item deleted successfully.');
    }
}
