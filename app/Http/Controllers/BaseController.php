<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Traits\Responder;

abstract class BaseController extends Controller
{
    use Responder;

    /**
     * Every payload is wrapped in `data`, and `message` is included whenever one
     * is given. Passing a JsonResource straight to response()->json() serialises
     * it through jsonSerialize(), which skips the `data` wrapper that Laravel's
     * own toResponse() path adds — so wrapping here is what keeps a single
     * resource, a resource collection and a paginated list decodable by one
     * client-side decoder.
     */
    protected function success(mixed $data, int $status = 200, string $message = ''): JsonResponse
    {
        $payload = ['data' => $data];

        if ($message !== '') {
            $payload['message'] = $message;
        }

        return response()->json($payload, $status);
    }

    /**
     * `errors` is cast to an object so it serialises as `{}` when empty rather
     * than `[]`. An empty PHP array encodes as a JSON array, which made this
     * field flip type between validation failures (object) and controller
     * errors (array), so no single typed error model could parse both.
     */
    protected function error(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'errors'  => (object) $errors,
        ], $status);
    }

    protected function paginated(LengthAwarePaginator $paginator, string $resourceClass): JsonResponse
    {
        return response()->json([
            'data' => $resourceClass::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }
}
