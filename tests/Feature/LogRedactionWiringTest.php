<?php

namespace Tests\Feature;

use App\Logging\RedactSensitiveDataTap;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Every channel that persists log records must carry the redaction tap. A
 * stack that points at an untapped channel would silently log personal data
 * again, so this pins the wiring rather than the class.
 */
class LogRedactionWiringTest extends TestCase
{
    #[Test]
    public function every_file_logging_channel_redacts_sensitive_values(): void
    {
        $checked = 0;

        foreach (config('logging.channels') as $name => $channel) {
            if (! in_array($channel['driver'] ?? null, ['single', 'daily'], true)) {
                continue;
            }

            $this->assertContains(
                RedactSensitiveDataTap::class,
                $channel['tap'] ?? [],
                "Log channel '{$name}' writes files without the redaction tap.",
            );
            $checked++;
        }

        $this->assertGreaterThan(0, $checked);
    }

    #[Test]
    public function file_channels_resolve_and_write_redacted_context(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'log-redaction-');
        $this->assertNotFalse($path);

        try {
            foreach (['single', 'daily', 'chatbot'] as $name) {
                config(["logging.channels.{$name}.path" => $path, "logging.channels.{$name}.level" => 'debug']);
                Log::forgetChannel($name);
                $logger = Log::channel($name)->getLogger();
                $output = $logger->getHandlers()[0]->getUrl();

                try {
                    $logger->warning('probe', ['email' => 'private@example.test']);
                    $contents = file_get_contents($output);
                    $this->assertStringContainsString('[redacted]', $contents);
                    $this->assertStringNotContainsString('private@example.test', $contents);
                } finally {
                    $logger->close();
                    Log::forgetChannel($name);
                    if (is_file($output)) {
                        unlink($output);
                    }
                }
            }
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function the_stack_channel_used_in_production_only_lists_file_channels_that_redact(): void
    {
        $stack = config('logging.channels.stack');
        $this->assertSame('stack', $stack['driver']);

        foreach ($stack['channels'] as $channel) {
            $driver = config("logging.channels.{$channel}.driver");
            if (in_array($driver, ['single', 'daily'], true)) {
                $this->assertContains(RedactSensitiveDataTap::class, config("logging.channels.{$channel}.tap"));
            }
        }
    }
}
