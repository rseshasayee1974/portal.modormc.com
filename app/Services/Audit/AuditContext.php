<?php

namespace App\Services\Audit;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\PlantContextService;

class AuditContext
{
    public function current(?Request $request = null, ?int $responseStatus = null): array
    {
        $request ??= app()->bound('request') ? request() : null;
        $userAgent = $request?->userAgent();

        return [
            'user_id' => Auth::id(),
            'plant_id' => app(PlantContextService::class)->plantId(),
            'ip_address' => $request?->ip(),
            'user_agent' => $userAgent,
            'device_type' => $this->detectDeviceType($userAgent),
            'browser' => $this->detectBrowser($userAgent),
            'operating_system' => $this->detectOperatingSystem($userAgent),
            'request_method' => $request?->method(),
            'request_url' => $request?->fullUrl(),
            'route_name' => $request?->route()?->getName(),
            'response_status' => $responseStatus,
            'trace_id' => $request?->attributes->get('audit_trace_id')
                ?: $request?->headers->get('X-Trace-Id'),
        ];
    }

    private function detectDeviceType(?string $userAgent): ?string
    {
        if (!$userAgent) {
            return null;
        }

        $value = strtolower($userAgent);

        if (str_contains($value, 'tablet') || str_contains($value, 'ipad')) {
            return 'tablet';
        }

        if (str_contains($value, 'mobile') || str_contains($value, 'android')) {
            return 'mobile';
        }

        if (str_contains($value, 'postman') || str_contains($value, 'insomnia') || str_contains($value, 'curl')) {
            return 'api-client';
        }

        return 'desktop';
    }

    private function detectBrowser(?string $userAgent): ?string
    {
        if (!$userAgent) {
            return null;
        }

        return match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'OPR/') || str_contains($userAgent, 'Opera') => 'Opera',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Safari/') => 'Safari',
            str_contains($userAgent, 'PostmanRuntime') => 'Postman',
            str_contains($userAgent, 'Insomnia') => 'Insomnia',
            str_contains($userAgent, 'curl/') => 'cURL',
            default => 'Unknown',
        };
    }

    private function detectOperatingSystem(?string $userAgent): ?string
    {
        if (!$userAgent) {
            return null;
        }

        return match (true) {
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac OS X') || str_contains($userAgent, 'Macintosh') => 'macOS',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'Unknown',
        };
    }
}
