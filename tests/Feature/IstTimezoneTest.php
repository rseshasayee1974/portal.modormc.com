<?php
/*
Author: Cipher
Created: 2026-11-06 12:26:00 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Helpers\DateTimeHelper;
use App\Models\Entity;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\TestCase;

class IstTimezoneTest extends TestCase
{
    public function test_business_timezone_is_always_ist(): void
    {
        $this->assertSame('Asia/Kolkata', config('app.timezone'));
        $this->assertSame('+05:30', config('database.connections.mysql.timezone'));
        $this->assertSame('+05:30', config('database.connections.mariadb.timezone'));
        $this->assertSame('Asia/Kolkata', DateTimeHelper::getEntityTimezone(999));
        $this->assertSame('Asia/Kolkata', DateTimeHelper::timezoneForPlant(999));
        $this->assertSame('Asia/Kolkata', DateTimeHelper::validTimezone('America/New_York'));
        $this->assertSame('Asia/Kolkata', (new Entity(['time_zone' => 'America/New_York']))->time_zone);
    }

    public function test_jobs_and_requests_use_ist_and_restore_process_state_on_errors(): void
    {
        $previous = date_default_timezone_get();
        $previousConfig = config('app.timezone');
        try {
            date_default_timezone_set('America/Los_Angeles');
            config(['app.timezone' => 'America/Los_Angeles']);
            DateTimeHelper::inTimezone('UTC', function () {
                $this->assertSame('Asia/Kolkata', date_default_timezone_get());
                $this->assertSame('Asia/Kolkata', config('app.timezone'));
                $this->assertSame('2026-11-07 00:00:00', Carbon::parse('2026-11-06T18:30:00Z')->setTimezone(config('app.timezone'))->toDateTimeString());
                throw new \RuntimeException('Test restoration');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('Test restoration', $exception->getMessage());
            $this->assertSame('America/Los_Angeles', date_default_timezone_get());
            $this->assertSame('America/Los_Angeles', config('app.timezone'));
        } finally {
            date_default_timezone_set($previous);
            config(['app.timezone' => $previousConfig]);
        }
    }
}
