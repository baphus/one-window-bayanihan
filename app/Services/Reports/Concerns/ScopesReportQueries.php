<?php

namespace App\Services\Reports\Concerns;

use App\Models\CaseFile;
use App\Models\ClientEmployment;
use App\Models\Referral;

trait ScopesReportQueries
{
    /**
     * A scoped report requires the identity that defines that scope.  Keep
     * this check centralized so payloads, options, and individual metrics do
     * not accidentally fall back to an unrestricted query.
     *
     * CASE_MANAGER sees all (no userId needed for scoping).
     * AGENCY must have an agencyId.
     */
    private function hasRequiredRoleScope(?string $userId, ?string $role, ?string $agencyId): bool
    {
        return $role !== 'AGENCY' || (bool) $agencyId;
    }

    private function caseQuery(?string $userId = null, ?string $role = null, ?string $agencyId = null, string $dateScope = 'case_created_at')
    {
        $query = CaseFile::whereNotIn('cases.status', ['DRAFT', 'ARCHIVED']);
        if (! $this->hasRequiredRoleScope($userId, $role, $agencyId)) {
            return $query->whereRaw('1 = 0');
        }
        if ($agencyId) {
            $query->whereIn('cases.id', function ($q) use ($agencyId) {
                $q->select('case_id')->from('referrals')
                    ->where('agcy_id', $agencyId)
                    ->whereNull('deleted_at');
            });
        }

        return $query;
    }

    private function referralQuery(?string $userId = null, ?string $role = null, ?string $agencyId = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at')
    {
        $query = Referral::query();

        if (! $this->hasRequiredRoleScope($userId, $role, $agencyId)) {
            return $query->whereRaw('1 = 0');
        }

        if ($agencyId) {
            $query->where('referrals.agcy_id', $agencyId);
        }

        if ($dateScope === 'case_created_at') {
            // Subquery avoids JOIN â€” prevents ambiguous column errors
            $query->whereIn('referrals.case_id', function ($q) use ($fromDate, $toDate) {
                $q->select('cases.id')->from('cases')
                    ->whereNull('cases.deleted_at');
                if ($fromDate) {
                    $q->whereDate('cases.created_at', '>=', $fromDate);
                }
                if ($toDate) {
                    $q->whereDate('cases.created_at', '<=', $toDate);
                }
            });
        } else {
            if ($fromDate) {
                $query->whereDate($dateScope === 'referral_created_at' ? 'referrals.created_at' : 'referrals.updated_at', '>=', $fromDate);
            }
            if ($toDate) {
                $query->whereDate($dateScope === 'referral_created_at' ? 'referrals.created_at' : 'referrals.updated_at', '<=', $toDate);
            }
        }

        return $query;
    }

    /**
     * Apply geographic filter (province/city) to a query builder.
     * For referral-based queries, uses a subquery on cases->clients->client_addresses.
     * For case-based queries, joins directly.
     */
    /**
     * Restrict a `cases`-based query to the active reporting window.
     *
     * Five panels â€” client type, vulnerability, case status, and both
     * employment breakdowns â€” previously ignored the date and geography
     * filters entirely and reported all-time figures beside panels that
     * honoured them. Exporting those sections would have printed numbers that
     * contradict the date range on the same page.
     *
     * Geography is applied as a subquery rather than the JOIN used by
     * applyGeoFilter(): a client with more than one address would otherwise be
     * counted once per address and inflate every count(*) here.
     */
    private function applyCaseWindow($query, ?string $fromDate, ?string $toDate, ?string $province, ?string $city): void
    {
        if ($fromDate) {
            $query->whereDate('cases.created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('cases.created_at', '<=', $toDate);
        }

        if (! $province && ! $city) {
            return;
        }

        $query->whereIn('cases.id', function ($q) use ($province, $city) {
            $q->select('cases.id')->from('cases')
                ->join('clients', 'clients.id', '=', 'cases.client_id')
                ->join('client_addresses', 'client_addresses.client_id', '=', 'clients.id');
            if ($province) {
                $q->where('client_addresses.province', $province);
            }
            if ($city) {
                $q->where('client_addresses.city_municipality', $city);
            }
        });
    }

    private function applyGeoFilter($query, ?string $province, ?string $city, string $baseTable = 'referrals'): void
    {
        if (! $province && ! $city) {
            return;
        }

        if ($baseTable === 'referrals') {
            $query->select('referrals.*')->whereIn('referrals.case_id', function ($q) use ($province, $city) {
                $q->select('cases.id')->from('cases')
                    ->join('clients', 'clients.id', '=', 'cases.client_id')
                    ->join('client_addresses', 'client_addresses.client_id', '=', 'clients.id');
                if ($province) {
                    $q->where('client_addresses.province', $province);
                }
                if ($city) {
                    $q->where('client_addresses.city_municipality', $city);
                }
            });
        } else {
            $query->join('clients', 'clients.id', '=', 'cases.client_id')
                ->join('client_addresses', 'client_addresses.client_id', '=', 'clients.id');
            if ($province) {
                $query->where('client_addresses.province', $province);
            }
            if ($city) {
                $query->where('client_addresses.city_municipality', $city);
            }
        }
    }

    /**
     * Subquery of client IDs whose cases match the active date/role/geo filters.
     * Lets client-level distributions (gender/age) respect the same filters as
     * the rest of the report instead of counting the whole clients table.
     */
    private function filteredClientIds(?string $userId, ?string $role, ?string $fromDate, ?string $toDate, ?string $province, ?string $city, ?string $agencyId = null)
    {
        $q = CaseFile::query()
            ->whereNotIn('cases.status', ['DRAFT', 'ARCHIVED'])
            ->whereNull('cases.deleted_at');

        if ($agencyId) {
            $q->whereIn('cases.id', function ($q) use ($agencyId) {
                $q->select('case_id')->from('referrals')
                    ->where('agcy_id', $agencyId)
                    ->whereNull('deleted_at');
            });
        }
        if ($fromDate) {
            $q->whereDate('cases.created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $q->whereDate('cases.created_at', '<=', $toDate);
        }
        if ($province || $city) {
            $q->join('client_addresses', 'client_addresses.client_id', '=', 'cases.client_id');
            if ($province) {
                $q->where('client_addresses.province', $province);
            }
            if ($city) {
                $q->where('client_addresses.city_municipality', $city);
            }
        }

        return $q->select('cases.client_id');
    }

    /**
     * Case scope for event-based metrics (reopens, actor split).
     *
     * Carries role/agency/geo only â€” no case-date window. Event metrics
     * window the events themselves, so activity on older cases still counts
     * when it falls inside the reporting window.
     */
    private function eventScopedCases(?string $userId = null, ?string $role = null, ?string $agencyId = null, ?string $province = null, ?string $city = null)
    {
        $cases = $this->caseQuery($userId, $role, $agencyId);
        $this->applyCaseWindow($cases, null, null, $province, $city);

        return $cases;
    }

    /**
     * Build the encrypted employment query with the same fail-closed role
     * guards used by the shared case/referral query helpers.
     */
    private function employmentQuery(
        ?string $userId,
        ?string $role,
        ?string $agencyId,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?string $province = null,
        ?string $city = null,
    ) {
        $query = ClientEmployment::query()
            ->where('client_employments.is_deleted', false)
            ->whereNull('client_employments.deleted_at');

        if (! $this->hasRequiredRoleScope($userId, $role, $agencyId)) {
            return $query->whereRaw('1 = 0');
        }

        $needsCaseScope = $agencyId || $fromDate || $toDate || $province || $city;

        // Employment rows hang off the client, so the reporting window reaches
        // them through the client's cases. Without this the employment panels
        // reported every client on record regardless of the selected dates.
        if ($needsCaseScope) {
            $query->whereIn('client_id', function ($q) use ($agencyId, $fromDate, $toDate, $province, $city) {
                $q->select('client_id')->from('cases')
                    ->where('is_deleted', false)
                    ->whereNull('deleted_at')
                    ->whereNotIn('status', ['DRAFT', 'ARCHIVED']);

                if ($agencyId) {
                    $q->whereIn('cases.id', function ($q2) use ($agencyId) {
                        $q2->select('case_id')->from('referrals')
                            ->where('agcy_id', $agencyId)
                            ->where('is_deleted', false)
                            ->whereNull('deleted_at');
                    });
                }

                $this->applyCaseWindow($q, $fromDate, $toDate, $province, $city);
            });
        }

        return $query;
    }
}
