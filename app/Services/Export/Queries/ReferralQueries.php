<?php

namespace App\Services\Export\Queries;

use App\Casts\EncryptedString;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Export\Queries\Concerns\FiltersCaseCategories;
use App\Services\Export\Queries\Concerns\ScopesForExportUser;
use App\Services\PhilippineAddressService;
use App\Support\CategoryFilter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReferralQueries
{
    use FiltersCaseCategories, ScopesForExportUser;

    /**
     * Get referrals. ADMIN/CASE_MANAGER: all. AGENCY: own referral agency only.
     */
    public function getReferrals(?User $user = null): Collection
    {
        $query = DB::table('referrals')
            ->select([
                'id',
                'required_services',
                'category_id',
                'concern',
                'override_reason',
                'notes',
                'status',
                'decision',
                'decision_comment',
                'case_id',
                'agcy_id',
                'created_at',
                'updated_at',
            ])
            ->where('is_deleted', false);

        if (! $this->isAdmin($user)) {
            if ($user->role !== UserRole::AGENCY->value || ! $user->agcy_id) {
                return collect();
            }
            $query->where('agcy_id', $user->agcy_id);
        }

        return $query->whereNull('deleted_at')->get()->map(function ($row) {
            $row->concern = EncryptedString::decrypt($row->concern);
            $row->override_reason = EncryptedString::decrypt($row->override_reason);

            return $row;
        });
    }

    /**
     * Get referrals enriched with business data (no IDs).
     * Joins each referral to its case snapshot, category and agency to produce
     * a flat export suitable for business reporting.
     */
    public function getReferralsExport(?User $user = null, array $filters = []): Collection
    {
        if ($user?->role === UserRole::AGENCY->value && ! $user->agcy_id) {
            return collect();
        }

        $query = DB::table('referrals AS r')
            ->select([
                // Case info
                'c.case_number',
                'c.intake_date',
                'cc.name AS category',
                'r.override_reason',
                'c.status AS case_status',
                'c.tracker_number',
                'c.client_type AS client_type',
                DB::raw("CASE
                    WHEN c.client_type = 'NEXT_OF_KIN'
                        THEN COALESCE(NULLIF(c.nok_vulnerability_indicator, 'None'), NULLIF(c.vulnerability_indicator, 'None'), 'None')
                    ELSE COALESCE(NULLIF(c.vulnerability_indicator, 'None'), NULLIF(c.nok_vulnerability_indicator, 'None'), 'None')
                END AS vulnerability"),
                'c.summary AS case_summary',
                // Case intake facts
                'c.first_name AS client_first_name',
                'c.last_name AS client_last_name',
                'c.middle_name AS client_middle_name',
                'c.date_of_birth AS client_date_of_birth',
                'c.sex',
                'c.email AS client_email',
                'c.contact_number AS client_contact_number',
                // Case address snapshot
                'c.address_street AS street',
                'c.address_barangay AS barangay',
                'c.address_city_municipality AS municipality',
                'c.address_province AS province',
                'c.address_region AS region',
                // Case employment snapshot
                'c.employment_date_of_arrival AS date_of_arrival',
                'c.employment_country AS previous_country',
                'c.employment_position AS work_position',
                // Representative recorded on this case
                'c.nok_name AS nok_full_name',

                'c.nok_relationship AS nok_relationship',
                'c.nok_contact_number AS nok_contact_number',
                'c.nok_email AS nok_email',
                // Agency
                'a.name AS referred_agency',
                // Referral info
                'r.required_services',
                'r.created_at AS date_referred',
                'r.status AS referral_status',
                // Latest update — most recent milestone title, or fallback to status change
                DB::raw('(SELECT m.title FROM milestones m WHERE m.refr_id = r.id AND m.is_deleted = false ORDER BY m.created_at DESC LIMIT 1) AS latest_milestone_title'),
                DB::raw('(SELECT m.created_at FROM milestones m WHERE m.refr_id = r.id AND m.is_deleted = false ORDER BY m.created_at DESC LIMIT 1) AS latest_milestone_date'),
                'r.updated_at AS referral_updated_at',
                // Issue/Concern
                'r.concern AS issue_concern',
            ])
            ->join('cases AS c', function ($join) {
                $join->on('r.case_id', '=', 'c.id')
                    ->where('c.is_deleted', false)
                    ->where('c.status', '!=', 'ARCHIVED');
            })
            ->leftJoin('agencies AS a', function ($join) {
                $join->on('r.agcy_id', '=', 'a.id')
                    ->where('a.is_deleted', false);
            })
            ->leftJoin('case_categories AS cc', 'cc.id', '=', 'r.category_id')
            ->whereNull('r.deleted_at')->whereNull('c.deleted_at')
            ->where('r.is_deleted', false)
            ->orderBy('r.created_at', 'desc');

        // --- Apply optional filters ---
        if (! empty($filters['status'])) {
            $query->where('r.status', $filters['status']);
        }
        if (! empty($filters['age_min_days']) && is_numeric($filters['age_min_days'])) {
            $query->where('r.created_at', '<=', now()->subDays((int) $filters['age_min_days']));
        }
        if (! empty($filters['age_max_days']) && is_numeric($filters['age_max_days'])) {
            $query->where('r.created_at', '>=', now()->subDays((int) $filters['age_max_days']));
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('r.created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('r.created_at', '<=', $filters['date_to']);
        }
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('c.case_number', 'ilike', "%{$search}%")
                    ->orWhere('c.first_name', 'ilike', "%{$search}%")
                    ->orWhere('c.last_name', 'ilike', "%{$search}%")
                    ->orWhere('a.name', 'ilike', "%{$search}%")
                    ->orWhere('r.required_services', 'ilike', "%{$search}%");
            });
        }

        $categoryIds = CategoryFilter::fromArray($filters)->ids();
        if ($categoryIds) {
            $query->whereIn('r.category_id', $categoryIds);
        }

        // ADMIN/CASE_MANAGER: all referrals. AGENCY: own agency referrals only.
        // Fail closed: any other role (including OFW) or missing user sees nothing.
        if ($user?->role === UserRole::AGENCY->value && $user->agcy_id) {
            $query->where('r.agcy_id', $user->agcy_id);
        } elseif ($user?->role !== UserRole::ADMIN->value && $user?->role !== UserRole::CASE_MANAGER->value) {
            $query->whereRaw('1 = 0');
        }

        // Safety cap — prevents memory exhaustion on unbounded exports.
        // The DataExportService should eventually support streaming for larger sets.
        $query->limit(10000);

        return $query->get()->map(function ($row) {
            $row = (object) $row;

            $row->issue_concern = EncryptedString::decrypt($row->issue_concern);
            $row->override_reason = EncryptedString::decrypt($row->override_reason);

            $row->client_date_of_birth = EncryptedString::decrypt($row->client_date_of_birth ?? null);
            $row->client_contact_number = EncryptedString::decrypt($row->client_contact_number ?? null);
            $row->client_email = EncryptedString::decrypt($row->client_email ?? null);
            $row->nok_contact_number = EncryptedString::decrypt($row->nok_contact_number ?? null);
            $row->nok_email = EncryptedString::decrypt($row->nok_email ?? null);
            // Address street is encrypted
            $row->street = EncryptedString::decrypt($row->street ?? null);
            // Employment subquery fields
            if (isset($row->previous_country)) {
                $row->previous_country = EncryptedString::decrypt($row->previous_country);
            }
            if (isset($row->work_position)) {
                $row->work_position = EncryptedString::decrypt($row->work_position);
            }

            // --- Client Full Name: "Last, First Middle" ---
            $firstName = $row->client_first_name ?? '';
            $lastName = $row->client_last_name ?? '';
            $middleInitial = $row->client_middle_name ?? '';
            $row->client_full_name = trim($lastName.($firstName ? ', '.$firstName : '').($middleInitial ? ' '.$middleInitial : ''));

            // --- Client Age ---
            $row->client_age = '';
            if (! empty($row->client_date_of_birth)) {
                try {
                    $dob = new \DateTimeImmutable($row->client_date_of_birth);
                    $row->client_age = (string) (int) CarbonImmutable::parse($dob)->diffInYears(CarbonImmutable::parse($row->intake_date), true);
                } catch (\Exception) {
                    $row->client_age = '';
                }
            }

            // --- Client Type display label ---
            $row->client_type = $row->client_type === 'NEXT_OF_KIN' ? 'Next of Kin' : 'OFW';

            // --- Client Full Address ---
            $addressResolver = app(PhilippineAddressService::class);
            $row->client_full_address = $addressResolver->format(
                $row->street ?? null,
                $row->barangay ?? null,
                $row->municipality ?? null,
                $row->province ?? null,
                $row->region ?? null,
            );

            // --- Date Referred display ---
            $row->date_referred = $row->date_referred instanceof \DateTimeInterface
                ? $row->date_referred->format('Y-m-d')
                : (($row->date_referred && str_contains((string) $row->date_referred, ' '))
                    ? substr((string) $row->date_referred, 0, 10)
                    : (string) ($row->date_referred ?? ''));

            // --- Latest Update: milestone title + date, or meaningful status description ---
            $statusDescription = match ($row->referral_status ?? '') {
                'PENDING' => 'Sent to agency — awaiting response',
                'PROCESSING' => 'Accepted — now processing',
                'FOR_COMPLIANCE' => 'Set as For Compliance',
                'COMPLETED' => 'Completed',
                'REJECTED' => 'Rejected',
                default => 'Status: '.($row->referral_status ?? 'Unknown'),
            };

            if (! empty($row->latest_milestone_title)) {
                $milestoneDate = $row->latest_milestone_date ?? '';
                if ($milestoneDate && str_contains((string) $milestoneDate, ' ')) {
                    $milestoneDate = substr((string) $milestoneDate, 0, 16);
                }
                // If milestone is newer than updated_at, use milestone; otherwise use status
                $usesMilestone = true;
                if (! empty($row->referral_updated_at) && ! empty($row->latest_milestone_date)) {
                    $usesMilestone = $row->latest_milestone_date >= $row->referral_updated_at;
                }
                if ($usesMilestone) {
                    $row->latest_update = $row->latest_milestone_title.' ('.$milestoneDate.')';
                } else {
                    $updatedAt = str_contains((string) $row->referral_updated_at, ' ')
                        ? substr((string) $row->referral_updated_at, 0, 16)
                        : (string) $row->referral_updated_at;
                    $row->latest_update = $statusDescription.' ('.$updatedAt.')';
                }
            } elseif (! empty($row->referral_updated_at) && (string) $row->referral_updated_at !== (string) $row->date_referred) {
                $updatedAt = str_contains((string) $row->referral_updated_at, ' ')
                    ? substr((string) $row->referral_updated_at, 0, 16)
                    : (string) $row->referral_updated_at;
                $row->latest_update = $statusDescription.' ('.$updatedAt.')';
            } else {
                $dateReferred = $row->date_referred ?? '';
                $row->latest_update = 'Sent to agency ('.$dateReferred.')';
            }
            unset($row->latest_milestone_title, $row->latest_milestone_date, $row->referral_updated_at);

            // Strip raw intermediate fields
            unset(
                $row->client_first_name,
                $row->client_last_name,
                $row->client_middle_name,
                $row->street,
            );

            $row->barangay = $addressResolver->resolve($row->barangay ?? null);
            $row->municipality = $addressResolver->resolve($row->municipality ?? null);
            $row->province = $addressResolver->resolve($row->province ?? null);
            $row->region = $addressResolver->resolve($row->region ?? null);

            return $row;
        });
    }

    public function countReferralsExport(?User $user = null, array $filters = []): int
    {
        return (int) $this->getReferralsExport($user, $filters)->count();
    }
}
