<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SessionService
{
    public function getSessions(): array
    {
        try {
            $sessions = DB::table('sessions')
                ->orderBy('last_activity', 'desc')
                ->get();

            return $sessions->map(function ($session) {
                $user = null;

                if ($session->user_id) {
                    $user = User::find($session->user_id);
                }

                return [
                    'id' => $session->id,
                    'user_name' => $user?->name ?? 'Guest',
                    'user_email' => $user?->email ?? 'N/A',
                    'user_id' => $session->user_id,
                    'ip_address' => $session->ip_address,
                    'user_agent' => $session->user_agent,
                    'last_activity' => date('Y-m-d H:i:s', $session->last_activity),
                    'is_current' => session()->getId() === $session->id,
                ];
            })->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    public function terminate(string $sessionId): void
    {
        if (config('session.driver') === 'redis' || session()->getId() === $sessionId) {
            return;
        }

        // NOTE: the raw session id is deliberately NOT used as entity_id —
        // sessions.id is an opaque string (not a UUID) while
        // audit_logs.entity_id is UUID-typed, so it would fail the DB cast.
        // The logger falls back to Auth::id() when entity is null.
        SecurityAuditLogger::log(
            'session',
            sprintf('User session terminated (…%s)', substr($sessionId, -6)),
            null,
            AuditAction::DELETE->value,
        );

        DB::table('sessions')->where('id', $sessionId)->delete();
    }
}
