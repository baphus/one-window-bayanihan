<?php

namespace App\Services\Reports;

use App\Models\Agency;
use App\Models\CaseEvent;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Services\Export\DataExportService;
use App\Services\PhilippineAddressService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdministrativeExportService
{
    public function __construct(private readonly DataExportService $writer) {}

    public function generate(array $scope): array
    {
        $timezone = config('app.timezone');
        $from = CarbonImmutable::createFromFormat('!Y-m-d', $scope['from'], $timezone);
        $until = CarbonImmutable::createFromFormat('!Y-m-d', $scope['to'], $timezone)->addDay();
        $agencyId = $scope['agency_id'] ?? null;
        $snapshotAt = now($timezone)->toIso8601String();
        if (DB::connection()->getDriverName() !== 'pgsql' || DB::transactionLevel() > 0) {
            throw new \RuntimeException('Administrative export requires PostgreSQL snapshot isolation.');
        }

        DB::beginTransaction();
        try {
            DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
            $caseQuery = CaseFile::query()->where('cases.is_deleted', false)
                ->where('cases.created_at', '>=', $from->utc())
                ->where('cases.created_at', '<', $until->utc());
            if ($agencyId) {
                $caseQuery->whereHas('referrals', fn ($q) => $q->where('is_deleted', false)->where('agcy_id', $agencyId));
            }
            $caseCount = (clone $caseQuery)->count();
            $referralQuery = Referral::query()->where('is_deleted', false)->whereIn('case_id', (clone $caseQuery)->select('cases.id'));
            if ($agencyId) {
                $referralQuery->where('agcy_id', $agencyId);
            }
            $referralCount = (clone $referralQuery)->count();
            $counts = ['cases' => $caseCount, 'referrals' => $referralCount];
            if ($caseCount === 0) {
                DB::commit();

                return ['path' => null, 'filename' => null, 'counts' => $counts, 'reason' => 'empty', 'message' => 'No cases match selected scope.', 'status' => 404];
            }
            $cap = max(0, (int) config('reports.export_row_cap', 6000));
            if ($caseCount > $cap || $referralCount > $cap) {
                DB::commit();

                return ['path' => null, 'filename' => null, 'counts' => $counts, 'reason' => 'over_cap', 'message' => 'Export exceeds row limit. Narrow dates or agency.', 'status' => 422];
            }
            $cases = $caseQuery->select(['id', 'case_number', 'created_at', 'status', 'ofw_user_id', 'sex', 'date_of_birth', 'intake_date', 'address_province'])
                ->with('categories:id,name')->orderBy('created_at')->orderBy('id')->get();
            $referrals = $referralQuery->select(['id', 'case_id', 'agcy_id', 'created_at', 'status'])
                ->with('agency:id,name')->orderBy('created_at')->orderBy('id')->get();
            $caseReferences = $cases->pluck('case_number', 'id');
            if ($cases->count() !== $caseCount || $referrals->count() !== $referralCount) {
                throw new \RuntimeException('Snapshot population changed during materialization.');
            }
            $referralIds = $referrals->modelKeys();
            $sentDates = CaseEvent::query()->whereIn('referral_id', $referralIds)->where('type', CaseEvent::TYPE_REFERRAL_SENT)->get(['referral_id', 'occurred_at'])->keyBy('referral_id');
            $agencyName = $agencyId ? Agency::query()->find($agencyId)?->name : 'All agencies';
            $categories = [];
            $caseCategoryCounts = [];
            foreach ($cases as $case) {
                foreach ($case->categories as $category) {
                    $categories[$case->id][] = $category->name;
                    $caseCategoryCounts[$category->name] = ($caseCategoryCounts[$category->name] ?? 0) + 1;
                }
            }
            $exportedReferralCounts = $referrals->countBy('case_id');
            $caseRows = $cases->map(fn ($case) => [
                'reference' => $case->case_number,
                'created' => $case->created_at?->timezone($timezone)->format('Y-m-d'),
                'status' => $case->status,
                'categories' => implode(', ', $categories[$case->id] ?? []),
                'referral_count' => (int) ($exportedReferralCounts[$case->id] ?? 0),
            ]);
            $referralRows = $referrals->map(fn ($referral) => [
                'reference' => 'Restricted reference '.$referral->id,
                'case_reference' => $caseReferences->get($referral->case_id),
                'agency' => $referral->agency?->name,
                'sent_date' => $sentDates->get($referral->id)?->occurred_at?->timezone($timezone)->format('Y-m-d'),
                'status' => $referral->status,
                'completion_date' => null,
            ]);
            $summary = collect();
            foreach ($cases->groupBy('status') as $status => $group) {
                $summary->push(['metric' => 'Case status', 'label' => $status, 'count' => $group->count()]);
            }
            foreach ($caseCategoryCounts as $name => $count) {
                $summary->push(['metric' => 'Case category (non-additive)', 'label' => $name, 'count' => $count]);
            }
            foreach ($referrals->groupBy(fn ($r) => $r->agency?->name ?? 'Unknown') as $agency => $group) {
                $summary->push(['metric' => 'Referral agency', 'label' => $agency, 'count' => $group->count()]);
            }
            foreach ($referrals->groupBy('status') as $status => $group) {
                $summary->push(['metric' => 'Referral status', 'label' => $status, 'count' => $group->count()]);
            }
            $clientCount = $cases->whereNotNull('ofw_user_id')->pluck('ofw_user_id')->unique()->count();
            $stats = $this->clientStatistics($cases);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $scopeRows = collect([
            ['item' => 'Unique linked clients', 'value' => $clientCount],
            ['item' => 'From', 'value' => $scope['from']], ['item' => 'To', 'value' => $scope['to']],
            ['item' => 'Agency', 'value' => $agencyName], ['item' => 'Timezone', 'value' => $timezone],
            ['item' => 'Snapshot generated', 'value' => $snapshotAt], ['item' => 'Cases', 'value' => $caseCount],
            ['item' => 'Referrals', 'value' => $referralCount], ['item' => 'Population', 'value' => 'Current state at generation; cases selected by inclusive case-created dates. Linked referrals are not date-filtered.'],
            ['item' => 'Confidentiality', 'value' => 'Restricted linkable data; not anonymous. No identities, contact details, narratives, or exact addresses.'],
            ['item' => 'Client linkage', 'value' => 'Distinct linked OFW accounts; one latest selected case snapshot per account. Unlinked cases excluded.'],
            ['item' => 'Suppression', 'value' => 'Positive cells below threshold withheld; complementary values withheld when needed; zeros remain zero.'],
            ['item' => 'Sent date', 'value' => 'Recorded referral_sent case event time; blank when event is unavailable.'],
            ['item' => 'Detail populations', 'value' => 'Cases: '.$caseCount.'; referrals: '.$referralCount.'. Counts reconcile to complete detail sheets.'],
            ['item' => 'Suppression legend', 'value' => 'Suppressed denotes withheld aggregate; zero remains an explicit zero.'],
            ['item' => 'Agency scope', 'value' => $agencyId ? 'Cases require referral to selected agency; Referrals include selected agency only.' : 'All eligible cases and referrals; cases without referrals included.'],
            ['item' => 'Completion date', 'value' => 'Blank: no reliable recorded completion timestamp exists.'],
            ['item' => 'Categories', 'value' => 'Category counts are non-additive; one case can have multiple categories.'],
        ]);
        $filename = 'administrative-export-'.$scope['from'].'-'.$scope['to'].'.xlsx';
        $path = $this->writer->generateMultiSheetFile([
            ['title' => 'Scope & Definitions', 'columnMap' => [['label' => 'Item', 'key' => 'item'], ['label' => 'Value', 'key' => 'value']], 'rows' => $scopeRows],
            ['title' => 'Management Summary', 'columnMap' => [['label' => 'Metric', 'key' => 'metric'], ['label' => 'Label', 'key' => 'label'], ['label' => 'Count', 'key' => 'count', 'type' => 'int']], 'rows' => $summary],
            ['title' => 'Cases', 'columnMap' => [['label' => 'Case reference (restricted)', 'key' => 'reference'], ['label' => 'Created date', 'key' => 'created'], ['label' => 'Status', 'key' => 'status', 'type' => 'status'], ['label' => 'Categories', 'key' => 'categories'], ['label' => 'Exported referral count', 'key' => 'referral_count', 'type' => 'int']], 'rows' => $caseRows],
            ['title' => 'Referrals', 'columnMap' => [['label' => 'Referral reference (restricted)', 'key' => 'reference'], ['label' => 'Case reference (restricted)', 'key' => 'case_reference'], ['label' => 'Agency', 'key' => 'agency'], ['label' => 'Sent date', 'key' => 'sent_date'], ['label' => 'Status', 'key' => 'status', 'type' => 'status'], ['label' => 'Completion date', 'key' => 'completion_date']], 'rows' => $referralRows],
            ['title' => 'Client Statistics', 'columnMap' => [['label' => 'Distribution', 'key' => 'distribution'], ['label' => 'Value', 'key' => 'value'], ['label' => 'Count', 'key' => 'count']], 'rows' => $stats],
        ], $filename);

        return ['path' => $path, 'filename' => $filename, 'counts' => $counts];
    }

    public function clientStatistics($cases): Collection
    {
        $clients = $cases->filter(fn ($case) => $case->ofw_user_id !== null)
            ->groupBy('ofw_user_id')->map(fn ($group) => $group->sortBy(fn ($case) => [$case->created_at?->getTimestamp() ?? 0, $case->id])->last());
        $buckets = ['Sex' => [], 'Age at intake' => [], 'Province' => []];
        $address = app(PhilippineAddressService::class);
        foreach ($clients as $case) {
            $sex = $case->sex ?: 'Unknown';
            $buckets['Sex'][$sex] = ($buckets['Sex'][$sex] ?? 0) + 1;
            $age = $case->age_at_intake;
            $band = $age === null ? 'Unknown' : match (true) {
                $age < 18 => '0-17', $age <= 25 => '18-25', $age <= 40 => '26-40', $age <= 60 => '41-60', default => '61+'
            };
            $buckets['Age at intake'][$band] = ($buckets['Age at intake'][$band] ?? 0) + 1;
            $province = $case->address_province ? $address->resolve($case->address_province) : 'Unknown';
            $buckets['Province'][$province] = ($buckets['Province'][$province] ?? 0) + 1;
        }
        foreach (['MALE', 'FEMALE', 'Unknown'] as $value) {
            $buckets['Sex'][$value] ??= 0;
        }
        foreach (['0-17', '18-25', '26-40', '41-60', '61+', 'Unknown'] as $value) {
            $buckets['Age at intake'][$value] ??= 0;
        }
        $buckets['Province']['Unknown'] ??= 0;
        $clientCount = $clients->count();
        $threshold = max(0, (int) config('reports.suppression_threshold', 5));
        $rows = collect();
        foreach ($buckets as $distribution => $counts) {
            $hiddenValues = [];
            foreach ($counts as $value => $count) {
                if ($count > 0 && $count < $threshold) {
                    $hiddenValues[] = $value;
                }
            }
            // Withhold every positive bucket when any is small; totals cannot expose a lone hidden count.
            if ($hiddenValues !== []) {
                $hiddenValues = array_keys(array_filter($counts, fn ($count) => $count > 0));
            }
            foreach ($counts as $value => $count) {
                $withheld = $count > 0 && in_array($value, $hiddenValues, true);
                $rows->push(['distribution' => $distribution, 'value' => $withheld ? 'Suppressed' : $value, 'count' => $withheld ? 'Suppressed' : $count]);
            }
        }

        return $rows->prepend(['distribution' => 'Linked clients', 'value' => 'Distinct linked accounts', 'count' => $clientCount]);
    }
}
