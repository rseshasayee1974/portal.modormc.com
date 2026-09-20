<?php

namespace App\Console\Commands;

use App\Services\UserPresenceService;
use Illuminate\Console\Command;

class ExpireUserPresence extends Command
{
    protected $signature = 'users:expire-presence {--watch : Run cleanup every minute for local development}';
    protected $description = 'Mark users offline when their browser presence expires';

    public function handle(UserPresenceService $presence): int
    {
        do {
            $this->info('Refreshed presence for '.$presence->expire().' users.');
            if ($this->option('watch')) sleep(60);
        } while ($this->option('watch'));
        return self::SUCCESS;
    }
}
