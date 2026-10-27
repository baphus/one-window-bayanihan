<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Helpers\CacheHelper;
use App\Models\Agency;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\CaseIssue;
use App\Models\CaseStatus;
use App\Models\Client;
use App\Models\ClientEmployment;
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

    public const KEY_CASE_STATUSES = 'ref:case_statuses:case';

    public const KEY_REFERRAL_STATUSES = 'ref:case_statuses:referral';

    public const KEY_CLIENT_TYPES = 'ref:client_types';

    public const KEY_VULNERABILITY_INDICATORS = 'ref:vulnerability_indicators';

    public const KEY_SEXES = 'ref:sexes';

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
            fn () => User::where('role', UserRole::CASE_MANAGER->value)
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

    public function assignDefaultAgency(User $user): User
    {
        if ($user->agcy_id === null) {
            $default = $this->getDefaultAgency();
            if ($default) {
                $user->agcy_id = $default->id;
                $user->save();
            }
        }

        return $user;
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

    /**
     * Distinct occupation values from client_employments, for the case-create
     * occupation dropdown. Not cached — the list should reflect the latest
     * entries as new cases are created. Curated defaults are merged on the
     * frontend (resources/js/data/defaultOccupations.ts).
     *
     * Returns an array of [value, label] pairs for SearchableSelect.
     */
    public function getOccupationOptions(): array
    {
        $occupations = ClientEmployment::query()
            ->whereNotNull('last_position')
            ->where('last_position', '!=', '')
            ->cursor()
            ->map(fn (ClientEmployment $e) => trim((string) $e->last_position))
            ->filter()
            ->unique()
            ->values()
            ->sort()
            ->toArray();

        return array_values(array_map(fn (string $o) => ['value' => $o, 'label' => $o], $occupations));
    }

    // ── Filter option lists ──────────────────────────────────────────────
    //
    // These drive the index pages' filter panels, so an option can never list a
    // value the database does not hold.

    /**
     * Case statuses available to the case filter panel.
     * Sourced from case_statuses (type=case), so ARCHIVED and PENDING_REVIEW
     * appear exactly when the reference table carries them.
     */
    public function getCaseStatusOptions(): array
    {
        return $this->statusOptions(self::KEY_CASE_STATUSES, 'case');
    }

    /**
     * Referral statuses available to the referral filter panel.
     */
    public function getReferralStatusOptions(): array
    {
        return $this->statusOptions(self::KEY_REFERRAL_STATUSES, 'referral');
    }

    /**
     * @return array<int, array{value: string, label: string, color: ?string}>
     */
    private function statusOptions(string $cacheKey, string $type): array
    {
        return CacheHelper::safeRemember($cacheKey, self::TTL_REFERENCE, function () use ($type) {
            return CaseStatus::query()
                ->where('type', $type)
                ->where('is_active', true)
                ->where('is_deleted', false)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['slug', 'name', 'color'])
                ->map(fn (CaseStatus $status) => [
                    'value' => $status->slug,
                    'label' => $status->name,
                    'color' => $status->color,
                ])
                ->values()
                ->toArray();
        });
    }

    /**
     * Client types actually present in the directory.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function getClientTypeOptions(): array
    {
        return CacheHelper::safeRemember(self::KEY_CLIENT_TYPES, self::TTL_REFERENCE, function () {
            return Client::query()
                ->where('is_deleted', false)
                ->whereNotNull('type')
                ->where('type', '!=', '')
                ->distinct()
                ->orderBy('type')
                ->pluck('type')
                ->map(fn (string $type) => ['value' => $type, 'label' => self::clientTypeLabel($type)])
                ->values()
                ->toArray();
        });
    }

    /**
     * Vulnerability markers present on case records.
     *
     * Both indicator columns hold a free-text comma-separated list, so the
     * options are derived from the data itself rather than a lookup table that
     * would drift from it.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function getVulnerabilityOptions(): array
    {
        return CacheHelper::safeRemember(self::KEY_VULNERABILITY_INDICATORS, self::TTL_USERS, function () {
            $raw = CaseFile::query()
                ->where('is_deleted', false)
                ->whereNotNull('vulnerability_indicator')
                ->where('vulnerability_indicator', '!=', '')
                ->pluck('vulnerability_indicator')
                ->merge(
                    CaseFile::query()
                        ->where('is_deleted', false)
                        ->whereNotNull('nok_vulnerability_indicator')
                        ->where('nok_vulnerability_indicator', '!=', '')
                        ->pluck('nok_vulnerability_indicator')
                );

            return $raw
                ->flatMap(fn (string $value) => explode(',', $value))
                ->map(fn (string $value) => trim($value))
                ->filter(fn (string $value) => $value !== '')
                ->unique()
                ->sort()
                ->values()
                ->map(fn (string $value) => ['value' => $value, 'label' => $value])
                ->toArray();
        });
    }

    /**
     * Sex values actually present in the directory.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function getSexOptions(): array
    {
        return CacheHelper::safeRemember(self::KEY_SEXES, self::TTL_REFERENCE, function () {
            return Client::query()
                ->where('is_deleted', false)
                ->whereNotNull('sex')
                ->where('sex', '!=', '')
                ->distinct()
                ->orderBy('sex')
                ->pluck('sex')
                ->map(fn (string $sex) => ['value' => $sex, 'label' => ucfirst(strtolower($sex))])
                ->values()
                ->toArray();
        });
    }

    /**
     * Screen label for a stored client type. Explicit so "NEXT_OF_KIN" never
     * renders as "Next Of Kin".
     */
    private static function clientTypeLabel(string $type): string
    {
        return match (strtoupper($type)) {
            'OFW' => 'OFW',
            'NEXT_OF_KIN' => 'Next of Kin',
            default => ucwords(strtolower(str_replace('_', ' ', $type))),
        };
    }

    /**
     * Flush the case/referral status option caches.
     */
    public static function invalidateCaseStatuses(): void
    {
        cache()->forget(self::KEY_CASE_STATUSES);
        cache()->forget(self::KEY_REFERRAL_STATUSES);
    }

    /**
     * Flush the option caches derived from client/case records.
     */
    public static function invalidateClientOptions(): void
    {
        cache()->forget(self::KEY_CLIENT_TYPES);
        cache()->forget(self::KEY_SEXES);
        cache()->forget(self::KEY_VULNERABILITY_INDICATORS);
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
        self::invalidateCaseStatuses();
        self::invalidateClientOptions();
    }
}
