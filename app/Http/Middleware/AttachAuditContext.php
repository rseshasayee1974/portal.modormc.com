<?php

namespace App\Http\Middleware;

use App\Services\Audit\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AttachAuditContext
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $traceId = $request->headers->get('X-Trace-Id') ?: (string) Str::uuid();

        $request->attributes->set('audit_trace_id', $traceId);
        $request->attributes->set('audit_started_at', microtime(true));

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Trace-Id', $traceId);

        $this->auditLogger->logRequest($response);

        return $response;
    }
}
