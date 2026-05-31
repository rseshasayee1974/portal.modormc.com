<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Artisan;

Schedule::command('plants:monitor')->everyMinute();
Schedule::command('website:monitor modormc.com')->everyFiveMinutes();
Schedule::command('trace-replay:prune --days=30')->daily();
Schedule::command('fleet:check-maintenance')->daily();
Schedule::command('reports:send-scheduled')->everyMinute();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
