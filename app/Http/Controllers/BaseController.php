<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    /** Fallback page size when the client asks for nothing. */
    protected const PER_PAGE_DEFAULT = 15;

    /**
     * Hard ceiling. The mobile client's screens open on the ledger and the
     * fault log, and a driver with years of history behind them would
     * otherwise be able to ask for all of it in one query.
     */
    protected const PER_PAGE_MAX = 100;

    /**
     * `?per_page=` on any paginated collection.
     *
     * Page size used to be fixed at 15 with no way to change it, so a client
     * that wanted a whole list had to walk it a page at a time on every screen
     * open. Out-of-range and non-numeric values are clamped rather than
     * rejected: a page size is a hint, and answering 422 to `per_page=1000`
     * would fail a request that has a perfectly good answer.
     */
    protected function perPage(?Request $request = null): int
    {
        $requested = ($request ?? request())->query('per_page');

        if (! is_numeric($requested)) {
            return self::PER_PAGE_DEFAULT;
        }

        return (int) max(1, min(self::PER_PAGE_MAX, (int) $requested));
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
