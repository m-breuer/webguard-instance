<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class SchedulingTest extends TestCase
{
    public function test_monitoring_commands_are_scheduled_correctly()
    {
        $schedule = $this->app->make(Schedule::class);

        $responseEvent = collect($schedule->events())->first(function ($event) {
            return str_contains($event->command, 'monitoring:response');
        });

        $sslEvent = collect($schedule->events())->first(function ($event) {
            return str_contains($event->command, 'monitoring:ssl');
        });

        $this->assertNotNull($responseEvent, 'Response monitoring command is not scheduled.');
        $this->assertEquals('*/5 * * * *', $responseEvent->expression);

        $this->assertNotNull($sslEvent, 'SSL monitoring command is not scheduled.');
        $this->assertEquals('*/5 * * * *', $sslEvent->expression);
    }
}
