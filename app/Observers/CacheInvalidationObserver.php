<?php

namespace App\Observers;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Agency;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\CaseIssue;
use App\Models\CaseStatus;
use App\Models\Feedback;
use App\Models\FeedbackInvitation;
use App\Models\Milestone;
use App\Models\Referral;
use App\Models\Service;
use App\Models\ServiceRequirement;
use App\Models\User;
use App\Services\ReferenceDataService;
use App\Services\ReportsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Invalidates relevant Redis caches when models are created, updated, or deleted.
 *
 * Registered in AppServiceProvider alongside AuditObserver for models that
 * affect cached reference data, stats, or shared props.
 *
 * NOTE: Feedback, FeedbackInvitation, and Milestone models must also be
 * registered to observe this observer in AppServiceProvider.
 */
class CacheInvalidationObserver
{
    public function created(Model $model): void
    {
        $this->invalidateFor($model);
    }

    public function updated(Model $model): void
    {
        $this->invalidateFor($model);

        // If a user's agency assignment changed, invalidate their cached agency
        if ($model instanceof User && $model->wasChanged('agcy_id')) {
            HandleInertiaRequests::invalidateUserAgency($model->id);
        }

        // If a user's role changed, invalidate user lists
        if ($model instanceof User && $model->wasChanged('role')) {
            ReferenceDataService::invalidateUsers();
            Cache::forget('admin:user_stats');
        }
    }

    public function deleted(Model $model): void
    {
        $this->invalidateFor($model);
    }

    private function invalidateFor(Model $model): void
    {
        match (true) {
            $model instanceof Agency => $this->invalidateAgency(),
            $model instanceof CaseCategory => $this->invalidateCategory(),
            $model instanceof CaseIssue => $this->invalidateIssue(),
            $model instanceof CaseStatus => $this->invalidateCaseStatus(),
            $model instanceof User => $this->invalidateUser(),
            $model instanceof CaseFile => $this->invalidateCase($model),
            $model instanceof Referral => $this->invalidateReferral(),
            $model instanceof Service,
            $model instanceof ServiceRequirement => $this->invalidateService(),
            $model instanceof Feedback,
            $model instanceof FeedbackInvitation => $this->invalidateFeedback(),
            $model instanceof Milestone => $this->invalidateMilestone(),
            default => null,
        };
    }

    private function invalidateAgency(): void
    {
        ReferenceDataService::invalidateAgencies();
        Cache::forget('admin:agency_stats');
        Cache::forget('dashboard:active_agencies_count');
        Cache::forget('dashboard:admin_agency_counts');
        Cache::forget('stakeholder:agencies_list');
        ReportsService::invalidateAll();
    }

    private function invalidateCategory(): void
    {
        ReferenceDataService::invalidateCategories();
        Cache::forget('dashboard:cm_cases_by_category');
        ReportsService::invalidateAll();
    }

    private function invalidateIssue(): void
    {
        ReferenceDataService::invalidateIssues();
        ReportsService::invalidateAll();
    }

    private function invalidateCaseStatus(): void
    {
        ReportsService::invalidateAll();
    }

    private function invalidateUser(): void
    {
        ReferenceDataService::invalidateUsers();
        Cache::forget('admin:user_stats');
        Cache::forget('dashboard:admin_user_counts');
    }

    private function invalidateCase(): void
    {
        Cache::forget('stats:cases');
        Cache::forget('dashboard:cm_counts');
        Cache::forget('dashboard:admin_counts');
        Cache::forget('dashboard:cm_closed_days');
        Cache::forget('dashboard:cm_cases_by_category');
        Cache::forget('dashboard:cm_client_counts');
        Cache::forget('dashboard:cm_priority_cases');
        Cache::forget('dashboard:cm_aging_open_count');
        Cache::forget('dashboard:cm_no_referral_count');
    }

    private function invalidateReferral(): void
    {
        Cache::forget('stats:cases');
        // Invalidate all referral stats (could be role-specific, but simplest to clear all)
        Cache::forget('stats:referrals:all:global');
        Cache::forget('stats:referrals:AGENCY:' . ($this->getAgencyId() ?? 'global'));
        Cache::forget('dashboard:cm_counts');
        Cache::forget('dashboard:admin_counts');
        Cache::forget('dashboard:cm_client_counts');
        Cache::forget('dashboard:cm_aging_bands');
        Cache::forget('dashboard:cm_priority_referrals');
        Cache::forget('dashboard:cm_scorecard');
        Cache::forget('dashboard:cm_agency_breakdown');
        Cache::forget('dashboard:cm_priority_cases');
        Cache::forget('dashboard:cm_aging_open_count');
        Cache::forget('dashboard:cm_no_referral_count');
        Cache::forget('stakeholder:agencies_list');
        // Agency-specific keys cleared via pattern (agcy_id may not be reliably available)
    }

    private function invalidateService(): void
    {
        ReferenceDataService::invalidateAgencies(); // services tree is part of agency cache
    }

    private function invalidateFeedback(): void
    {
        // Can't pattern-delete easily, so clear the most common keys
        // Admin summary for all windows
        foreach (['all', '7d', '30d', '90d', 'quarter', 'year'] as $window) {
            Cache::forget('feedback:admin_summary:' . $window);
        }
        // Agency-specific feedback dashboard keys will expire via TTL (180s)
    }

    private function invalidateMilestone(): void
    {
        // Tracking cache is keyed by case_id — can't reliably get it from observer
        // Rely on TTL (90s) for tracking data freshness
        // Clear dashboard priority referrals since milestone activity affects overdue logic
        Cache::forget('dashboard:cm_priority_referrals');
    }

    private function getAgencyId(): ?string
    {
        return null; // Can't reliably get from observer context; rely on TTL for agency-scoped keys
    }
}
