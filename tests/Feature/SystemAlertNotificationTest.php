<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SystemAlertNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use ReflectionClass;
use Tests\TestCase;

class SystemAlertNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_renders_mail_correctly(): void
    {
        $notification = new SystemAlertNotification('database_down', 'critical', 'Database connection failed');
        $user = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'role' => 'ADMIN',
        ]);

        $mail = $notification->toMail($user);

        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame('System Alert: critical - database_down', $this->readProperty($mail, 'subject'));
        $this->assertSame([
            'Type: database_down',
            'Severity: critical',
            'Message: Database connection failed',
        ], $this->readProperty($mail, 'introLines'));
        $this->assertSame([
            'This is an automated system alert.',
        ], $this->readProperty($mail, 'outroLines'));
        $this->assertSame('View Dashboard', $this->readProperty($mail, 'actionText'));
        $this->assertSame(url('/admin/system/health'), $this->readProperty($mail, 'actionUrl'));
    }

    public function test_notification_stores_database_array(): void
    {
        $notification = new SystemAlertNotification('cpu_high', 'warning', 'CPU usage above threshold');
        $user = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'role' => 'ADMIN',
        ]);

        $this->assertSame([
            'alert_type' => 'cpu_high',
            'severity' => 'warning',
            'message' => 'CPU usage above threshold',
        ], $notification->toArray($user));
    }

    private function readProperty(object $object, string $property): mixed
    {
        $reflection = new ReflectionClass($object);
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);

        return $prop->getValue($object);
    }
}
