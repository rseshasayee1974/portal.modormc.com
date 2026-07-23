<?php

namespace App\Traits;

trait TracksModelChanges
{
    public static function bootTracksModelChanges(): void
    {
    }

    public function getAuditChanges(array $ignoredFields = []): array
    {
        return [];
    }

    public function getAuditRemarkString(): string
    {
        return '';
    }

    protected function normalizeAuditValue(mixed $value): string
    {
        return '';
    }
}
