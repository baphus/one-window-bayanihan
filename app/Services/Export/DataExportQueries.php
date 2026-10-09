<?php

namespace App\Services\Export;

use App\Models\User;
use App\Services\Export\Queries\CaseProgressQueries;
use App\Services\Export\Queries\CaseQueries;
use App\Services\Export\Queries\ClientDetailQueries;
use App\Services\Export\Queries\ClientQueries;
use App\Services\Export\Queries\ReferenceDataQueries;
use App\Services\Export\Queries\ReferralQueries;
use Illuminate\Support\Collection;

/**
 * Orchestrator over the per-sheet-group query builders in
 * App\Services\Export\Queries. Every method here is a pass-through; the SQL
 * lives in the query class named by the method.
 */
class DataExportQueries
{
    public function __construct(
        private readonly CaseQueries $cases = new CaseQueries,
        private readonly ClientQueries $clients = new ClientQueries,
        private readonly ClientDetailQueries $clientDetails = new ClientDetailQueries,
        private readonly ReferralQueries $referrals = new ReferralQueries,
        private readonly CaseProgressQueries $caseProgress = new CaseProgressQueries,
        private readonly ReferenceDataQueries $referenceData = new ReferenceDataQueries,
    ) {}

    /**
     * Build the full 13-table workbook sheets shared by the admin download
     * and the queued GenerateSystemReport job. Single source of truth for
     * the table-to-query map — add new tables here, not at call sites.
     *
     * @return list<array{title: string, columnMap: array, rows: Collection}>
     */
    public function fullExportSheets(?User $user = null): array
    {
        $tableQueryMap = [
            'cases' => fn () => $this->getCases($user),
            'clients' => fn () => $this->getClients($user),
            'referrals' => fn () => $this->getReferrals($user),
            'users' => fn () => $this->getUsers($user),
            'agencies' => fn () => $this->getAgencies(),
            'services' => fn () => $this->getServices(),
            'milestones' => fn () => $this->getMilestones($user),
            'next_of_kin' => fn () => $this->getNextOfKins($user),
            'case_documents' => fn () => $this->getCaseDocuments($user),
            'client_addresses' => fn () => $this->getClientAddresses($user),
            'client_employments' => fn () => $this->getClientEmployments($user),
            'case_categories' => fn () => $this->getCaseCategories(),
            'case_statuses' => fn () => $this->getCaseStatuses(),
        ];

        $sheets = [];
        foreach (ColumnMaps::getAllTables() as $table) {
            $data = isset($tableQueryMap[$table]) ? $tableQueryMap[$table]() : collect();
            $sheets[] = [
                'title' => ucfirst($table),
                'columnMap' => ColumnMaps::getMap($table),
                'rows' => $data,
            ];
        }

        return $sheets;
    }

    public function getCases(?User $user = null): Collection
    {
        return $this->cases->getCases($user);
    }

    public function getCasesExport(?User $user = null, array $filters = []): Collection
    {
        return $this->cases->getCasesExport($user, $filters);
    }

    public function countCasesExport(?User $user = null, array $filters = []): int
    {
        return $this->cases->countCasesExport($user, $filters);
    }

    public function getClients(?User $user = null): Collection
    {
        return $this->clients->getClients($user);
    }

    public function getClientsExport(?User $user = null, array $filters = []): Collection
    {
        return $this->clients->getClientsExport($user, $filters);
    }

    public function countClientsExport(?User $user = null, array $filters = []): int
    {
        return $this->clients->countClientsExport($user, $filters);
    }

    public function getNextOfKins(?User $user = null): Collection
    {
        return $this->clientDetails->getNextOfKins($user);
    }

    public function getClientAddresses(?User $user = null): Collection
    {
        return $this->clientDetails->getClientAddresses($user);
    }

    public function getClientEmployments(?User $user = null): Collection
    {
        return $this->clientDetails->getClientEmployments($user);
    }

    public function getReferrals(?User $user = null): Collection
    {
        return $this->referrals->getReferrals($user);
    }

    public function getReferralsExport(?User $user = null, array $filters = []): Collection
    {
        return $this->referrals->getReferralsExport($user, $filters);
    }

    public function countReferralsExport(?User $user = null, array $filters = []): int
    {
        return $this->referrals->countReferralsExport($user, $filters);
    }

    public function getMilestones(?User $user = null): Collection
    {
        return $this->caseProgress->getMilestones($user);
    }

    public function getCaseDocuments(?User $user = null): Collection
    {
        return $this->caseProgress->getCaseDocuments($user);
    }

    public function getUsers(?User $user = null): Collection
    {
        return $this->referenceData->getUsers($user);
    }

    public function getAgencies(): Collection
    {
        return $this->referenceData->getAgencies();
    }

    public function getServices(): Collection
    {
        return $this->referenceData->getServices();
    }

    public function getCaseCategories(): Collection
    {
        return $this->referenceData->getCaseCategories();
    }

    public function getCaseStatuses(): Collection
    {
        return $this->referenceData->getCaseStatuses();
    }
}
