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
use Illuminate\Support\Facades\Log;

class CaseQueries
{
    use FiltersCaseCategories, ScopesForExportUser;

    /**
     * Get cases. ADMIN/CASE_MANAGER: all. AGENCY: own referral cases only.
     */
    public function getCases(?User $user = null): Collection
    {
        $query = DB::table('cases')
            ->select([
                'id',
                'case_number',
                'client_type',
                'vulnerability_indicator',
                'nok_vulnerability_indicator',
                'tracker_number',
                'summary',
                'status',
                'closed_at',
                'consent_given_at',
                'user_id',
                'client_id',
                'category_id',
                DB::raw($this->categoryNamesExpression('cases').' AS categories'),
                'created_at',
                'updated_at',
            ])
            ->where('is_deleted', false);

        if (! $this->isAdmin($user)) {
            $query->where('user_id', $user->id);
        }

        $addressResolver = app(PhilippineAddressService::class);

        return $query->get()->map(function ($row) use ($addressResolver) {
            foreach (['region', 'province', 'city_municipality', 'barangay'] as $field) {
                $row->{$field} = $addressResolver->resolve($row->{$field} ?? null);
            }

            return $row;
        });
    }

    /**
     * Get enriched cases for business export — joins related client data,
     * addresses, employments, next-of-kin, case issues, and referral parties.
     * No IDs or system fields. ADMIN/CASE_MANAGER see all; AGENCY sees own.
     *
     * @param  array  $filters  Optional: status, search, client_type, vulnerability_indicator,
     *                          user_id, agcy_id, category_id/category_ids, case_issue_id,
     *                          age_min_days, referral_state
     */
    public function getCasesExport(?User $user = null, array $filters = []): Collection
    {
        $query = DB::table('cases AS c')
            ->select([
                'c.case_number',
                'c.status',
                'c.tracker_number',
                'c.client_type',
                // Vulnerability — combine both indicators; prefer the one matching client type
                DB::raw("CASE
                    WHEN c.client_type = 'NEXT_OF_KIN'
                        THEN COALESCE(NULLIF(c.nok_vulnerability_indicator, 'None'), NULLIF(c.vulnerability_indicator, 'None'), 'None')
                    ELSE COALESCE(NULLIF(c.vulnerability_indicator, 'None'), NULLIF(c.nok_vulnerability_indicator, 'None'), 'None')
                END AS vulnerability"),
                // Client (to-one via FK)
                'cl.first_name AS client_first_name',
                'cl.last_name AS client_last_name',
                'cl.middle_name AS client_middle_name',
                'cl.sex AS ofw_sex',
                'cl.date_of_birth AS ofw_date_of_birth',
                'cl.contact_number AS ofw_contact_number',
                'cl.email AS ofw_email',
                // Address — to-many, take first row
                DB::raw('(SELECT ca.barangay FROM client_addresses ca WHERE ca.client_id = c.client_id AND ca.is_deleted = false LIMIT 1) AS barangay'),
                DB::raw('(SELECT ca.city_municipality FROM client_addresses ca WHERE ca.client_id = c.client_id AND ca.is_deleted = false LIMIT 1) AS municipality'),
                DB::raw('(SELECT ca.province FROM client_addresses ca WHERE ca.client_id = c.client_id AND ca.is_deleted = false LIMIT 1) AS province'),
                DB::raw('(SELECT ca.region FROM client_addresses ca WHERE ca.client_id = c.client_id AND ca.is_deleted = false LIMIT 1) AS region'),
                // Employment — to-many, take most recent
                DB::raw('(SELECT ce.date_of_arrival FROM client_employments ce WHERE ce.client_id = c.client_id AND ce.is_deleted = false ORDER BY ce.created_at DESC LIMIT 1) AS date_of_arrival'),
                DB::raw('(SELECT ce.country FROM client_employments ce WHERE ce.client_id = c.client_id AND ce.is_deleted = false ORDER BY ce.created_at DESC LIMIT 1) AS previous_country'),
                DB::raw('(SELECT ce.position FROM client_employments ce WHERE ce.client_id = c.client_id AND ce.is_deleted = false ORDER BY ce.created_at DESC LIMIT 1) AS work_position'),
                // Case summary
                'c.summary AS case_summary',
                DB::raw($this->categoryNamesExpression('c').' AS categories'),
                // Case issue (to-one)
                DB::raw("COALESCE(ci.name, '—') AS issue_concern"),
                // Receiving parties — comma-separated agency names from referrals (COALESCE handles empty)
                DB::raw("COALESCE((SELECT STRING_AGG(a.name, ', ') FROM referrals r JOIN agencies a ON r.agcy_id = a.id WHERE r.case_id = c.id AND r.is_deleted = false), '') AS receiving_parties"),
                // Next-of-kin — to-many, take primary first, then by sort_order
                DB::raw('(SELECT nok.first_name FROM next_of_kin nok WHERE nok.client_id = c.client_id AND nok.is_deleted = false ORDER BY nok.is_primary DESC, nok.sort_order ASC LIMIT 1) AS nok_first_name'),
                DB::raw('(SELECT nok.last_name FROM next_of_kin nok WHERE nok.client_id = c.client_id AND nok.is_deleted = false ORDER BY nok.is_primary DESC, nok.sort_order ASC LIMIT 1) AS nok_last_name'),
                DB::raw('(SELECT nok.middle_name FROM next_of_kin nok WHERE nok.client_id = c.client_id AND nok.is_deleted = false ORDER BY nok.is_primary DESC, nok.sort_order ASC LIMIT 1) AS nok_middle_name'),
                DB::raw('(SELECT nok.phone_number FROM next_of_kin nok WHERE nok.client_id = c.client_id AND nok.is_deleted = false ORDER BY nok.is_primary DESC, nok.sort_order ASC LIMIT 1) AS nok_contact_number'),
                DB::raw('(SELECT nok.email FROM next_of_kin nok WHERE nok.client_id = c.client_id AND nok.is_deleted = false ORDER BY nok.is_primary DESC, nok.sort_order ASC LIMIT 1) AS nok_email'),
            ])
            ->leftJoin('clients AS cl', function ($join) {
                $join->on('c.client_id', '=', 'cl.id')
                    ->where('cl.is_deleted', false);
            })
            ->leftJoin('case_issues AS ci', 'c.case_issue_id', '=', 'ci.id')
            ->where('c.is_deleted', false)
            ->where('c.status', '!=', 'DRAFT')
            ->where('c.status', '!=', 'ARCHIVED')
            ->orderBy('c.created_at', 'desc');

        // ADMIN/CASE_MANAGER: all. AGENCY: own referral cases only.
        if ($user?->role === UserRole::AGENCY->value) {
            if ($user->agcy_id) {
                $query->whereIn('c.id', function ($q) use ($user) {
                    $q->select('case_id')
                        ->from('referrals')
                        ->where('agcy_id', $user->agcy_id)
                        ->where('is_deleted', false);
                });
            } else {
                $query->whereRaw('1=0');
            }
        }

        // --- Apply optional filters ---
        if (! empty($filters['status'])) {
            $query->where('c.status', $filters['status']);
        }
        if (! empty($filters['client_type'])) {
            if ($filters['client_type'] === CaseFile::CLIENT_TYPE_NEXT_OF_KIN) {
                $query->where('c.client_type', CaseFile::CLIENT_TYPE_NEXT_OF_KIN);
            } else {
                $query->where('c.client_type', CaseFile::CLIENT_TYPE_OFW);
            }
        }
        if (! empty($filters['vulnerability_indicator'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('c.vulnerability_indicator', 'LIKE', "%{$filters['vulnerability_indicator']}%")
                    ->orWhere('c.nok_vulnerability_indicator', 'LIKE', "%{$filters['vulnerability_indicator']}%");
            });
        }
        if (! empty($filters['user_id'])) {
            $query->where('c.user_id', $filters['user_id']);
        }
        if (! empty($filters['agcy_id'])) {
            $query->whereIn('c.id', function ($q) use ($filters) {
                $q->select('case_id')
                    ->from('referrals')
                    ->where('agcy_id', $filters['agcy_id'])
                    ->where('is_deleted', false);
            });
        }
        if (! empty($filters['age_min_days']) && is_numeric($filters['age_min_days'])) {
            $query->where('c.created_at', '<=', now()->subDays((int) $filters['age_min_days']));
        }
        if (($filters['referral_state'] ?? null) === 'none') {
            $query->whereNotIn('c.id', function ($q) {
                $q->select('case_id')
                    ->from('referrals')
                    ->where('is_deleted', false);
            });
        }
        $this->applyCategoryFilter($query, 'c', $filters);
        if (! empty($filters['case_issue_id'])) {
            $query->where('c.case_issue_id', $filters['case_issue_id']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('c.created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('c.created_at', '<=', $filters['date_to']);
        }
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('c.case_number', 'ilike', "%{$search}%")
                    ->orWhere('c.tracker_number', 'ilike', "%{$search}%")
                    ->orWhere('cl.first_name', 'ilike', "%{$search}%")
                    ->orWhere('cl.last_name', 'ilike', "%{$search}%");
            });
        }

        // Safety cap — prevents memory exhaustion on unbounded exports.
        // The DataExportService should eventually support streaming for larger sets.
        $query->limit(10000);

        return $query->get()->map(function ($row) {
            // Convert stdClass to a mutable object we can add properties to
            $row = (object) $row;

            // --- Decrypt encrypted PII fields from raw subqueries ---
            $row->ofw_date_of_birth = EncryptedString::decrypt($row->ofw_date_of_birth ?? null);
            $row->ofw_contact_number = EncryptedString::decrypt($row->ofw_contact_number ?? null);
            $row->ofw_email = EncryptedString::decrypt($row->ofw_email ?? null);
            $row->nok_contact_number = EncryptedString::decrypt($row->nok_contact_number ?? null);
            $row->nok_email = EncryptedString::decrypt($row->nok_email ?? null);
            // Employment subquery fields
            if (isset($row->previous_country)) {
                $row->previous_country = EncryptedString::decrypt($row->previous_country);
            }
            if (isset($row->work_position)) {
                $row->work_position = EncryptedString::decrypt($row->work_position);
            }

            // --- OFW Full Name: "Last, First Middle" ---
            $firstName = $row->client_first_name ?? '';
            $lastName = $row->client_last_name ?? '';
            $middleInitial = $row->client_middle_name ?? '';
            $row->ofw_full_name = trim($lastName.($firstName ? ', '.$firstName : '').($middleInitial ? ' '.$middleInitial : ''));

            // --- OFW Age ---
            $row->ofw_age = '';
            if (! empty($row->ofw_date_of_birth)) {
                try {
                    $dob = new \DateTimeImmutable($row->ofw_date_of_birth);
                    $row->ofw_age = (string) CarbonImmutable::parse($dob)->age;
                } catch (\Exception $e) {
                    Log::debug('DataExportQueries: unparseable date of birth in export', [
                        'value' => $row->ofw_date_of_birth,
                        'exception' => $e->getMessage(),
                    ]);
                    $row->ofw_age = '';
                }
            }

            // --- Client Type display label ---
            $row->client_type = $row->client_type === CaseFile::CLIENT_TYPE_NEXT_OF_KIN ? 'Next of Kin' : 'OFW';

            // --- NOK Full Name: "Last, First M." ---
            $nokFirstName = $row->nok_first_name ?? '';
            $nokLastName = $row->nok_last_name ?? '';
            $nokMiddle = $row->nok_middle_name ?? '';
            $row->nok_full_name = trim($nokLastName.($nokFirstName ? ', '.$nokFirstName : '').($nokMiddle ? ' '.$nokMiddle : ''));

            // --- Strip raw intermediate fields ---
            unset(
                $row->client_first_name,
                $row->client_last_name,
                $row->client_middle_name,
                $row->nok_first_name,
                $row->nok_last_name,
                $row->nok_middle_name,
            );

            $addressResolver = app(PhilippineAddressService::class);
            $row->barangay = $addressResolver->resolve($row->barangay ?? null);
            $row->municipality = $addressResolver->resolve($row->municipality ?? null);
            $row->province = $addressResolver->resolve($row->province ?? null);
            $row->region = $addressResolver->resolve($row->region ?? null);

            return $row;
        });
    }

    public function countCasesExport(?User $user = null, array $filters = []): int
    {
        return (int) $this->getCasesExport($user, $filters)->count();
    }
}
