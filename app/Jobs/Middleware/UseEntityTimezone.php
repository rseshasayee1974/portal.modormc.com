<?php

namespace App\Jobs\Middleware;

use App\Helpers\DateTimeHelper;

class UseEntityTimezone
{
    public function __construct(public ?int $plantId) {}

    public function handle(object $job, callable $next): mixed
    {
        return DateTimeHelper::inTimezone(
            DateTimeHelper::timezoneForPlant($this->plantId),
            fn () => $next($job)
        );
    }
}
