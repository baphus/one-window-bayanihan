<?php

namespace App\Services\Mail;

/**
 * Maps provider delivery events onto email_logs.status, and decides when an
 * incoming event is allowed to change it.
 *
 * Webhook delivery is not ordered and retries can arrive long after later
 * events, so statuses carry a rank and only ever advance. Without that, a
 * retried "email.sent" landing after "email.bounced" would report a bounced
 * message as successfully sent — the exact failure this tracking exists to
 * prevent.
 */
class DeliveryStatus
{
    /**
     * Status precedence. Terminal outcomes outrank informational ones so a
     * bounce or complaint is never overwritten by a delivery notification.
     *
     * @var array<string, int>
     */
    public const RANKS = [
        'sent' => 1,
        'delayed' => 2,
        'delivered' => 3,
        'suppressed' => 4,
        'failed' => 5,
        'bounced' => 6,
        'complained' => 7,
    ];

    /**
     * Provider event types that set a delivery status.
     *
     * @var array<string, string>
     */
    public const EVENT_STATUSES = [
        'email.sent' => 'sent',
        'email.scheduled' => 'sent',
        'email.delivery_delayed' => 'delayed',
        'email.delivered' => 'delivered',
        'email.suppressed' => 'suppressed',
        'email.failed' => 'failed',
        'email.bounced' => 'bounced',
        'email.complained' => 'complained',
    ];

    /**
     * Event types worth recording that deliberately set no status.
     *
     * Open and click tracking depends on remote image loading and link
     * rewriting, so it is unreliable as a delivery signal — but it is useful
     * evidence when a recipient disputes receiving a message.
     *
     * @var list<string>
     */
    public const RECORDED_ONLY_EVENTS = [
        'email.opened',
        'email.clicked',
        'email.received',
    ];

    /**
     * Statuses that indicate the message did not reach the recipient.
     *
     * @var list<string>
     */
    public const PROBLEM_STATUSES = ['failed', 'bounced', 'complained', 'suppressed'];

    /**
     * The status an event maps to, or null if the event sets no status.
     */
    public static function fromEvent(string $eventType): ?string
    {
        return self::EVENT_STATUSES[$eventType] ?? null;
    }

    /**
     * Whether this event type is one we store at all.
     */
    public static function isRecorded(string $eventType): bool
    {
        return isset(self::EVENT_STATUSES[$eventType])
            || in_array($eventType, self::RECORDED_ONLY_EVENTS, true);
    }

    /**
     * Whether $candidate should replace $current.
     *
     * Unknown or missing current statuses rank 0, so any known status advances
     * past them.
     */
    public static function outranks(string $candidate, ?string $current): bool
    {
        return self::rank($candidate) > self::rank($current);
    }

    public static function rank(?string $status): int
    {
        if ($status === null) {
            return 0;
        }

        return self::RANKS[$status] ?? 0;
    }

    /**
     * Every status a log row may hold, for filter whitelists.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(self::RANKS);
    }
}
