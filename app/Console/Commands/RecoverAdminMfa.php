<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class RecoverAdminMfa extends Command
{
    protected $signature = 'mfa:recover-admin {--target=} {--operator=} {--reason=} {--confirm=}';

    protected $description = 'Reset MFA enrollment for one administrator after explicit operator confirmation';

    public function handle(UserService $users): int
    {
        $target = $this->option('target');
        $operator = trim((string) $this->option('operator'));
        $reason = trim((string) $this->option('reason'));

        if (! is_string($target) || trim($target) === '' || $operator === '' || $reason === '' || strlen($reason) > 500
            || $this->option('confirm') !== 'RECOVER') {
            $this->error('Provide --target, --operator, --reason, and --confirm=RECOVER. No changes made.');

            return self::FAILURE;
        }

        $user = User::withTrashed()->where(function ($query) use ($target): void {
            $query->where('email', trim($target));
            if (Str::isUuid(trim($target))) {
                $query->orWhere('id', trim($target));
            }
        })->where('role', 'ADMIN')->where('is_active', true)->where('is_deleted', false)->first();

        if ($user === null) {
            $this->error('Target must be one existing active administrator. No changes made.');

            return self::FAILURE;
        }

        $users->resetMfa($user, null, $reason, $operator);

        $this->info('MFA enrollment was reset. Target must enroll again before protected access.');

        return self::SUCCESS;
    }
}
