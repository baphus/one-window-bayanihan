<?php

namespace App\Services\Export\Queries;

use App\Casts\EncryptedString;
use App\Enums\UserRole;
use App\Models\CaseFile;
use App\Models\User;
use App\Services\Export\Queries\Concerns\FiltersCaseCategories;
use App\Services\Export\Queries\Concerns\ScopesForExportUser;
use App\Services\PhilippineAddressService;
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
            $query->whereIn('case_id', function ($q) use ($user) {
                $q->select('id')
                    ->from('cases')
                    ->where('user_id', $user->id)
                    ->where('is_deleted', false);
            });
        }

        return $query->get();
    }

    /**
     * Get referrals enriched with business data (no IDs).
     * Joins cases, clients, agencies, case_issues, and client addresses
     * to produce a flat export suitable for business reporting.
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
                'c.status AS case_status',
                'c.tracker_number',
                'c.client_type',
                DB::raw("CASE
                    WHEN c.client_type = 'NEXT_OF_KIN'
                        THEN COALESCE(NULLIF(c.nok_vulnerability_indicator, 'None'), NULLIF(c.vulnerability_indicator, 'None'), 'None')
                    ELSE COALESCE(NULLIF(c.vulnerability_indicator, 'None'), NULLIF(c.nok_vulnerability_indicator, 'None'), 'None')
                END AS vulnerability"),
                'c.summary AS case_summary',
                // Client info
                'cl.first_name AS client_first_name',
                'cl.last_name AS client_last_name',
                'cl.middle_name AS client_middle_name',
                'cl.date_of_birth AS client_date_of_birth',
                'cl.sex',
                'cl.email AS client_email',
                'cl.contact_number AS client_contact_number',
                // Address — first active address
                DB::raw('(SELECT ca.street FROM client_addresses ca WHERE ca.client_id = c.client_id AND ca.is_deleted = false LIMIT 1) AS street'),
                DB::raw('(SELECT ca.barangay FROM client_addresses ca WHERE ca.client_id = c.client_id AND ca.is_deleted = false LIMIT 1) AS barangay'),
                DB::raw('(SELECT ca.city_municipality FROM client_addresses ca WHERE ca.client_id = c.client_id AND ca.is_deleted = false LIMIT 1) AS municipality'),
                DB::raw('(SELECT ca.province FROM client_addresses ca WHERE ca.client_id = c.client_id AND ca.is_deleted = false LIMIT 1) AS province'),
                DB::raw('(SELECT ca.region FROM client_addresses ca WHERE ca.client_id = c.client_id AND ca.is_deleted = false LIMIT 1) AS region'),
                // Employment — most recent
                DB::raw('(SELECT ce.date_of_arrival FROM client_employments ce WHERE ce.client_id = c.client_id AND ce.is_deleted = false ORDER BY ce.created_at DESC LIMIT 1) AS date_of_arrival'),
                DB::raw('(SELECT ce.country FROM client_employments ce WHERE ce.client_id = c.client_id AND ce.is_deleted = false ORDER BY ce.created_at DESC LIMIT 1) AS previous_country'),
                DB::raw('(SELECT ce.position FROM client_employments ce WHERE ce.client_id = c.client_id AND ce.is_deleted = false ORDER BY ce.created_at DESC LIMIT 1) AS work_position'),
                // Next of Kin — primary first, then by sort_order
                DB::raw('(SELECT nok.first_name FROM next_of_kin nok WHERE nok.client_id = c.client_id AND nok.is_deleted = false ORDER BY nok.is_primary DESC, nok.sort_order ASC LIMIT 1) AS nok_first_name'),
                DB::raw('(SELECT nok.last_name FROM next_of_kin nok WHERE nok.client_id = c.client_id AND nok.is_deleted = false ORDER BY nok.is_primary DESC, nok.sort_order ASC LIMIT 1) AS nok_last_name'),
                DB::raw('(SELECT nok.middle_name FROM next_of_kin nok WHERE nok.client_id = c.client_id AND nok.is_deleted = false ORDER BY nok.is_primary DESC, nok.sort_order ASC LIMIT 1) AS nok_middle_name'),
                DB::raw('(SELECT nok.relationship FROM next_of_kin nok WHERE nok.client_id = c.client_id AND nok.is_deleted = false ORDER BY nok.is_primary DESC, nok.sort_order ASC LIMIT 1) AS nok_relationship'),
                DB::raw('(SELECT nok.phone_number FROM next_of_kin nok WHERE nok.client_id = c.client_id AND nok.is_deleted = false ORDER BY nok.is_primary DESC, nok.sort_order ASC LIMIT 1) AS nok_contact_number'),
                DB::raw('(SELECT nok.email FROM next_of_kin nok WHERE nok.client_id = c.client_id AND nok.is_deleted = false ORDER BY nok.is_primary DESC, nok.sort_order ASC LIMIT 1) AS nok_email'),
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
                DB::raw("COALESCE(ci.name, '—') AS issue_concern"),
            ])
            ->join('cases AS c', function ($join) {
                $join->on('r.case_id', '=', 'c.id')
                    ->where('c.is_deleted', false)
                    ->where('c.status', '!=', 'ARCHIVED');
            })
            ->leftJoin('clients AS cl', function ($join) {
                $join->on('c.client_id', '=', 'cl.id')
                    ->where('cl.is_deleted', false);
            })
            ->leftJoin('agencies AS a', function ($join) {
                $join->on('r.agcy_id', '=', 'a.id')
                    ->where('a.is_deleted', false);
            })
            ->leftJoin('case_issues AS ci', 'c.case_issue_id', '=', 'ci.id')
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
                    ->orWhere('cl.first_name', 'ilike', "%{$search}%")
                    ->orWhere('cl.last_name', 'ilike', "%{$search}%")
                    ->orWhere('a.name', 'ilike', "%{$search}%")
                    ->orWhere('r.required_services', 'ilike', "%{$search}%");
            });
        }

        $this->applyCategoryFilter($query, 'c', $filters);

        // ADMIN/CASE_MANAGER: all referrals. AGENCY: own agency referrals only.
        if ($user?->role === UserRole::AGENCY->value && $user->agcy_id) {
            $query->where('r.agcy_id', $user->agcy_id);
        } elseif ($user?->role === UserRole::AGENCY->value) {
            $query->whereRaw('1 = 0');
        }

        // Safety cap — prevents memory exhaustion on unbounded exports.
        // The DataExportService should eventually support streaming for larger sets.
        $query->limit(10000);

        return $query->get()->map(function ($row) {
            $row = (object) $row;

            // --- Decrypt encrypted PII fields from raw subqueries ---
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
                    $row->client_age = (string) CarbonImmutable::parse($dob)->age;
                } catch (\Exception) {
                    $row->client_age = '';
                }
            }

            // --- Client Type display label ---
            $row->client_type = $row->client_type === CaseFile::CLIENT_TYPE_NEXT_OF_KIN ? 'Next of Kin' : 'OFW';

            // --- Client Full Address ---
            $addressResolver = app(PhilippineAddressService::class);
            $row->client_full_address = $addressResolver->format(
                $row->street ?? null,
                $row->barangay ?? null,
                $row->municipality ?? null,
                $row->province ?? null,
                $row->region ?? null,
            );

            // --- NOK Full Name: "Last, First M." ---
            $nokFirstName = $row->nok_first_name ?? '';
            $nokLastName = $row->nok_last_name ?? '';
            $nokMiddle = $row->nok_middle_name ?? '';
            $row->nok_full_name = trim($nokLastName.($nokFirstName ? ', '.$nokFirstName : '').($nokMiddle ? ' '.$nokMiddle : ''));

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
                $row->nok_first_name,
                $row->nok_last_name,
                $row->nok_middle_name,
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
