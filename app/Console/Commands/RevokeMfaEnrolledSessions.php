<?php

namespace App\Console\Commands;

use App\Enums\AuditModule;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RevokeMfaEnrolledSessions extends Command
{
    protected $signature = 'mfa:revoke-enrolled-sessions {--force : Confirm revocation without an interactive prompt}';

    protected $description = 'Delete database sessions belonging to currently MFA-enrolled users';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Revoke all sessions for currently MFA-enrolled users?')) {
            $this->warn('No sessions were revoked.');

            return self::SUCCESS;
        }

        $count = DB::table('sessions')
            ->whereIn('user_id', DB::table('users')->whereNotNull('mfa_enabled_at')->select('id'))
            ->delete();

        AuditLogger::revokeSessions()
            ->byId(null)
            ->module(AuditModule::SESSION)
            ->context(null, ['count' => $count])
            ->write();

        $this->info("Revoked {$count} database session(s).");

        return self::SUCCESS;
    }
}
