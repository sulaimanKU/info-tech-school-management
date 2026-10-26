<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(api: __DIR__.'/../routes/api.php', commands: __DIR__.'/../routes/console.php')
    ->withMiddleware(function (Middleware $middleware) {})
    ->withExceptions(function (Exceptions $exceptions) {

        $exceptions->report(function (Throwable $e) {
            // Never serialize request bodies, SQL bindings, OTP/provider payloads, or private records.
            Log::error('Unhandled server exception', ['type' => get_class($e), 'source' => basename($e->getFile()), 'line' => $e->getLine()]);

            return false;
        });
        $exceptions->shouldRenderJsonWhen(fn (Request $r) => true);
        $exceptions->render(function (Throwable $e, Request $r) {
            $databaseState = $e instanceof QueryException ? ($e->errorInfo[0] ?? null) : null;
            $status = $databaseState === '23505' ? 409 : ($databaseState === '23514' || $databaseState === '23503' ? 422 : ($e instanceof ValidationException ? 422 : ($e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500)));
            $messages = [401 => 'Authentication failed.', 403 => 'Permission denied.', 404 => 'Resource not found.', 409 => 'Record conflict. Refresh and retry.', 503 => 'Service unavailable.', 405 => 'Method not allowed.', 422 => 'Validation failed.', 429 => 'Too many requests.'];
            $headers = $e instanceof HttpExceptionInterface ? $e->getHeaders() : [];

            return response()->json(['data' => null, 'error' => ['code' => $status, 'message' => $messages[$status] ?? 'Request failed.', 'fields' => $e instanceof ValidationException ? $e->errors() : (object) []]], $status, $headers);
        });
    })->create();
