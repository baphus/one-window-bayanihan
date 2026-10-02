<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SessionService
{
    public function getSessions(): array
    {
        try {
            $sessions = DB::table('sessions')
                ->whereNotNull('user_id')
                ->orderBy('last_activity', 'desc')
                ->get();

            $users = User::whereIn('id', $sessions->pluck('user_id')->unique()->values()->all())
                ->get(['id', 'name', 'email'])
                ->keyBy('id');

            return $sessions->map(function ($session) use ($users) {
                $user = $users->get($session->user_id);

                if (! $user) {
                    return null;
                }

                return [
                    'id' => $session->id,
                    'user_name' => $user->name,
                    'user_email' => $user->email,
                    'user_id' => $session->user_id,
                    'ip_address' => $session->ip_address,
                    'user_agent' => $session->user_agent,
                    'last_activity' => date('Y-m-d H:i:s', $session->last_activity),
                    'is_current' => session()->getId() === $session->id,
                ];
            })->filter()->values()->toArray();
        } catch (\Exception $e) {
            Log::warning('SessionService: failed to load sessions', ['exception' => $e->getMessage()]);

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
