<?php

namespace App\Services\Export;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DataExportQueries
{
    private function isAdmin(?User $user): bool
    {
        return $user === null || $user->role === 'ADMIN';
    }

    /**
     * Get cases. ADMIN: all. CASE_MANAGER: own cases only (via user_id).
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
                'created_at',
                'updated_at',
            ])
            ->where('is_deleted', false);

        if (! $this->isAdmin($user)) {
            $query->where('user_id', $user->id);
        }

        return $query->get();
    }

    /**
     * Get enriched cases for business export — joins related client data,
     * addresses, employments, next-of-kin, case issues, and referral parties.
     * No IDs or system fields. Administrators see all; Case Managers see own.
     *
     * @param  array  $filters  Optional: status, search, client_type, vulnerability_indicator,
     *                          user_id, agcy_id, category_id, case_issue_id
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
                    WHEN c.client_type = 'NOK'
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
                // Case issue (to-one)
                'ci.name AS issue_concern',
                // Receiving parties — comma-separated agency names from referrals (COALESCE handles empty)
                DB::raw("COALESCE((SELECT STRING_AGG(a.name, ', ') FROM referrals r JOIN agencies a ON r.agcy_id = a.id WHERE r.case_id = c.id AND r.is_deleted = false), '') AS receiving_parties"),
                // Next-of-kin — to-many, take primary first, then by sort_order
                DB::raw('(SELECT nok.first_name FROM next_of_kin nok WHERE nok.client_id = c.client_id AND nok.is_deleted = false ORDER BY nok.is_primary DESC, nok.sort_order ASC LIMIT 1) AS nok_first_name'),
                DB::raw('(SELECT nok.last_name FROM next_of_kin nok WHERE nok.client_id = c.client_id AND nok.is_deleted = false ORDER BY nok.is_primary DESC, nok.sort_order ASC LIMIT 1) AS nok_last_name'),
                DB::raw('(SELECT nok.middle_initial FROM next_of_kin nok WHERE nok.client_id = c.client_id AND nok.is_deleted = false ORDER BY nok.is_primary DESC, nok.sort_order ASC LIMIT 1) AS nok_middle_initial'),
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

        if (! $this->isAdmin($user)) {
            $query->where('c.user_id', $user->id);
        }

        // --- Apply optional filters ---
        if (! empty($filters['status'])) {
            $query->where('c.status', $filters['status']);
        }
        if (! empty($filters['client_type'])) {
            // Normalize: treat any non-NOK value as OFW
            if ($filters['client_type'] === 'NOK') {
                $query->where('c.client_type', 'NOK');
            } else {
                $query->where('c.client_type', '!=', 'NOK');
            }
        }
        if (! empty($filters['vulnerability_indicator'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('c.vulnerability_indicator', $filters['vulnerability_indicator'])
                    ->orWhere('c.nok_vulnerability_indicator', $filters['vulnerability_indicator']);
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
        if (! empty($filters['category_id'])) {
            $query->where('c.category_id', $filters['category_id']);
        }
        if (! empty($filters['case_issue_id'])) {
            $query->where('c.case_issue_id', $filters['case_issue_id']);
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

        return $query->get()->map(function ($row) {
            // Convert stdClass to a mutable object we can add properties to
            $row = (object) $row;

            // --- OFW Full Name: "Last, First Middle" ---
            $firstName = $row->client_first_name ?? '';
            $lastName = $row->client_last_name ?? '';
            $middleName = $row->client_middle_name ?? '';
            $row->ofw_full_name = trim($lastName.($firstName ? ', '.$firstName : '').($middleName ? ' '.$middleName : ''));

            // --- OFW Age ---
            $row->ofw_age = '';
            if (! empty($row->ofw_date_of_birth)) {
                try {
                    $dob = $row->ofw_date_of_birth instanceof \DateTimeInterface
                        ? $row->ofw_date_of_birth
                        : new \DateTimeImmutable($row->ofw_date_of_birth);
                    $row->ofw_age = (string) CarbonImmutable::parse($dob)->age;
                } catch (\Exception) {
                    $row->ofw_age = '';
                }
            }

            // --- Client Type display label ---
            // Normalize: only OFW and NOK are valid; treat anything non-NOK as OFW
            $row->client_type = $row->client_type === 'NOK' ? 'Next of Kin' : 'OFW';

            // --- NOK Full Name: "Last, First M." ---
            $nokFirstName = $row->nok_first_name ?? '';
            $nokLastName = $row->nok_last_name ?? '';
            $nokMiddle = $row->nok_middle_initial ?? '';
            $row->nok_full_name = trim($nokLastName.($nokFirstName ? ', '.$nokFirstName : '').($nokMiddle ? ' '.$nokMiddle : ''));

            // --- Strip raw intermediate fields ---
            unset(
                $row->client_first_name,
                $row->client_last_name,
                $row->client_middle_name,
                $row->nok_first_name,
                $row->nok_last_name,
                $row->nok_middle_initial,
            );

            return $row;
        });
    }

    /**
     * Get clients. ADMIN: all. CASE_MANAGER: clients linked to their cases (via cases.client_id).
     */
    public function getClients(?User $user = null): Collection
    {
        $query = DB::table('clients')
            ->select([
                'id',
                'first_name',
                'last_name',
                'middle_name',
                'suffix',
                'date_of_birth',
                'sex',
                'email',
                'contact_number',
                'created_at',
                'updated_at',
            ])
            ->where('is_deleted', false);

        if (! $this->isAdmin($user)) {
            if ($user && $user->role === 'AGENCY' && $user->agcy_id) {
                $query->whereIn('id', function ($q) use ($user) {
                    $q->select('client_id')
                        ->from('cases')
                        ->where('is_deleted', false)
                        ->whereNotNull('client_id')
                        ->whereIn('id', function ($q2) use ($user) {
                            $q2->select('case_id')
                                ->from('referrals')
                                ->where('agcy_id', $user->agcy_id);
                        });
                });
            } else {
                $query->whereIn('id', function ($q) use ($user) {
                    $q->select('client_id')
                        ->from('cases')
                        ->where('user_id', $user->id)
                        ->where('is_deleted', false)
                        ->whereNotNull('client_id');
                });
            }
        }

        return $query->get();
    }

    /**
     * Get enriched clients for business export — no IDs or system fields.
     * Joins case info, addresses, employments, next-of-kin, and referral parties.
     * ADMIN: all. CASE_MANAGER: clients on their cases. AGENCY: clients with referrals to their agency.
     *
     * @param  array  $filters  Optional: search, sex, client_type
     */
    public function getClientsExport(?User $user = null, array $filters = []): Collection
    {
        $query = DB::table('clients AS cl')
            ->select([
                // Client info
                'cl.first_name',
                'cl.last_name',
                'cl.middle_name',
                'cl.sex',
                'cl.date_of_birth',
                'cl.contact_number',
                'cl.email',
                // Case info — latest case per client via scalar subquery
                DB::raw('(SELECT c.case_number FROM cases c WHERE c.client_id = cl.id AND c.is_deleted = false AND c.status != \'ARCHIVED\' ORDER BY c.created_at DESC, c.id DESC LIMIT 1) AS case_number'),
                DB::raw('(SELECT c.status FROM cases c WHERE c.client_id = cl.id AND c.is_deleted = false AND c.status != \'ARCHIVED\' ORDER BY c.created_at DESC, c.id DESC LIMIT 1) AS case_status'),
                DB::raw('(SELECT c.tracker_number FROM cases c WHERE c.client_id = cl.id AND c.is_deleted = false AND c.status != \'ARCHIVED\' ORDER BY c.created_at DESC, c.id DESC LIMIT 1) AS tracker_number'),
                DB::raw("(SELECT CASE
                    WHEN c.client_type = 'NOK' THEN COALESCE(NULLIF(c.nok_vulnerability_indicator, 'None'), NULLIF(c.vulnerability_indicator, 'None'), 'None')
                    ELSE COALESCE(NULLIF(c.vulnerability_indicator, 'None'), NULLIF(c.nok_vulnerability_indicator, 'None'), 'None')
                END FROM cases c WHERE c.client_id = cl.id AND c.is_deleted = false AND c.status != 'ARCHIVED' ORDER BY c.created_at DESC, c.id DESC LIMIT 1) AS vulnerability"),
                DB::raw("(SELECT CASE
                    WHEN c.client_type = 'NOK' THEN 'Next of Kin'
                    ELSE 'OFW'
                END FROM cases c WHERE c.client_id = cl.id AND c.is_deleted = false AND c.status != 'ARCHIVED' ORDER BY c.created_at DESC, c.id DESC LIMIT 1) AS client_type"),
                // Case issue — from latest case
                DB::raw('(SELECT ci.name FROM case_issues ci JOIN cases c3 ON c3.case_issue_id = ci.id WHERE c3.client_id = cl.id AND c3.is_deleted = false AND c3.status != \'ARCHIVED\' ORDER BY c3.created_at DESC, c3.id DESC LIMIT 1) AS issue_concern'),
                // Address — first active
                DB::raw('(SELECT ca.street FROM client_addresses ca WHERE ca.client_id = cl.id AND ca.is_deleted = false LIMIT 1) AS street'),
                DB::raw('(SELECT ca.barangay FROM client_addresses ca WHERE ca.client_id = cl.id AND ca.is_deleted = false LIMIT 1) AS barangay'),
                DB::raw('(SELECT ca.city_municipality FROM client_addresses ca WHERE ca.client_id = cl.id AND ca.is_deleted = false LIMIT 1) AS municipality'),
                DB::raw('(SELECT ca.province FROM client_addresses ca WHERE ca.client_id = cl.id AND ca.is_deleted = false LIMIT 1) AS province'),
                DB::raw('(SELECT ca.region FROM client_addresses ca WHERE ca.client_id = cl.id AND ca.is_deleted = false LIMIT 1) AS region'),
                // Employment — most recent
                DB::raw('(SELECT ce.date_of_arrival FROM client_employments ce WHERE ce.client_id = cl.id AND ce.is_deleted = false ORDER BY ce.created_at DESC LIMIT 1) AS date_of_arrival'),
                DB::raw('(SELECT ce.country FROM client_employments ce WHERE ce.client_id = cl.id AND ce.is_deleted = false ORDER BY ce.created_at DESC LIMIT 1) AS previous_country'),
                DB::raw('(SELECT ce.position FROM client_employments ce WHERE ce.client_id = cl.id AND ce.is_deleted = false ORDER BY ce.created_at DESC LIMIT 1) AS work_position'),
                // Receiving parties — comma-separated from referrals on all their cases
                DB::raw("COALESCE((SELECT STRING_AGG(a.name, ', ') FROM referrals r JOIN agencies a ON r.agcy_id = a.id JOIN cases c4 ON r.case_id = c4.id WHERE c4.client_id = cl.id AND r.is_deleted = false), '') AS receiving_parties"),
                // Next of kin — primary first, then sort_order
                DB::raw('(SELECT nok.first_name FROM next_of_kin nok WHERE nok.client_id = cl.id AND nok.is_deleted = false ORDER BY nok.is_primary DESC, nok.sort_order ASC LIMIT 1) AS nok_first_name'),
                DB::raw('(SELECT nok.last_name FROM next_of_kin nok WHERE nok.client_id = cl.id AND nok.is_deleted = false ORDER BY nok.is_primary DESC, nok.sort_order ASC LIMIT 1) AS nok_last_name'),
                DB::raw('(SELECT nok.middle_initial FROM next_of_kin nok WHERE nok.client_id = cl.id AND nok.is_deleted = false ORDER BY nok.is_primary DESC, nok.sort_order ASC LIMIT 1) AS nok_middle_initial'),
                DB::raw('(SELECT nok.phone_number FROM next_of_kin nok WHERE nok.client_id = cl.id AND nok.is_deleted = false ORDER BY nok.is_primary DESC, nok.sort_order ASC LIMIT 1) AS nok_contact_number'),
                DB::raw('(SELECT nok.email FROM next_of_kin nok WHERE nok.client_id = cl.id AND nok.is_deleted = false ORDER BY nok.is_primary DESC, nok.sort_order ASC LIMIT 1) AS nok_email'),
            ])
            ->where('cl.is_deleted', false)
            ->orderBy('cl.created_at', 'desc');

        // Role-based scoping
        if (! $this->isAdmin($user)) {
            if ($user && $user->role === 'AGENCY' && $user->agcy_id) {
                $query->whereIn('cl.id', function ($q) use ($user) {
                    $q->select('c5.client_id')
                        ->from('cases AS c5')
                        ->where('c5.is_deleted', false)
                        ->whereNotNull('c5.client_id')
                        ->whereIn('c5.id', function ($q2) use ($user) {
                            $q2->select('r2.case_id')
                                ->from('referrals AS r2')
                                ->where('r2.agcy_id', $user->agcy_id);
                        });
                });
            } else {
                $query->whereIn('cl.id', function ($q) use ($user) {
                    $q->select('c6.client_id')
                        ->from('cases AS c6')
                        ->where('c6.user_id', $user->id)
                        ->where('c6.is_deleted', false)
                        ->whereNotNull('c6.client_id');
                });
            }
        }

        // --- Apply optional filters ---
        if (! empty($filters['sex'])) {
            $query->where('cl.sex', $filters['sex']);
        }
        if (! empty($filters['client_type'])) {
            $query->whereIn('cl.id', function ($q) use ($filters) {
                $q->select('c7.client_id')
                    ->from('cases AS c7')
                    ->where('c7.client_type', $filters['client_type'])
                    ->where('c7.is_deleted', false)
                    ->whereNotNull('c7.client_id');
            });
        }
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('cl.first_name', 'ilike', "%{$search}%")
                    ->orWhere('cl.last_name', 'ilike', "%{$search}%")
                    ->orWhere('cl.middle_name', 'ilike', "%{$search}%")
                    ->orWhere('cl.contact_number', 'ilike', "%{$search}%")
                    ->orWhere('cl.email', 'ilike', "%{$search}%");
            });
        }

        return $query->get()->map(function ($row) {
            $row = (object) $row;

            // --- Full Name: "Last, First Middle" ---
            $firstName = $row->first_name ?? '';
            $lastName = $row->last_name ?? '';
            $middleName = $row->middle_name ?? '';
            $row->full_name = trim($lastName.($firstName ? ', '.$firstName : '').($middleName ? ' '.$middleName : ''));

            // --- Age ---
            $row->age = '';
            if (! empty($row->date_of_birth)) {
                try {
                    $dob = $row->date_of_birth instanceof \DateTimeInterface
                        ? $row->date_of_birth
                        : new \DateTimeImmutable($row->date_of_birth);
                    $row->age = (string) CarbonImmutable::parse($dob)->age;
                } catch (\Exception) {
                    $row->age = '';
                }
            }

            // --- Full Address ---
            $parts = array_filter([
                $row->street ?? '',
                $row->barangay ?? '',
                $row->municipality ?? '',
                $row->province ?? '',
                $row->region ?? '',
            ]);
            $row->full_address = implode(', ', $parts);

            // --- NOK Full Name: "Last, First M." ---
            $nokFirstName = $row->nok_first_name ?? '';
            $nokLastName = $row->nok_last_name ?? '';
            $nokMiddle = $row->nok_middle_initial ?? '';
            $row->nok_full_name = trim($nokLastName.($nokFirstName ? ', '.$nokFirstName : '').($nokMiddle ? ' '.$nokMiddle : ''));

            // Strip raw intermediate fields
            unset(
                $row->first_name,
                $row->last_name,
                $row->middle_name,
                $row->street,
                $row->barangay,
                $row->municipality,
                $row->province,
                $row->region,
                $row->nok_first_name,
                $row->nok_last_name,
                $row->nok_middle_initial,
            );

            return $row;
        });
    }

    /**
     * Get referrals. ADMIN: all. CASE_MANAGER: referrals on their cases (via case_id → cases.user_id).
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
        $query = DB::table('referrals AS r')
            ->select([
                // Case info
                'c.case_number',
                'c.status AS case_status',
                'c.tracker_number',
                'c.client_type',
                DB::raw("CASE
                    WHEN c.client_type = 'NOK'
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
                DB::raw('(SELECT nok.middle_initial FROM next_of_kin nok WHERE nok.client_id = c.client_id AND nok.is_deleted = false ORDER BY nok.is_primary DESC, nok.sort_order ASC LIMIT 1) AS nok_middle_initial'),
                DB::raw('(SELECT nok.relationship FROM next_of_kin nok WHERE nok.client_id = c.client_id AND nok.is_deleted = false ORDER BY nok.is_primary DESC, nok.sort_order ASC LIMIT 1) AS nok_relationship'),
                DB::raw('(SELECT nok.phone_number FROM next_of_kin nok WHERE nok.client_id = c.client_id AND nok.is_deleted = false ORDER BY nok.is_primary DESC, nok.sort_order ASC LIMIT 1) AS nok_contact_number'),
                DB::raw('(SELECT nok.email FROM next_of_kin nok WHERE nok.client_id = c.client_id AND nok.is_deleted = false ORDER BY nok.is_primary DESC, nok.sort_order ASC LIMIT 1) AS nok_email'),
                // Agency
                'a.name AS referred_agency',
                // Referral info
                'r.created_at AS date_referred',
                'r.status AS referral_status',
                // Issue/Concern
                'ci.name AS issue_concern',
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

        if (! $this->isAdmin($user)) {
            $query->whereIn('r.case_id', function ($q) use ($user) {
                $q->select('id')
                    ->from('cases')
                    ->where('user_id', $user->id)
                    ->where('is_deleted', false);
            });
        }

        return $query->get()->map(function ($row) {
            $row = (object) $row;

            // --- Client Full Name: "Last, First Middle" ---
            $firstName = $row->client_first_name ?? '';
            $lastName = $row->client_last_name ?? '';
            $middleName = $row->client_middle_name ?? '';
            $row->client_full_name = trim($lastName.($firstName ? ', '.$firstName : '').($middleName ? ' '.$middleName : ''));

            // --- Client Age ---
            $row->client_age = '';
            if (! empty($row->client_date_of_birth)) {
                try {
                    $dob = $row->client_date_of_birth instanceof \DateTimeInterface
                        ? $row->client_date_of_birth
                        : new \DateTimeImmutable($row->client_date_of_birth);
                    $row->client_age = (string) CarbonImmutable::parse($dob)->age;
                } catch (\Exception) {
                    $row->client_age = '';
                }
            }

            // --- Client Type display label ---
            // Normalize: only OFW and NOK are valid; treat anything non-NOK as OFW
            $row->client_type = $row->client_type === 'NOK' ? 'Next of Kin' : 'OFW';

            // --- Client Full Address ---
            $parts = array_filter([
                $row->street ?? '',
                $row->barangay ?? '',
                $row->municipality ?? '',
                $row->province ?? '',
                $row->region ?? '',
            ]);
            $row->client_full_address = implode(', ', $parts);

            // --- NOK Full Name: "Last, First M." ---
            $nokFirstName = $row->nok_first_name ?? '';
            $nokLastName = $row->nok_last_name ?? '';
            $nokMiddle = $row->nok_middle_initial ?? '';
            $row->nok_full_name = trim($nokLastName.($nokFirstName ? ', '.$nokFirstName : '').($nokMiddle ? ' '.$nokMiddle : ''));

            // --- Date Referred display ---
            $row->date_referred = $row->date_referred instanceof \DateTimeInterface
                ? $row->date_referred->format('Y-m-d')
                : (($row->date_referred && str_contains((string) $row->date_referred, ' '))
                    ? substr((string) $row->date_referred, 0, 10)
                    : (string) ($row->date_referred ?? ''));

            // Strip raw intermediate fields
            unset(
                $row->client_first_name,
                $row->client_last_name,
                $row->client_middle_name,
                $row->street,
                $row->nok_first_name,
                $row->nok_last_name,
                $row->nok_middle_initial,
            );

            return $row;
        });
    }

    /**
     * Get users. ADMIN only — returns empty collection for any other role.
     */
    public function getUsers(?User $user = null): Collection
    {
        if (! $this->isAdmin($user)) {
            return collect();
        }

        return DB::table('users')
            ->select([
                'id',
                'name',
                'email',
                'role',
                'agcy_id',
                'is_active',
                'contact_number',
                'position',
                'department',
                'office_location',
                'created_at',
                'updated_at',
            ])
            ->where('is_deleted', false)
            ->get();
    }

    /**
     * Get agencies. All roles receive all records (reference data).
     */
    public function getAgencies(): Collection
    {
        return DB::table('agencies')
            ->select([
                'id',
                'name',
                'short',
                'slug',
                'description',
                'contact_info',
                'is_active',
                'is_default',
                'created_at',
                'updated_at',
            ])
            ->where('is_deleted', false)
            ->get();
    }

    /**
     * Get services. All roles receive all records (reference data).
     */
    public function getServices(): Collection
    {
        return DB::table('services')
            ->select([
                'id',
                'name',
                'description',
                'processing_days',
                'agcy_id',
                'created_at',
                'updated_at',
            ])
            ->where('is_deleted', false)
            ->get();
    }

    /**
     * Get milestones. ADMIN: all. CASE_MANAGER: via referrals → cases → user_id.
     */
    public function getMilestones(?User $user = null): Collection
    {
        $query = DB::table('milestones')
            ->select([
                'id',
                'title',
                'description',
                'refr_id',
                'user_id',
                'created_at',
                'updated_at',
            ])
            ->where('is_deleted', false);

        if (! $this->isAdmin($user)) {
            $query->whereIn('refr_id', function ($q) use ($user) {
                $q->select('id')
                    ->from('referrals')
                    ->where('is_deleted', false)
                    ->whereIn('case_id', function ($q2) use ($user) {
                        $q2->select('id')
                            ->from('cases')
                            ->where('user_id', $user->id)
                            ->where('is_deleted', false);
                    });
            });
        }

        return $query->get();
    }

    /**
     * Get next-of-kin. ADMIN: all. CASE_MANAGER: via clients → cases → user_id.
     */
    public function getNextOfKins(?User $user = null): Collection
    {
        $query = DB::table('next_of_kin')
            ->select([
                'id',
                'client_id',
                'first_name',
                'middle_initial',
                'last_name',
                'is_primary',
                'relationship',
                'phone_number',
                'email',
                'full_address',
                'region',
                'province',
                'city_municipality',
                'barangay',
                'street',
                'sort_order',
                'created_at',
                'updated_at',
            ])
            ->where('is_deleted', false);

        if (! $this->isAdmin($user)) {
            $query->whereIn('client_id', function ($q) use ($user) {
                $q->select('client_id')
                    ->from('cases')
                    ->where('user_id', $user->id)
                    ->where('is_deleted', false)
                    ->whereNotNull('client_id');
            });
        }

        return $query->get();
    }

    /**
     * Get feedback. ADMIN: all. CASE_MANAGER: via case_id → cases → user_id.
     * Note: feedback table does not use SoftDeleteFlag — no is_deleted filter.
     */
    public function getFeedbacks(?User $user = null): Collection
    {
        $query = DB::table('feedback')
            ->select([
                'id',
                'case_id',
                'agency_id',
                'service_name',
                'overall_rating',
                'comments',
                'created_at',
                'updated_at',
            ]);

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
     * Get feedback with SERVQUAL dimension averages for export.
     * ADMIN: all. CASE_MANAGER/AGENCY: scoped.
     * Uses the feedback_servqual_responses table for dimension calculations.
     *
     * @param  array  $filters  Optional filters: agency_id, date_from, date_to
     */
    public function getFeedbackWithServqual(?User $user = null, array $filters = []): Collection
    {
        // Base query joins feedback with referrals, cases, clients, agencies
        $query = DB::table('feedback')
            ->select([
                'feedback.id',
                'feedback.case_id',
                'feedback.referral_id',
                DB::raw("CONCAT(clients.first_name, ' ', clients.last_name) AS client_name"),
                'agencies.name AS agency_name',
                'referrals.status AS referral_status',
                'feedback.service_name',
                'feedback.overall_rating',
                'feedback.comments',
                'feedback.created_at',
                // SERVQUAL dimension averages (subquery approach)
                DB::raw('(SELECT ROUND(AVG(perception::numeric), 2) FROM feedback_servqual_responses WHERE feedback_id = feedback.id AND dimension = \'Tangibles\') AS tangibles_avg'),
                DB::raw('(SELECT ROUND(AVG(perception::numeric), 2) FROM feedback_servqual_responses WHERE feedback_id = feedback.id AND dimension = \'Reliability\') AS reliability_avg'),
                DB::raw('(SELECT ROUND(AVG(perception::numeric), 2) FROM feedback_servqual_responses WHERE feedback_id = feedback.id AND dimension = \'Responsiveness\') AS responsiveness_avg'),
                DB::raw('(SELECT ROUND(AVG(perception::numeric), 2) FROM feedback_servqual_responses WHERE feedback_id = feedback.id AND dimension = \'Assurance\') AS assurance_avg'),
                DB::raw('(SELECT ROUND(AVG(perception::numeric), 2) FROM feedback_servqual_responses WHERE feedback_id = feedback.id AND dimension = \'Empathy\') AS empathy_avg'),
            ])
            ->leftJoin('referrals', 'feedback.referral_id', '=', 'referrals.id')
            ->leftJoin('cases', 'feedback.case_id', '=', 'cases.id')
            ->leftJoin('clients', 'cases.client_id', '=', 'clients.id')
            ->leftJoin('agencies', 'feedback.agency_id', '=', 'agencies.id');

        // Role-based scoping
        if (! $this->isAdmin($user)) {
            if ($user && $user->role === 'AGENCY' && $user->agcy_id) {
                $query->where('feedback.agency_id', $user->agcy_id);
            } else {
                $query->whereIn('feedback.case_id', function ($q) use ($user) {
                    $q->select('id')
                        ->from('cases')
                        ->where('user_id', $user->id)
                        ->where('is_deleted', false);
                });
            }
        }

        // Apply optional filters
        if (! empty($filters['agency_id'])) {
            $query->where('feedback.agency_id', $filters['agency_id']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('feedback.created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('feedback.created_at', '<=', $filters['date_to']);
        }

        return $query->orderBy('feedback.created_at', 'desc')->get();
    }

    /**
     * Get case documents. ADMIN: all. CASE_MANAGER: via case_id → cases → user_id.
     */
    public function getCaseDocuments(?User $user = null): Collection
    {
        $query = DB::table('case_documents')
            ->select([
                'id',
                'file_name',
                'file_path',
                'file_type',
                'case_id',
                'user_id',
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
     * Get client addresses. ADMIN: all. CASE_MANAGER: via client_id → cases → user_id.
     */
    public function getClientAddresses(?User $user = null): Collection
    {
        $query = DB::table('client_addresses')
            ->select([
                'id',
                'client_id',
                'region',
                'province',
                'city_municipality',
                'barangay',
                'street',
                'created_at',
                'updated_at',
            ])
            ->where('is_deleted', false);

        if (! $this->isAdmin($user)) {
            $query->whereIn('client_id', function ($q) use ($user) {
                $q->select('client_id')
                    ->from('cases')
                    ->where('user_id', $user->id)
                    ->where('is_deleted', false)
                    ->whereNotNull('client_id');
            });
        }

        return $query->get();
    }

    /**
     * Get client employments. ADMIN: all. CASE_MANAGER: via client_id → cases → user_id.
     */
    public function getClientEmployments(?User $user = null): Collection
    {
        $query = DB::table('client_employments')
            ->select([
                'id',
                'client_id',
                'employer_name',
                'position',
                'last_position',
                'country',
                'last_country',
                'start_date',
                'end_date',
                'date_of_arrival',
                'created_at',
                'updated_at',
            ])
            ->where('is_deleted', false);

        if (! $this->isAdmin($user)) {
            $query->whereIn('client_id', function ($q) use ($user) {
                $q->select('client_id')
                    ->from('cases')
                    ->where('user_id', $user->id)
                    ->where('is_deleted', false)
                    ->whereNotNull('client_id');
            });
        }

        return $query->get();
    }

    /**
     * Get case categories. All roles receive all records (reference data).
     */
    public function getCaseCategories(): Collection
    {
        return DB::table('case_categories')
            ->select([
                'id',
                'name',
                'description',
                'color',
                'sort_order',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->where('is_deleted', false)
            ->get();
    }

    /**
     * Get case statuses. All roles receive all records (reference data).
     */
    public function getCaseStatuses(): Collection
    {
        return DB::table('case_statuses')
            ->select([
                'id',
                'name',
                'slug',
                'type',
                'color',
                'sort_order',
                'is_system',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->where('is_deleted', false)
            ->get();
    }
}
