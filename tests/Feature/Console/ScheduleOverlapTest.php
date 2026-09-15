<?php

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class ScheduleOverlapTest extends TestCase
{
    public function test_schedules_have_overlap_protection(): void
    {
        $events = collect(app(Schedule::class)->events());

        foreach (['logs:cleanup', 'storage:cleanup-orphans'] as $command) {
            $event = $events->first(fn ($event): bool => str_contains((string) $event->command, " {$command}"));

            $this->assertNotNull($event, "Schedule for {$command} exists.");
            $this->assertTrue($event->withoutOverlapping, "Schedule for {$command} has overlap protection.");
        }
    }
}
