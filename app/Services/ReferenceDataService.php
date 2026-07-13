<?php

namespace App\Services;

use App\Helpers\CacheHelper;
use App\Models\Agency;
use App\Models\CaseCategory;
use App\Models\CaseIssue;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Centralized service for reference/dropdown data that rarely changes.
 *
 * All methods return cached results to avoid redundant DB queries across
 * Controllers (Cases, Referrals, Clients, Reports all query the same tables).
 * Invalidation is handled by CacheInvalidationObserver on model changes.
 */
class ReferenceDataService
{
    // ── Cache TTLs (seconds) ─────────────────────────────────────────────

    private const TTL_REFERENCE = 3600;       // 1 hour — categories, issues, agencies
    private const TTL_USERS = 600;            // 10 minutes — user lists
    private const TTL_AGENCIES_FULL = 1800;   // 30 minutes — agencies with services tree
    private const TTL_DEFAULT_AGENCY = 86400; // 24 hours — default agency

    // ── Cache Keys ───────────────────────────────────────────────────────

    public const KEY_AGENCIES_DROPDOWN = 'ref:agencies:dropdown';
    public const KEY_CATEGORIES_ACTIVE = 'ref:categories:active';
    public const KEY_ISSUES_ACTIVE = 'ref:issues:active';
    public const KEY_USERS_CASE_MANAGERS = 'ref:users:case_managers';
    public const KEY_AGENCIES_WITH_SERVICES = 'ref:agencies:with_services';
    public const KEY_AGENCY_DEFAULT = 'ref:agency:default';
    public const KEY_AGENCIES_ACTIVE_FULL = 'ref:agencies:active_full';

    // ── Dropdown/Reference Data Methods ──────────────────────────────────

    /**
     * Agency list for dropdown filters (id + name only).
     * Used by: CaseController, ReferralController, ClientController, ReportsController
     */
    public function getAgenciesDropdown(): Collection
    {
        return CacheHelper::safeRemember(
            self::KEY_AGENCIES_DROPDOWN,
            self::TTL_REFERENCE,
            fn () => Agency::select('id', 'name')->orderBy('name')->get(),
        );
    }

    /**
     * Active case categories for filters and forms.
     * Used by: CaseController, ReferralController, ClientController, ReportsController
     */
    public function getActiveCategories(): Collection
    {
        return CacheHelper::safeRemember(
            self::KEY_CATEGORIES_ACTIVE,
            self::TTL_REFERENCE,
            fn () => CaseCategory::where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'color']),
        );
    }

    /**
     * Active case issues for filters and forms.
     * Used by: CaseController, ReferralController, ClientController, ReportsController
     */
    public function getActiveIssues(): Collection
    {
        return CacheHelper::safeRemember(
            self::KEY_ISSUES_ACTIVE,
            self::TTL_REFERENCE,
            fn () => CaseIssue::where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name']),
        );
    }

    /**
     * Case manager users for dropdown (assignment filters).
     * Used by: CaseController index
     */
    public function getCaseManagerUsers(): Collection
    {
        return CacheHelper::safeRemember(
            self::KEY_USERS_CASE_MANAGERS,
            self::TTL_USERS,
            fn () => User::where('role', 'CASE_MANAGER')
                ->where('is_active', true)
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
        );
    }

    /**
     * Agencies with full services and requirements tree.
     * Used by: ReferralController create, ReferralService
     */
    public function getAgenciesWithServices(): Collection
    {
        return CacheHelper::safeRemember(
            self::KEY_AGENCIES_WITH_SERVICES,
            self::TTL_AGENCIES_FULL,
            fn () => Agency::with('services.requirements')
                ->where('is_deleted', false)
                ->get(),
        );
    }

    /**
     * The default agency record.
     * Used by: ProfileController, AdminUserController, registration
     */
    public function getDefaultAgency(): ?Agency
    {
        return CacheHelper::safeRemember(
            self::KEY_AGENCY_DEFAULT,
            self::TTL_DEFAULT_AGENCY,
            fn () => Agency::where('is_default', true)->first(),
        );
    }

    /**
     * All active agencies (full data for public welcome page).
     */
    public function getActiveAgenciesFull(): Collection
    {
        return CacheHelper::safeRemember(
            self::KEY_AGENCIES_ACTIVE_FULL,
            self::TTL_REFERENCE,
            fn () => Agency::where('is_active', true)->get(),
        );
    }

    // ── Invalidation ─────────────────────────────────────────────────────

    /**
     * Flush all agency-related caches.
     */
    public static function invalidateAgencies(): void
    {
        $keys = [
            self::KEY_AGENCIES_DROPDOWN,
            self::KEY_AGENCIES_WITH_SERVICES,
            self::KEY_AGENCY_DEFAULT,
            self::KEY_AGENCIES_ACTIVE_FULL,
        ];

        foreach ($keys as $key) {
            cache()->forget($key);
        }
    }

    /**
     * Flush category caches.
     */
    public static function invalidateCategories(): void
    {
        cache()->forget(self::KEY_CATEGORIES_ACTIVE);
    }

    /**
     * Flush case issue caches.
     */
    public static function invalidateIssues(): void
    {
        cache()->forget(self::KEY_ISSUES_ACTIVE);
    }

    /**
     * Flush user-related caches.
     */
    public static function invalidateUsers(): void
    {
        cache()->forget(self::KEY_USERS_CASE_MANAGERS);
    }

    /**
     * Flush all reference data caches.
     */
    public static function invalidateAll(): void
    {
        self::invalidateAgencies();
        self::invalidateCategories();
        self::invalidateIssues();
        self::invalidateUsers();
    }
}
