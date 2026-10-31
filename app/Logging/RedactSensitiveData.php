<?php

namespace App\Logging;

use Monolog\LogRecord;

/**
 * Monolog processor that blanks personal and credential values in log
 * `context` and `extra` before the record reaches disk.
 *
 * Encrypted model columns protect the database, not the log file: `context`
 * arrays routinely carry request bodies, emails and tokens, and the app log
 * keeps 14 days of them.
 *
 * Known limit: this matches KEYS, not free text. An exception message — which
 * Monolog puts in the record's own `message` field, not in `context` — passes
 * through untouched, and so does any sensitive string inside a non-listed key
 * (e.g. an `exception` value). Callers that build a message from outside data
 * must keep that data out of it. Redacting arbitrary text with patterns is not
 * attempted: it cannot be made reliable and invites false confidence.
 */
class RedactSensitiveData
{
    /**
     * @var list<string>
     */
    private const REDACTED_KEYS = [
        'password', 'password_confirmation', 'current_password', 'token',
        'remember_token', 'mfa_secret', 'mfa_recovery_codes', 'authorization',
        'body', 'email', 'contact_number',
    ];

    private const PLACEHOLDER = '[redacted]';

    public function __invoke(LogRecord $record): LogRecord
    {
        // LogRecord::$context and $extra are readonly: rebuild the record.
        return $record->with(
            context: $this->redact($record->context),
            extra: $this->redact($record->extra),
        );
    }

    private function redact(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            if (is_string($key) && in_array(strtolower($key), self::REDACTED_KEYS, true)) {
                $value[$key] = self::PLACEHOLDER;

                continue;
            }

            $value[$key] = $this->redact($item);
        }

        return $value;
    }
}
