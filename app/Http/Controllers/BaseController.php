<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Traits\Responder;

abstract class BaseController extends Controller
{
    use Responder;

    protected function success(mixed $data, int $status = 200, string $message = ''): JsonResponse
    {
        $payload = ['success' => true, 'data' => $data];

        if ($message !== '') {
            $payload['message'] = $message;
        }

        return response()->json($payload, $status);
    }

    protected function error(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], $status);
    }

    protected function paginated(LengthAwarePaginator $paginator, string $resourceClass): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $resourceClass::collection($paginator->items()),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }
}
