<?php

namespace App\Services;

use App\Models\CaseDocument;
use App\Models\CaseFile;
use App\Models\Milestone;
use App\Models\Referral;
use App\Models\ReferralAttachment;
use App\Models\ReferralClientAccessLink;
use App\Models\ReferralClientMessage;
use App\Models\ReferralClientMessageAttachment;
use App\Models\ReferralClientRequest;
use App\Models\ReferralClientRequestItem;
use App\Models\ReferralComment;
use App\Models\ReferralServiceRequirement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class ActivityScope
{
    private const SENTINEL = '00000000-0000-0000-0000-000000000000';

    public static function entityIdsFor(User $user): array
    {
        if ($user->isAdmin()) {
            return [];
        }

        // Empty entity IDs mean "no filter" only for ADMIN; scoped roles need the sentinel to deny access.
        if ($user->isCaseManager()) {
            $caseIds = CaseFile::where('user_id', $user->id)->pluck('id');
            $referralIds = Referral::whereIn('case_id', $caseIds)->pluck('id');
            $entityIds = self::expandReferralIds($caseIds, $referralIds, true);

            return $entityIds === [] ? [self::SENTINEL] : $entityIds;
        }

        if ($user->isAgency()) {
            if (! $user->agcy_id) {
                return [self::SENTINEL];
            }

            $referralIds = Referral::where('agcy_id', $user->agcy_id)->pluck('id');
            $entityIds = self::expandReferralIds(collect(), $referralIds, false);

            return $entityIds === [] ? [self::SENTINEL] : $entityIds;
        }

        return [self::SENTINEL];
    }

    public static function caseEntityIds(string $caseId): array
    {
        $caseIds = collect([$caseId]);
        $referralIds = Referral::where('case_id', $caseId)->where('is_deleted', false)->pluck('id');

        return self::expandReferralIds($caseIds, $referralIds, true);
    }

    public static function referralEntityIds(string $referralId): array
    {
        return self::expandReferralIds(collect(), collect([$referralId]), false);
    }

    public static function applyTo(Builder $query, User $user): Builder
    {
        $modules = ActivityVisibility::modulesFor($user->role);
        $actions = ActivityVisibility::actionsFor($user->role);

        $query->whereIn('module', $modules)->whereIn('action', $actions);

        $entityIds = self::entityIdsFor($user);
        if ($entityIds !== []) {
            $query->whereIn('entity_id', $entityIds);
        }

        return $query;
    }

    /**
     * @param  Collection<int, string>  $caseIds
     * @param  Collection<int, string>  $referralIds
     * @return array<int, string>
     */
    private static function expandReferralIds(Collection $caseIds, Collection $referralIds, bool $includeCases): array
    {
        $ids = $includeCases ? $caseIds->all() : [];
        if ($includeCases) {
            $ids = array_merge($ids, CaseDocument::withTrashed()->whereIn('case_id', $caseIds)->pluck('id')->all());
        }
        $ids = array_merge($ids, $referralIds->all());
        $ids = array_merge($ids, Milestone::whereIn('refr_id', $referralIds)->pluck('id')->all());
        $ids = array_merge($ids, ReferralAttachment::withTrashed()->whereIn('referral_id', $referralIds)->pluck('id')->all());
        $ids = array_merge($ids, ReferralServiceRequirement::whereIn('referral_id', $referralIds)->pluck('id')->all());
        $ids = array_merge($ids, ReferralComment::whereIn('refr_id', $referralIds)->pluck('id')->all());

        $requestIds = ReferralClientRequest::withTrashed()->whereIn('referral_id', $referralIds)->pluck('id');
        $ids = array_merge($ids, $requestIds->all());
        $ids = array_merge($ids, ReferralClientRequestItem::whereIn('request_id', $requestIds)->pluck('id')->all());
        $ids = array_merge($ids, ReferralClientMessage::withTrashed()->whereIn('request_id', $requestIds)->pluck('id')->all());
        $ids = array_merge($ids, ReferralClientAccessLink::whereIn('request_id', $requestIds)->pluck('id')->all());

        $messageIds = ReferralClientMessage::withTrashed()->whereIn('request_id', $requestIds)->pluck('id');
        $ids = array_merge($ids, ReferralClientMessageAttachment::withTrashed()->whereIn('message_id', $messageIds)->pluck('id')->all());

        return array_values(array_unique($ids));
    }
}
