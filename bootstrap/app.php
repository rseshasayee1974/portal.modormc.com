<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\SetEntityContext::class,
            \App\Http\Middleware\RequireOtpVerification::class,
        ]);

        $middleware->alias([
            'api.key' => \App\Http\Middleware\ApiKeyMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Normalize JSON error shape for frontend mapping (especially 422 validation).
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response) {
            if (!request()->expectsJson()) {
                return $response;
            }

            $status = $response->getStatusCode();

            // Validation errors may come back as: { message, errors: { field: [...] } }
            // We wrap into a consistent envelope for the SPA.
            if ($status === 422) {
                $content = $response->getContent();
                $decoded = is_string($content) ? json_decode($content, true) : null;

                if (is_array($decoded) && isset($decoded['errors']) && is_array($decoded['errors'])) {
                    return response()->json([
                        'success' => false,
                        'message' => $decoded['message'] ?? 'Validation failed.',
                        'errors'  => $decoded['errors'],
                        'code'    => 422,
                    ], 422);
                }
            }

            // Generic JSON error envelope (keep original message where possible).
            if ($status >= 400) {
                $content = $response->getContent();
                $decoded = is_string($content) ? json_decode($content, true) : null;

                $message = 'Request failed.';
                if (is_array($decoded)) {
                    $message = $decoded['message'] ?? $decoded['error'] ?? $message;
                }

                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'code'    => $status,
                ], $status);
            }

            return $response;
        });

        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response) {
            // Only use the custom Inertia error view if debugging is disabled
            if (!config('app.debug') 
                && in_array($response->getStatusCode(), [400, 403, 404, 419, 429, 500, 503])
                && !request()->expectsJson()
            ) {
                return \Inertia\Inertia::render('Error', [
                    'status' => $response->getStatusCode(),
                ])->toResponse(request())->setStatusCode($response->getStatusCode());
            }
            return $response;
        });
    })->create();
