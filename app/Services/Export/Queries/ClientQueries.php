<?php

namespace App\Services\Export\Queries;

use App\Casts\EncryptedString;
use App\Enums\UserRole;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\User;
use App\Services\Export\Queries\Concerns\FiltersCaseCategories;
use App\Services\PhilippineAddressService;
use App\Support\CategoryFilter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ClientQueries
{
    use FiltersCaseCategories;

    /**
     * Get clients. ADMIN/CASE_MANAGER: all. AGENCY: clients linked to their referrals.
     */
    public function getClients(?User $user = null): Collection
    {
        $query = Client::query()
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
            ->where('is_deleted', false)
            // Exports leave the system, so unaccepted self-filed intakes must not
            // ride along in a client extract either.
            ->withoutUnacceptedIntake();

        // ADMIN/CASE_MANAGER: all. AGENCY: clients linked to their referrals.
        if ($user?->role === UserRole::AGENCY->value) {
            if ($user->agcy_id) {
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
                $query->whereRaw('1=0');
            }
        }

        return $query->get();
    }

    private function clientsExportQuery(?User $user = null, array $filters = [])
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
                    WHEN c.client_type = 'NEXT_OF_KIN' THEN COALESCE(NULLIF(c.nok_vulnerability_indicator, 'None'), NULLIF(c.vulnerability_indicator, 'None'), 'None')
                    ELSE COALESCE(NULLIF(c.vulnerability_indicator, 'None'), NULLIF(c.nok_vulnerability_indicator, 'None'), 'None')
                END FROM cases c WHERE c.client_id = cl.id AND c.is_deleted = false AND c.status != 'ARCHIVED' ORDER BY c.created_at DESC, c.id DESC LIMIT 1) AS vulnerability"),
                DB::raw("(SELECT CASE
                    WHEN c.client_type = 'NEXT_OF_KIN' THEN 'Next of Kin'
                    ELSE 'OFW'
                END FROM cases c WHERE c.client_id = cl.id AND c.is_deleted = false AND c.status != 'ARCHIVED' ORDER BY c.created_at DESC, c.id DESC LIMIT 1) AS client_type"),
                // Case issue — from latest case
                DB::raw("COALESCE((SELECT ci.name FROM case_issues ci JOIN cases c3 ON c3.case_issue_id = ci.id WHERE c3.client_id = cl.id AND c3.is_deleted = false AND c3.status != 'ARCHIVED' ORDER BY c3.created_at DESC, c3.id DESC LIMIT 1), '—') AS issue_concern"),
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
                DB::raw('(SELECT nok.middle_name FROM next_of_kin nok WHERE nok.client_id = cl.id AND nok.is_deleted = false ORDER BY nok.is_primary DESC, nok.sort_order ASC LIMIT 1) AS nok_middle_name'),
                DB::raw('(SELECT nok.phone_number FROM next_of_kin nok WHERE nok.client_id = cl.id AND nok.is_deleted = false ORDER BY nok.is_primary DESC, nok.sort_order ASC LIMIT 1) AS nok_contact_number'),
                DB::raw('(SELECT nok.email FROM next_of_kin nok WHERE nok.client_id = cl.id AND nok.is_deleted = false ORDER BY nok.is_primary DESC, nok.sort_order ASC LIMIT 1) AS nok_email'),
            ])
            ->where('cl.is_deleted', false)
            ->orderBy('cl.created_at', 'desc');

        // Drop clients whose only case history is a self-filed intake that was
        // never accepted. Hand-written rather than Client::withoutUnacceptedIntake()
        // because this builds on the query builder, not Eloquent — same rule, so
        // change the two together.
        //
        // The first EXISTS deliberately does NOT filter deleted cases. rejectIntake()
        // soft-deletes the case and leaves the client row behind, so ignoring
        // trashed rows here would read a rejected filer as established and write
        // their PII into a spreadsheet that leaves the system.
        //
        // Grouped: an ungrouped orWhereExists would OR against cl.is_deleted above
        // and let deleted clients back into the export.
        $query->where(function ($outer) {
            $outer->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('cases AS cp')
                    ->whereColumn('cp.client_id', 'cl.id')
                    ->where('cp.source', CaseFile::SOURCE_SELF_FILED)
                    ->where('cp.status', 'DRAFT');
            })->orWhereExists(function ($q) {
                $q->selectRaw('1')
                    ->from('cases AS cr')
                    ->whereColumn('cr.client_id', 'cl.id')
                    ->where('cr.is_deleted', false)
                    // Both columns: SoftDeleteFlag writes them together and the
                    // Eloquent scope filters on deleted_at, so matching it here
                    // stops listing and export drifting apart if one is set alone.
                    ->whereNull('cr.deleted_at')
                    ->where(function ($inner) {
                        $inner->where('cr.source', '!=', CaseFile::SOURCE_SELF_FILED)
                            ->orWhere('cr.status', '!=', 'DRAFT');
                    });
            });
        });

        // ADMIN/CASE_MANAGER: all. AGENCY: clients linked to their referrals.
        if ($user?->role === UserRole::AGENCY->value) {
            if ($user->agcy_id) {
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
                $query->whereRaw('1=0');
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
        if (! empty($filters['vulnerability_indicator'])) {
            $vuln = $filters['vulnerability_indicator'];
            $query->whereIn('cl.id', function ($q) use ($vuln) {
                $q->select('c8.client_id')
                    ->from('cases AS c8')
                    ->where(function ($q2) use ($vuln) {
                        $q2->where('c8.vulnerability_indicator', 'LIKE', "%{$vuln}%")
                            ->orWhere('c8.nok_vulnerability_indicator', 'LIKE', "%{$vuln}%");
                    })
                    ->where('c8.is_deleted', false)
                    ->whereNotNull('c8.client_id');
            });
        }
        if (! empty($filters['case_status'])) {
            $query->whereIn('cl.id', function ($q) use ($filters) {
                $q->select('c9.client_id')
                    ->from('cases AS c9')
                    ->where('c9.status', $filters['case_status'])
                    ->where('c9.is_deleted', false)
                    ->whereNotNull('c9.client_id');
            });
        }
        $categoryIds = CategoryFilter::fromArray($filters)->ids();
        if ($categoryIds) {
            $query->whereIn('cl.id', function ($q) use ($filters) {
                $q->select('c10.client_id')
                    ->from('cases AS c10')
                    ->where('c10.is_deleted', false)
                    ->whereNotNull('c10.client_id');

                $this->applyCategoryFilter($q, 'c10', $filters);
            });
        }
        if (! empty($filters['case_issue_id'])) {
            $query->whereIn('cl.id', function ($q) use ($filters) {
                $q->select('c11.client_id')
                    ->from('cases AS c11')
                    ->where('c11.case_issue_id', $filters['case_issue_id'])
                    ->where('c11.is_deleted', false)
                    ->whereNotNull('c11.client_id');
            });
        }
        if (! empty($filters['agcy_id'])) {
            $query->whereIn('cl.id', function ($q) use ($filters) {
                $q->select('c12.client_id')
                    ->from('cases AS c12')
                    ->whereIn('c12.id', function ($q2) use ($filters) {
                        $q2->select('r3.case_id')
                            ->from('referrals AS r3')
                            ->where('r3.agcy_id', $filters['agcy_id'])
                            ->where('r3.is_deleted', false);
                    })
                    ->where('c12.is_deleted', false)
                    ->whereNotNull('c12.client_id');
            });
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('cl.created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('cl.created_at', '<=', $filters['date_to']);
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

        return $query;
    }

    /**
     * Get enriched clients for business export — no IDs or system fields.
     * Joins case info, addresses, employments, next-of-kin, and referral parties.
     * ADMIN/CASE_MANAGER: all. AGENCY: clients on their referrals.
     *
     * @param  array  $filters  Optional: search, sex, client_type
     */
    public function getClientsExport(?User $user = null, array $filters = []): Collection
    {
        // Safety cap — prevents memory exhaustion on unbounded exports.
        // The DataExportService should eventually support streaming for larger sets.
        return $this->clientsExportQuery($user, $filters)->limit(10000)->get()->map(function ($row) {
            $row = (object) $row;

            // --- Decrypt encrypted PII fields from raw subqueries ---
            $row->date_of_birth = EncryptedString::decrypt($row->date_of_birth ?? null);
            $row->contact_number = EncryptedString::decrypt($row->contact_number ?? null);
            $row->email = EncryptedString::decrypt($row->email ?? null);
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

            // --- Full Name: "Last, First Middle" ---
            $firstName = $row->first_name ?? '';
            $lastName = $row->last_name ?? '';
            $middleInitial = $row->middle_name ?? '';
            $row->full_name = trim($lastName.($firstName ? ', '.$firstName : '').($middleInitial ? ' '.$middleInitial : ''));

            // --- Age ---
            $row->age = '';
            if (! empty($row->date_of_birth)) {
                try {
                    $dob = new \DateTimeImmutable($row->date_of_birth);
                    $row->age = (string) CarbonImmutable::parse($dob)->age;
                } catch (\Exception) {
                    $row->age = '';
                }
            }

            // --- Full Address ---
            $addressResolver = app(PhilippineAddressService::class);
            $row->full_address = $addressResolver->format(
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
                $row->nok_middle_name,
            );

            return $row;
        });
    }

    public function countClientsExport(?User $user = null, array $filters = []): int
    {
        return (int) $this->clientsExportQuery($user, $filters)->count();
    }
}
