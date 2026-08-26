<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * Gap C3: the `errors` field used to flip JSON type — an object on a
         * validation failure, an empty array on a controller error — so no
         * single typed error model on the client could parse both. Every API
         * failure now answers with `{message, errors}` where `errors` is always
         * an object.
         *
         * Scoped to `api/*` so the Filament admin panel keeps Laravel's own
         * HTML error pages and redirect behaviour.
         */
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e instanceof ValidationException) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors'  => (object) $e->errors(),
                ], $e->status);
            }

            // Neither of these implements HttpExceptionInterface at the point a
            // render callback sees them, so without explicit cases a 401 or a
            // 403 would fall through to the framework's bare {"message": ...}
            // and the client would need a second error model after all.
            if ($e instanceof AuthenticationException) {
                return response()->json([
                    'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Unauthenticated.',
                    'errors'  => (object) [],
                ], 401);
            }

            if ($e instanceof AuthorizationException) {
                return response()->json([
                    'message' => $e->getMessage() !== '' ? $e->getMessage() : 'This action is unauthorized.',
                    'errors'  => (object) [],
                ], 403);
            }

            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();

                // Route-model binding puts the model class and id in the
                // message ("No query results for model [App\Models\Brand] 99999"),
                // which leaks internal structure to anyone probing an id. The
                // real message is still available while debugging.
                $message = $status === 404 && ! config('app.debug')
                    ? 'Resource not found.'
                    : ($e->getMessage() !== '' ? $e->getMessage() : 'Request failed.');

                return response()->json([
                    'message' => $message,
                    'errors'  => (object) [],
                ], $status);
            }

            // Unhandled 500s fall through to the framework while debugging, so
            // the stack trace is not swallowed. In production they are reported
            // in the same envelope as everything else.
            if (config('app.debug')) {
                return null;
            }

            return response()->json([
                'message' => 'Server error.',
                'errors'  => (object) [],
            ], 500);
        });
    })->create();
