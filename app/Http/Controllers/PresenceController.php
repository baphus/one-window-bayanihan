<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class PresenceController extends Controller
{
    private const TTL = 60;

    public function case(string $case): JsonResponse
    {
        $user = request()->user();
        $record = CaseFile::query()->findOrFail($case);
        abort_unless($user->isAdmin() || $user->isCaseManager() || $user->isAgency(), 403);
        if (! $user->isAdmin() && ! $user->isCaseManager()) {
            abort_unless($user->agcy_id && $record->referrals()->where('agcy_id', $user->agcy_id)->whereNotIn('status', ['COMPLETED', 'REJECTED'])->exists(), 403);
        }

        return $this->heartbeat('case:'.$record->getKey(), $user);
    }

    public function referral(Referral $referral): JsonResponse
    {
        $user = request()->user();
        abort_unless(($user->isAgency() || $user->isAdmin() || $user->isCaseManager()) && $user->agcy_id && $user->agcy_id === $referral->agcy_id, 403);

        return $this->heartbeat('referral:'.$referral->getKey(), $user, $referral->agcy_id);
    }

    private function heartbeat(string $resource, User $user, ?string $agencyId = null): JsonResponse
    {
        $key = 'presence:'.$resource;
        $viewers = Cache::lock($key.':lock', 5)->block(2, function () use ($key, $user, $agencyId) {
            $leases = Cache::get($key, []);
            $leases[$user->getKey()] = now()->addSeconds(self::TTL)->timestamp;
            $now = now()->timestamp;
            $leases = array_filter($leases, fn ($expires) => $expires > $now);
            Cache::put($key, $leases, self::TTL);

            $query = User::query()->whereIn('id', array_keys($leases))->whereIn('role', UserRole::staffValues())->where('is_active', true)->where('is_deleted', false);
            if ($agencyId !== null) {
                $query->where('agcy_id', $agencyId);
            }
            $viewers = $query->get(['id', 'name'])->map(fn ($viewer) => ['id' => $viewer->getKey(), 'name' => $viewer->name]);
            $validIds = array_column($viewers->all(), 'id');
            $leases = array_intersect_key($leases, array_flip($validIds));
            Cache::put($key, $leases, self::TTL);

            return $viewers->sortBy(fn ($viewer) => mb_strtolower($viewer['name']))->values()->all();
        });

        return response()->json(['viewers' => $viewers, 'expires_in' => self::TTL])->header('Cache-Control', 'no-store, private');
    }
}
