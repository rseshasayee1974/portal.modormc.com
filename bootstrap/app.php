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
        $exceptions->shouldRenderJsonWhen(fn (Request $request, \Throwable $e) =>
            $request->is('api/mobile/*') || $request->expectsJson());

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, Request $request) {
            if (!$request->is('api/mobile/*')) return;
            $previous = $e->getPrevious();
            if (!$previous instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) return;

            $label = match ($previous->getModel()) {
                \App\Models\Quotation::class => 'Quotation',
                \App\Models\CrmLead::class => 'Lead',
                \App\Models\CrmActivity::class => 'Follow-up',
                \App\Models\SalesOrder::class => 'Sales order',
                \App\Models\CustomerPO::class => 'Customer PO',
                \App\Models\Site::class => 'Site',
                \App\Models\Patron::class => 'Customer',
                \App\Models\Attendance::class => 'Attendance record',
                \Illuminate\Notifications\DatabaseNotification::class => 'Notification',
                \App\Models\User::class => 'User',
                default => 'Record',
            };
            return response()->json(['message' => $label.' not found or unavailable to you in the selected plant.'], 404);
        });

        $exceptions->render(function (\Throwable $e, Request $request) {
            if (!$request->is('api/mobile/*')
                || $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                || $e instanceof \Illuminate\Validation\ValidationException
                || $e instanceof AccountingException
                || $e instanceof TokenMismatchException
                || $e instanceof AuthenticationException
                || $e instanceof \Illuminate\Auth\Access\AuthorizationException
                || $e instanceof \Illuminate\Http\Exceptions\HttpResponseException) return;

            $reference = (string) \Illuminate\Support\Str::uuid();
            \Illuminate\Support\Facades\Log::error('Mobile API request failed.', [
                'error_reference' => $reference,
                'route' => $request->route()?->uri(),
                'method' => $request->method(),
                'user_id' => $request->user()?->id,
                'plant_id' => $request->attributes->get('mobile_plant_id'),
                'exception' => $e,
            ]);
            return response()->json(['message' => 'Server error.', 'error_reference' => $reference], 500);
        });

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
            if (($request->is('api/mobile/*') || $request->expectsJson()) && ! $request->inertia()) {
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

            if ($request->is('api/mobile/*') || $request->expectsJson()) {
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
                if (isset($decoded['error_reference']) && \Illuminate\Support\Str::isUuid($decoded['error_reference'])) {
                    $payload['message'] = $decoded['message'] ?? 'Server error.';
                    $payload['error_reference'] = $decoded['error_reference'];
                }
                if (in_array($decoded['error_code'] ?? null, ['WHATSAPP_TLS_ERROR', 'WHATSAPP_CONNECTION_ERROR', 'WHATSAPP_CONFIG_ERROR', 'WHATSAPP_SEND_FAILED'], true)) {
                    $payload['message'] = $decoded['message'];
                    $payload['error_code'] = $decoded['error_code'];
                }
                if ($status === 503 && ($decoded['message'] ?? null) === 'Reverb is not configured.') {
                    $payload['message'] = $decoded['message'];
                }
                if ($status >= 500 && !isset($payload['error_reference']) && !isset($payload['error_code'])
                    && $payload['message'] === 'Server error.') {
                    $payload['error_reference'] = (string) \Illuminate\Support\Str::uuid();
                    \Illuminate\Support\Facades\Log::error('Mobile API error response.', [
                        'error_reference' => $payload['error_reference'],
                        'route' => request()->route()?->uri(),
                        'method' => request()->method(),
                        'status' => $status,
                        'response_message' => $decoded['message'] ?? null,
                    ]);
                }
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
