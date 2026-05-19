<?php

namespace App\Services\Audit;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuditLogger
{
    private bool $logging = false;

    public function __construct(private readonly AuditContext $context)
    {
    }

    public function isEnabled(): bool
    {
        return (bool) config('audit.enabled', true);
    }

    public function shouldAuditModel(mixed $model): bool
    {
        if (!$this->isEnabled() || !$model instanceof Model) {
            return false;
        }

        if ($model instanceof ActivityLog) {
            return false;
        }

        return !in_array($model::class, config('audit.ignored_models', []), true);
    }

    public function snapshot(Model $model, ?array $attributes = null): array
    {
        $attributes ??= $model->getAttributes();

        $data = [];
        foreach ($attributes as $key => $value) {
            if ($this->shouldIgnoreField($key)) {
                continue;
            }

            $data[$key] = $this->normalizeValue($value);
        }

        ksort($data);

        return $data;
    }

    public function diff(Model $model): array
    {
        $dirty = $model->getDirty();
        $passwordChanged = array_key_exists('password', $dirty);
        $oldValues = [];
        $newValues = [];

        foreach ($dirty as $field => $value) {
            if ($this->shouldIgnoreField($field) || $field === 'password') {
                continue;
            }

            $old = $this->normalizeValue($model->getOriginal($field));
            $new = $this->normalizeValue($value);

            if ($old === $new) {
                continue;
            }

            $oldValues[$field] = $old;
            $newValues[$field] = $new;
        }

        return [
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'changed_fields' => array_values(array_keys($newValues)),
            'password_changed' => $passwordChanged,
        ];
    }

    public function log(string $actionType, ?Model $model = null, array $payload = []): ?ActivityLog
    {
        if (!$this->isEnabled() || $this->logging) {
            return null;
        }

        if ($model && !$this->shouldAuditModel($model)) {
            return null;
        }

        $context = $this->context->current(
            request(),
            $payload['response_status'] ?? null
        );

        $attributes = [
            'user_id' => $payload['user_id'] ?? $context['user_id'] ?? null,
            'plant_id' => $payload['plant_id'] ?? $this->resolvePlantId($model, $context),
            'module_name' => $payload['module_name'] ?? $this->resolveModuleName($model),
            'entity_type' => $payload['entity_type'] ?? ($model ? $model::class : null),
            'entity_id' => $payload['entity_id'] ?? ($model?->getKey() !== null ? (string) $model->getKey() : null),
            'action_type' => Str::upper($actionType),
            'old_values' => $this->normalizeArrayPayload($payload['old_values'] ?? null),
            'new_values' => $this->normalizeArrayPayload($payload['new_values'] ?? null),
            'changed_fields' => $this->normalizeChangedFields($payload['changed_fields'] ?? null),
            'description' => $payload['description'] ?? $this->defaultDescription(Str::upper($actionType), $model),
            'ip_address' => $payload['ip_address'] ?? $context['ip_address'],
            'user_agent' => $payload['user_agent'] ?? $context['user_agent'],
            'device_type' => $payload['device_type'] ?? $context['device_type'],
            'browser' => $payload['browser'] ?? $context['browser'],
            'operating_system' => $payload['operating_system'] ?? $context['operating_system'],
            'request_method' => $payload['request_method'] ?? $context['request_method'],
            'request_url' => $payload['request_url'] ?? $context['request_url'],
            'route_name' => $payload['route_name'] ?? $context['route_name'],
            'response_status' => $payload['response_status'] ?? $context['response_status'],
            'trace_id' => $payload['trace_id'] ?? $context['trace_id'],
            'metadata' => $this->normalizeArrayPayload($payload['metadata'] ?? []),
            'created_at' => $payload['created_at'] ?? now(),
        ];

        if (!$attributes['description']) {
            $attributes['description'] = Str::headline($attributes['action_type']).' event';
        }

        $this->logging = true;

        try {
            return ActivityLog::create($attributes);
        } finally {
            $this->logging = false;
        }
    }

    public function logRequest(Response $response): ?ActivityLog
    {
        if (!$this->isEnabled() || !app()->bound('request')) {
            return null;
        }

        $request = request();

        if ($request->attributes->get('_audit_request_logged')) {
            return null;
        }

        $routeName = $request->route()?->getName();
        foreach (config('audit.ignored_routes', []) as $ignoredRoute) {
            if ($routeName && Str::is($ignoredRoute, $routeName)) {
                return null;
            }
        }

        $actionType = $this->detectRequestAction($response);
        if (!$actionType) {
            return null;
        }

        $request->attributes->set('_audit_request_logged', true);

        $startedAt = (float) $request->attributes->get('audit_started_at', microtime(true));
        $durationMs = round((microtime(true) - $startedAt) * 1000, 2);

        return $this->log($actionType, Auth::user(), [
            'module_name' => $this->resolveRequestModuleName(),
            'entity_type' => null,
            'entity_id' => null,
            'description' => $this->requestDescription($actionType),
            'response_status' => $response->getStatusCode(),
            'metadata' => [
                'request_input' => $this->sanitizeRequestPayload(),
                'file_fields' => array_keys(request()->allFiles()),
                'duration_ms' => $durationMs,
            ],
        ]);
    }

    public function logAuthEvent(string $actionType, mixed $user, array $metadata = []): ?ActivityLog
    {
        if (!$user instanceof Model) {
            return null;
        }

        return $this->log($actionType, $user, [
            'module_name' => 'auth',
            'description' => sprintf('%s for %s', Str::headline(strtolower($actionType)), $user->email ?? $user->username ?? ('user #'.$user->getKey())),
            'metadata' => $metadata,
        ]);
    }

    private function detectRequestAction(Response $response): ?string
    {
        $request = request();
        $routeName = strtolower((string) $request->route()?->getName());
        $path = strtolower($request->path());
        $disposition = strtolower((string) $response->headers->get('content-disposition'));

        if ($response->getStatusCode() >= 500) {
            return 'SYSTEM_EVENT';
        }

        if ($request->is('api/*')) {
            if (Str::contains($path, ['login', 'logout'])) {
                return null;
            }

            return 'API_CALL';
        }

        if (!empty($request->allFiles())) {
            if (Str::contains($routeName, 'import') || Str::contains($path, 'import')) {
                return 'IMPORT';
            }

            return 'UPLOAD';
        }

        if (Str::contains($disposition, 'attachment')) {
            if (Str::contains($routeName, 'export') || Str::contains($path, 'export')) {
                return 'EXPORT';
            }

            return 'DOWNLOAD';
        }

        return null;
    }

    private function resolvePlantId(?Model $model, array $context): ?int
    {
        $plantId = $model?->getAttribute('plant_id')
            ?? request()?->input('plant_id')
            ?? $context['plant_id']
            ?? null;

        return $plantId !== null ? (int) $plantId : null;
    }

    private function resolveModuleName(?Model $model): string
    {
        if (!$model) {
            return $this->resolveRequestModuleName();
        }

        if (method_exists($model, 'auditModuleName')) {
            return (string) $model->auditModuleName();
        }

        return Str::snake(class_basename($model));
    }

    private function resolveRequestModuleName(): string
    {
        $routeName = request()?->route()?->getName();

        if ($routeName) {
            return Str::snake((string) Str::before($routeName, '.'));
        }

        $segment = request()?->segment(1);

        return $segment ? Str::snake($segment) : 'system';
    }

    private function defaultDescription(string $actionType, ?Model $model): string
    {
        if (!$model) {
            return Str::headline(strtolower($actionType)).' event';
        }

        return sprintf(
            '%s %s #%s',
            Str::headline(strtolower($actionType)),
            class_basename($model),
            (string) $model->getKey()
        );
    }

    private function requestDescription(string $actionType): string
    {
        $routeName = request()?->route()?->getName();
        $target = $routeName ?: request()?->path() ?: 'request';

        return sprintf(
            '%s %s %s',
            Str::headline(strtolower($actionType)),
            request()?->method() ?? 'REQUEST',
            $target
        );
    }

    private function shouldIgnoreField(string $field): bool
    {
        return in_array($field, config('audit.ignored_fields', []), true);
    }

    private function normalizeChangedFields(mixed $fields): ?array
    {
        if (!$fields) {
            return null;
        }

        return array_values(array_map(static fn ($field) => (string) $field, (array) $fields));
    }

    private function normalizeArrayPayload(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        $normalized = $this->normalizeValue($value);

        if ($normalized === null) {
            return null;
        }

        return is_array($normalized) ? $normalized : ['value' => $normalized];
    }

    private function sanitizeRequestPayload(): array
    {
        $payload = request()->except([
            'password',
            'password_confirmation',
            'current_password',
            'token',
            'api_key',
        ]);

        return (array) $this->normalizeValue($payload);
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof Carbon) {
            return $value->toIso8601String();
        }

        if ($value instanceof UploadedFile) {
            return [
                'name' => $value->getClientOriginalName(),
                'size' => $value->getSize(),
                'mime' => $value->getClientMimeType(),
            ];
        }

        if ($value instanceof Model) {
            return [
                'id' => $value->getKey(),
                'type' => $value::class,
            ];
        }

        if ($value instanceof Collection) {
            return $value->map(fn ($item) => $this->normalizeValue($item))->all();
        }

        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                $normalized[$key] = $this->normalizeValue($item);
            }

            return $normalized;
        }

        if (is_object($value) && method_exists($value, 'toArray')) {
            return $this->normalizeValue($value->toArray());
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        return $value;
    }
}
