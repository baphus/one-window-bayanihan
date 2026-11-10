<?php

namespace App\Services\Export\Queries;

use App\Casts\EncryptedString;
use App\Enums\UserRole;
use App\Models\CaseFile;
use App\Models\User;
use App\Services\Export\Queries\Concerns\FiltersCaseCategories;
use App\Services\Export\Queries\Concerns\ScopesForExportUser;
use App\Services\PhilippineAddressService;
use App\Support\MultiSelectFilter;
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
                'vulnerability_indicator',
                'nok_vulnerability_indicator',
                'tracker_number',
                'summary',
                'status',
                'closed_at',
                'consent_given_at',
                'user_id',
                'ofw_user_id',
                'client_type',
                DB::raw($this->categoryNamesExpression('cases', $user).' AS categories'),
                'created_at',
                'updated_at',
            ])
            ->where('is_deleted', false);

        if (! $this->isAdmin($user)) {
            if ($user->role !== UserRole::AGENCY->value || ! $user->agcy_id) {
                return collect();
            }
            $query->whereIn('cases.id', function ($q) use ($user) {
                $q->select('case_id')->from('referrals')->where('agcy_id', $user->agcy_id)->where('is_deleted', false)->whereNull('deleted_at');
            });
        }

        return $query->whereNull('deleted_at')->get();
    }

    private function casesExportQuery(?User $user = null, array $filters = [])
    {
        $query = DB::table('cases AS c')
            ->select([
                'c.case_number',
                'c.status',
                'c.tracker_number',
                'c.client_type AS client_type',
                // Vulnerability — combine both indicators; prefer the one matching client type
                DB::raw("CASE
                    WHEN c.client_type = 'NEXT_OF_KIN'
                        THEN COALESCE(NULLIF(c.nok_vulnerability_indicator, 'None'), NULLIF(c.vulnerability_indicator, 'None'), 'None')
                    ELSE COALESCE(NULLIF(c.vulnerability_indicator, 'None'), NULLIF(c.nok_vulnerability_indicator, 'None'), 'None')
                END AS vulnerability"),
                // Client (to-one via FK)
                'c.first_name AS client_first_name',
                'c.last_name AS client_last_name',
                'c.middle_name AS client_middle_name',
                'c.sex AS ofw_sex',
                'c.date_of_birth AS ofw_date_of_birth',
                'c.contact_number AS ofw_contact_number',
                'c.email AS ofw_email',
                // Case address snapshot
                'c.address_barangay AS barangay',
                'c.address_city_municipality AS municipality',
                'c.address_province AS province',
                'c.address_region AS region',
                // Case employment snapshot
                'c.employment_date_of_arrival AS date_of_arrival',
                'c.employment_country AS previous_country',
                'c.employment_position AS work_position',
                // Case summary
                'c.summary AS case_summary',
                DB::raw($this->categoryNamesExpression('c', $user).' AS categories'),
                // Intake date supplies stable age-at-intake interpretation.
                'c.intake_date',
                // Receiving parties — comma-separated agency names from referrals (COALESCE handles empty)
                DB::raw($user?->role === UserRole::AGENCY->value
                    ? "'' AS receiving_parties"
                    : "COALESCE((SELECT STRING_AGG(a.name, ', ') FROM referrals r JOIN agencies a ON r.agcy_id = a.id WHERE r.case_id = c.id AND r.is_deleted = false AND r.deleted_at IS NULL), '') AS receiving_parties"),
                // Case representative
                'c.nok_name AS nok_full_name',

                'c.nok_contact_number AS nok_contact_number',
                'c.nok_email AS nok_email',
            ])
            ->where('c.is_deleted', false)->whereNull('c.deleted_at')
            ->whereNotIn('c.status', [CaseFile::STATUS_PENDING_REVIEW])
            ->orderBy('c.created_at', 'desc');

        // ADMIN/CASE_MANAGER: all. AGENCY: own referral cases only.
        if ($user?->role === UserRole::AGENCY->value) {
            if ($user->agcy_id) {
                $query->whereIn('c.id', function ($q) use ($user) {
                    $q->select('case_id')
                        ->from('referrals')
                        ->where('agcy_id', $user->agcy_id)
                        ->where('is_deleted', false)
                        ->whereNull('deleted_at');
                });
            } else {
                $query->whereRaw('1=0');
            }
        }

        if ($user !== null && ! in_array($user->role, [UserRole::ADMIN->value, UserRole::CASE_MANAGER->value, UserRole::AGENCY->value], true)) {
            $query->whereRaw('1 = 0');
        }

        // --- Apply optional filters ---
        //
        // These mirror CaseService::getCases exactly: list filters accept the
        // panel's array form (and the legacy scalar form), and ARCHIVED is only
        // excluded when no status filter was chosen so an archived export
        // returns the archived rows the page is showing.
        $statuses = MultiSelectFilter::ids($filters['status'] ?? null);
        if ($statuses !== []) {
            $query->whereIn('c.status', $statuses);
        } else {
            $query->where('c.status', '!=', 'ARCHIVED');
        }

        $clientTypes = MultiSelectFilter::ids($filters['client_type'] ?? null);
        if ($clientTypes !== []) {
            $query->whereIn('c.client_type', $clientTypes);
        }

        $vulnerabilities = MultiSelectFilter::ids($filters['vulnerability_indicator'] ?? null);
        if ($vulnerabilities !== []) {
            $query->where(function ($outer) use ($vulnerabilities) {
                foreach ($vulnerabilities as $indicator) {
                    $outer->orWhere(function ($q) use ($indicator) {
                        $q->where('c.vulnerability_indicator', 'LIKE', "%{$indicator}%")
                            ->orWhere('c.nok_vulnerability_indicator', 'LIKE', "%{$indicator}%");
                    });
                }
            });
        }

        $userIds = MultiSelectFilter::ids($filters['user_id'] ?? null);
        if ($userIds !== []) {
            $query->whereIn('c.user_id', $userIds);
        }

        $agencyIds = MultiSelectFilter::ids($filters['agcy_id'] ?? null);
        if ($agencyIds !== []) {
            $query->whereIn('c.id', function ($q) use ($agencyIds) {
                $q->select('case_id')
                    ->from('referrals')
                    ->whereIn('agcy_id', $agencyIds)
                    ->where('is_deleted', false)
                    ->whereNull('deleted_at');
            });
        }
        if (! empty($filters['age_min_days']) && is_numeric($filters['age_min_days'])) {
            $query->where('c.created_at', '<=', now()->subDays((int) $filters['age_min_days']));
        }
        if (($filters['referral_state'] ?? null) === 'none') {
            $query->whereNotIn('c.id', function ($q) {
                $q->select('case_id')
                    ->from('referrals')
                    ->where('is_deleted', false)
                    ->whereNull('deleted_at');
            });
        }
        $this->applyCategoryFilter($query, 'c', $filters, $user);

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
                    ->orWhere('c.first_name', 'ilike', "%{$search}%")
                    ->orWhere('c.last_name', 'ilike', "%{$search}%");
            });
        }

        return $query;
    }

    /**
     * Get enriched case intake records for business export, with referral
     * categories, case address/employment snapshots and agency parties.
     * No IDs or system fields. ADMIN/CASE_MANAGER see all; AGENCY sees own.
     *
     * @param  array  $filters  Optional: status, search, client_type, vulnerability_indicator,
     *                          user_id, agcy_id, category_id/category_ids,
     *                          age_min_days, referral_state
     */
    public function getCasesExport(?User $user = null, array $filters = []): Collection
    {
        // Safety cap — prevents memory exhaustion on unbounded exports.
        // The DataExportService should eventually support streaming for larger sets.
        return $this->casesExportQuery($user, $filters)->limit(10000)->get()->map(function ($row) {
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
                    $row->ofw_age = (string) (int) CarbonImmutable::parse($dob)->diffInYears(CarbonImmutable::parse($row->intake_date), true);
                } catch (\Exception $e) {
                    Log::debug('DataExportQueries: unparseable date of birth in export', [
                        'case_number' => $row->case_number,
                    ]);
                    $row->ofw_age = '';
                }
            }

            // --- Client Type display label ---
            $row->client_type = $row->client_type === 'NEXT_OF_KIN' ? 'Next of Kin' : 'OFW';

            // --- Strip raw intermediate fields ---
            unset(
                $row->client_first_name,
                $row->client_last_name,
                $row->client_middle_name,
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
        return (int) $this->casesExportQuery($user, $filters)->count();
    }
}
