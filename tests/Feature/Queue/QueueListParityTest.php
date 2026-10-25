<?php

namespace Tests\Feature\Queue;

use Tests\TestCase;

/**
 * Local Compose must consume the same queues as production.
 *
 * The application splits work across named queues: mail notifications are
 * dispatched to `notifications` through each notification's viaQueues(), and
 * the production supervisor worker listens to `default,notifications,heavy`.
 * A Compose listener that names no queue consumes only `default`, so in local
 * development every queued mail notification is accepted by Redis/database and
 * never processed — no exception, no failed job, the email just never arrives.
 *
 * These assertions read the shipped process definitions, the same way
 * QueueTimeoutPolicyTest pins the timeout chain.
 */
class QueueListParityTest extends TestCase
{
    /**
     * @return list<string> queue names in the order the command lists them
     */
    private function queueNames(string $command): array
    {
        preg_match('/--queue=([A-Za-z0-9_,\-]+)/', $command, $matches);

        $this->assertNotEmpty(
            $matches,
            'Queue worker command must name its queues explicitly: '.$command
        );

        return array_values(array_filter(explode(',', $matches[1])));
    }

    private function composeQueueCommand(): string
    {
        $compose = file_get_contents(base_path('docker-compose.yml'));

        // The `queue:` service block only; stop at the next top-level service.
        preg_match('/^ {2}queue:\n(.*?)(?=^ {2}\w)/ms', $compose, $matches);

        $this->assertNotEmpty($matches, 'docker-compose.yml must define a queue service');

        preg_match('/command:\s*(.+)/', $matches[1], $command);

        $this->assertNotEmpty($command, 'The compose queue service must define a command');

        return trim($command[1]);
    }

    private function supervisordQueueCommand(): string
    {
        $supervisord = file_get_contents(base_path('docker/supervisord.conf'));

        preg_match('/^command=.*queue:(?:work|listen)[^\\\\\']*/m', $supervisord, $matches);

        $this->assertNotEmpty($matches, 'supervisord must define the queue worker command');

        return $matches[0];
    }

    public function test_compose_listens_to_every_production_queue(): void
    {
        $this->assertSame(
            $this->queueNames($this->supervisordQueueCommand()),
            $this->queueNames($this->composeQueueCommand()),
            'Compose must listen to the same queues, in the same priority order, as production'
        );
    }

    public function test_compose_covers_the_queues_the_application_dispatches_to(): void
    {
        $queues = $this->queueNames($this->composeQueueCommand());

        // Every notification routes its mail through viaQueues(). Collect the
        // queue names the code actually uses rather than hard-coding a list, so
        // a new queue added to a notification cannot go unserved locally.
        $dispatched = [];
        foreach (glob(app_path('Notifications/*.php')) as $file) {
            $source = file_get_contents($file);
            if (preg_match('/viaQueues\(\)[\s\S]*?\];/', $source, $block)) {
                preg_match_all("/'[a-z_]+'\s*=>\s*'([a-z_]+)'/", $block[0], $names);
                $dispatched = array_merge($dispatched, $names[1]);
            }
        }

        $this->assertContains('notifications', array_unique($dispatched), 'A notification should use the notifications queue');

        foreach (array_unique($dispatched) as $queue) {
            $this->assertContains(
                $queue,
                $queues,
                "Compose queue worker must consume the '{$queue}' queue or its jobs never run locally"
            );
        }
    }

    public function test_existing_queue_options_are_unchanged(): void
    {
        $command = $this->composeQueueCommand();

        $this->assertStringContainsString('queue:listen', $command);
        $this->assertStringContainsString('--tries=3', $command);
        $this->assertStringContainsString('--timeout=320', $command);
    }
}
