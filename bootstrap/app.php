<?php

use App\Exceptions\AccountingException;
use App\Http\Middleware\ApiKeyMiddleware;
use App\Http\Middleware\EnforceBrowserSession;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireOtpVerification;
use App\Http\Middleware\SetEntityContext;
use App\Http\Middleware\SetEntityTimezone;
use App\Http\Middleware\TitleCaseInputs;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('api')
                ->prefix('api/mobile')
                ->group(base_path('routes/mobileapp.php'));
        },
    )
    ->withBroadcasting(__DIR__.'/../routes/channels.php', [
        'middleware' => ['web', 'auth:sanctum'],
        'excluded_middleware' => [TitleCaseInputs::class],
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            TitleCaseInputs::class,
            EnforceBrowserSession::class,
            SetEntityContext::class,
            HandleInertiaRequests::class,
            // Keep asset hints below Apache/FastCGI response header limits on full page loads.
            AddLinkHeadersForPreloadedAssets::using(10),
            SetEntityTimezone::class,
            RequireOtpVerification::class,
        ]);

        $middleware->api(append: [
            TitleCaseInputs::class,
        ]);

        $middleware->alias([
            'api.key' => ApiKeyMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if ($request->inertia()) {
                return Inertia::location(route('login'));
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your session has expired. Please log in again.',
                    'redirect' => route('login'),
                ], 419);
            }

            return redirect()->route('login')->with('status', 'Your session has expired. Please log in again.');
        });

        $exceptions->render(function (AccountingException $e, Request $request) {
            if ($request->expectsJson() && ! $request->inertia()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'code' => 422,
                ], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->inertia()) {
                return Inertia::location(route('login'));
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->guest(route('login'));
        });

        // Normalize JSON error shape for frontend mapping (especially 422 validation).
        $exceptions->respond(function (Response $response) {
            if (! request()->expectsJson()) {
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
                        'errors' => $decoded['errors'],
                        'code' => 422,
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
                    'code' => $status,
                ], $status);
            }

            return $response;
        });

        $exceptions->respond(function (Response $response) {
            if (request()->is('api/mobile/*') && $response->getStatusCode() >= 400) {
                $status = $response->getStatusCode();
                $decoded = json_decode((string) $response->getContent(), true);
                $payload = ['message' => $status >= 500 ? 'Server error.' : ($decoded['message'] ?? 'Request failed.')];
                if ($status === 422 && isset($decoded['errors'])) {
                    $payload['errors'] = $decoded['errors'];
                }

                return response()->json($payload, $status, Arr::only($response->headers->all(), ['retry-after', 'www-authenticate']));
            }
            // Only use the custom Inertia error view if debugging is disabled
            if (! config('app.debug')
                && in_array($response->getStatusCode(), [400, 403, 404, 419, 429, 500, 503])
                && ! request()->expectsJson()
            ) {
                return Inertia::render('Error', [
                    'status' => $response->getStatusCode(),
                ])->toResponse(request())->setStatusCode($response->getStatusCode());
            }

            return $response;
        });
    })->create();
