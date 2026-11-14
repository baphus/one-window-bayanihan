<?php

namespace App\Services\Export\Queries;

use App\Casts\EncryptedString;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Export\Queries\Concerns\FiltersCaseCategories;
use App\Services\Export\Queries\Concerns\ScopesForExportUser;
use App\Support\MultiSelectFilter;
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
    private function referralsExportQuery(?User $user = null, array $filters = [])
    {
        $agency = $user?->role === UserRole::AGENCY->value;
        $query = DB::table('referrals AS r')
            ->select(array_merge([
                // Case info
                'c.case_number',

                // Case intake facts
                'c.first_name AS client_first_name',
                'c.last_name AS client_last_name',
                'c.middle_name AS client_middle_name',
                'c.contact_number AS client_contact_number',
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
            ], $agency ? [] : [
                'c.client_type AS client_type',
                'c.vulnerability_indicator AS vulnerability',
                'c.nok_vulnerability_indicator AS nok_vulnerability',
                'c.employment_country AS employment_country',
                'c.summary AS case_summary',
                'cc.name AS category',
            ]))
            ->join('cases AS c', function ($join) {
                $join->on('r.case_id', '=', 'c.id')
                    ->where('c.is_deleted', false);
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
        $statuses = MultiSelectFilter::ids($filters['status'] ?? null);
        if ($statuses !== []) {
            $query->whereIn('r.status', $statuses);
        }
        $agencyIds = MultiSelectFilter::ids($filters['agcy_id'] ?? null);
        if ($agencyIds !== []) {
            $query->whereIn('r.agcy_id', $agencyIds);
        }
        if (! empty($filters['case_id'])) {
            $query->where('r.case_id', $filters['case_id']);
        }
        if (! empty($filters['age_min_days']) && is_numeric($filters['age_min_days'])) {
            $query->where('r.created_at', '<=', now()->subDays((int) $filters['age_min_days'])->endOfDay());
        }
        if (! empty($filters['age_max_days']) && is_numeric($filters['age_max_days'])) {
            $query->where('r.created_at', '>=', now()->subDays((int) $filters['age_max_days'])->startOfDay());
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
                $q->whereRaw('r.id::text ILIKE ?', ["%{$search}%"])
                    ->orWhereExists(function ($services) use ($search) {
                        $services->selectRaw('1')->from('referral_services AS rs')
                            ->join('services AS s', 's.id', '=', 'rs.service_id')
                            ->whereColumn('rs.referral_id', 'r.id')
                            ->where('s.name', 'ilike', "%{$search}%");
                    })
                    ->orWhere('c.case_number', 'ilike', "%{$search}%")
                    ->orWhere('c.first_name', 'ilike', "%{$search}%")
                    ->orWhere('c.last_name', 'ilike', "%{$search}%")
                    ->orWhere('c.middle_name', 'ilike', "%{$search}%")
                    ->orWhere('a.name', 'ilike', "%{$search}%");
            });
        }

        $categoryIds = $filters['category_ids'] ?? [];
        if (! is_array($categoryIds) || empty($categoryIds)) {
            $categoryIds = ! empty($filters['category_id']) ? [$filters['category_id']] : [];
        }
        if ($categoryIds) {
            $query->whereIn('r.category_id', $categoryIds);
        }

        // ADMIN/CASE_MANAGER: all referrals. AGENCY: own agency referrals only.
        // Fail closed: any other role (including OFW) or missing user sees nothing.
        if ($agency && $user->agcy_id) {
            $query->where('r.agcy_id', $user->agcy_id);
        } elseif ($agency || ! in_array($user?->role, [UserRole::ADMIN->value, UserRole::CASE_MANAGER->value], true)) {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    public function getReferralsExport(?User $user = null, array $filters = []): Collection
    {
        return $this->referralsExportQuery($user, $filters)->limit(10000)->get()->map(function ($row) {
            $row = (object) $row;

            $row->issue_concern = EncryptedString::decrypt($row->issue_concern);
            $row->client_contact_number = EncryptedString::decrypt($row->client_contact_number ?? null);
            if (isset($row->employment_country)) {
                $row->employment_country = EncryptedString::decrypt($row->employment_country);
            }
            if (isset($row->vulnerability)) {
                $row->vulnerability = $row->client_type === 'NEXT_OF_KIN'
                    ? ($row->nok_vulnerability ?: $row->vulnerability)
                    : ($row->vulnerability ?: $row->nok_vulnerability);
                unset($row->nok_vulnerability);
            }

            // --- Client Full Name: "Last, First Middle" ---
            $firstName = $row->client_first_name ?? '';
            $lastName = $row->client_last_name ?? '';
            $middleInitial = $row->client_middle_name ?? '';
            $row->client_full_name = trim($lastName.($firstName ? ', '.$firstName : '').($middleInitial ? ' '.$middleInitial : ''));

            // --- Client Type display label ---
            if (isset($row->client_type)) {
                $row->client_type = $row->client_type === 'NEXT_OF_KIN' ? 'Next of Kin' : 'OFW';
            }

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
            );

            return $row;
        });
    }

    public function countReferralsExport(?User $user = null, array $filters = []): int
    {
        return (int) $this->referralsExportQuery($user, $filters)->count();
    }
}
