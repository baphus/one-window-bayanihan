<?php

namespace Tests\Feature\Queue;

use App\Jobs\GenerateSystemReport;
use Tests\TestCase;

/**
 * The queue timeout chain has three links that must stay ordered:
 *
 *   job $timeout  <  worker --timeout  <  connection retry_after
 *
 * Break the first link and the worker kills a job that was still inside its
 * own budget; break the second and the queue re-delivers a job the worker is
 * still running, so the same export executes twice concurrently.
 */
class QueueTimeoutPolicyTest extends TestCase
{
    private function connectionRetryAfter(string $connection): int
    {
        return (int) config("queue.connections.{$connection}.retry_after");
    }

    /**
     * Every worker invocation the deployment ships — supervisord's queue-worker
     * program and the dedicated compose service — must use the same timeout.
     *
     * @return array<string, int>
     */
    private function workerTimeouts(): array
    {
        $root = base_path();
        $sources = [
            'docker/supervisord.conf' => file_get_contents($root.'/docker/supervisord.conf'),
            'docker-compose.yml' => file_get_contents($root.'/docker-compose.yml'),
        ];

        $timeouts = [];
        foreach ($sources as $file => $contents) {
            // Anchored on the queue commands so an unrelated flag (e.g. the
            // Dockerfile healthcheck's --timeout=3s) can never satisfy this.
            preg_match_all('/queue:(?:work|listen)[^"\']*?--timeout=(\d+)/', $contents, $matches);
            $this->assertNotEmpty($matches[1], "{$file} must pin the queue worker timeout explicitly");

            foreach ($matches[1] as $value) {
                $timeouts[$file] = (int) $value;
            }
        }

        return $timeouts;
    }

    public function test_worker_timeout_leaves_room_for_the_longest_job(): void
    {
        $jobTimeout = (new GenerateSystemReport('00000000-0000-0000-0000-000000000000', 'system_report_pdf'))->timeout;

        $this->assertSame(300, $jobTimeout, 'GenerateSystemReport is the longest job; update this test if that changes.');

        foreach ($this->workerTimeouts() as $file => $workerTimeout) {
            $this->assertGreaterThan(
                $jobTimeout,
                $workerTimeout,
                "{$file} worker timeout must exceed the longest job timeout so the job can finish its attempt"
            );
        }
    }

    public function test_retry_after_exceeds_the_worker_timeout(): void
    {
        $workerTimeout = max($this->workerTimeouts());

        foreach (['database', 'redis', 'beanstalkd'] as $connection) {
            $this->assertGreaterThan(
                $workerTimeout,
                $this->connectionRetryAfter($connection),
                "queue.connections.{$connection}.retry_after must exceed the worker timeout or the job is re-delivered mid-run"
            );
        }
    }

    public function test_supervisor_stop_wait_outlives_the_worker_timeout(): void
    {
        $configuration = file_get_contents(base_path('/docker/supervisord.conf'));

        preg_match('/stopwaitsecs=(\d+)/', $configuration, $stopWait);
        preg_match('/queue:(?:work|listen)[^"\']*?--timeout=(\d+)/', $configuration, $workerTimeout);

        $this->assertGreaterThan(
            (int) $workerTimeout[1],
            (int) $stopWait[1],
            'stopwaitsecs must outlive the worker timeout so a redeploy does not SIGKILL an in-flight job'
        );
    }
}
