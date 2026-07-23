<?php

namespace App\Services\Audit;

class AuditLogger
{
    /**
     * No-op audit logger.
     */
    public function log(string $actionType, mixed $entity = null, array $details = []): mixed
    {
        return null;
    }
}
