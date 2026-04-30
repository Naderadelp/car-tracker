<?php

namespace Src\Common\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

trait Responder
{
    protected array $data = [];
    protected ?\Closure $apiResponse = null;

    public function setData(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function useCollection(string $resourceClass, string $key): void
    {
        $value = $this->data[$key] ?? null;

        if ($value === null) {
            return;
        }

        $this->data[$key] = is_iterable($value) && ! ($value instanceof \Illuminate\Database\Eloquent\Model)
            ? $resourceClass::collection($value)
            : new $resourceClass($value);
    }

    public function setApiResponse(\Closure $callback): void
    {
        $this->apiResponse = $callback;
    }

    public function response(): JsonResponse
    {
        if ($this->apiResponse !== null) {
            return ($this->apiResponse)();
        }

        return response()->json(['data' => $this->data]);
    }
}
