<?php

namespace App\Services;

use App\Models\CaseFile;
use App\Models\User;
use App\Rules\VulnerabilityRule;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ClientDirectoryService
{
    private const PROFILE_FIELDS = [
        'first_name', 'middle_name', 'last_name', 'suffix', 'date_of_birth', 'sex',
        'civil_status', 'civil_status_other', 'deployment_mode', 'employment_status',
        'email', 'contact_number', 'address_region', 'address_province',
        'address_city_municipality', 'address_barangay', 'address_street',
        'employment_employer_name', 'employment_employer_address', 'employment_position',
        'employment_country', 'employment_last_position', 'employment_date_of_arrival',
    ];

    private function eligible(): Builder
    {
        return DB::table('cases')->where('cases.is_deleted', false)->whereNull('cases.deleted_at')
            ->whereIn('cases.status', ['OPEN', 'CLOSED']);
    }

    private function groups(): Builder
    {
        $vulnerable = collect(VulnerabilityRule::VALUES)
            ->map(fn ($value) => preg_quote($value, '/'))
            ->implode('|');
        $vulnerable = '(^|, )(?:'.$vulnerable.')(,|$)';

        $groups = $this->eligible()->selectRaw("CASE WHEN ofw_user_id IS NULL THEN 'case' ELSE 'account' END AS kind")
            ->selectRaw('COALESCE(ofw_user_id, id) AS id')
            ->selectRaw('COUNT(*) AS case_count')
            ->selectRaw("SUM(CASE WHEN status = 'OPEN' THEN 1 ELSE 0 END) AS open_case_count")
            ->selectRaw("SUM(CASE WHEN status = 'CLOSED' THEN 1 ELSE 0 END) AS closed_case_count")
            ->selectRaw("MAX(CASE WHEN status = 'OPEN' THEN 1 ELSE 0 END) AS has_open")
            ->selectRaw("MAX(CASE WHEN status = 'CLOSED' THEN 1 ELSE 0 END) AS has_closed")
            ->selectRaw("MAX(CASE WHEN client_type = 'OFW' THEN 1 ELSE 0 END) AS has_ofw")
            ->selectRaw("MAX(CASE WHEN client_type = 'NEXT_OF_KIN' THEN 1 ELSE 0 END) AS has_next_of_kin")
            ->selectRaw('MAX(CASE WHEN vulnerability_indicator ~ ? THEN 1 ELSE 0 END) AS vulnerable', [$vulnerable])
            ->selectRaw('MAX(created_at) AS latest_case_at');

        foreach (VulnerabilityRule::VALUES as $index => $marker) {
            $groups->selectRaw('MAX(CASE WHEN vulnerability_indicator ~ ? THEN 1 ELSE 0 END) AS marker_'.$index, ['(^|, )'.preg_quote($marker, '/').'(,|$)']);
        }

        return $groups->groupByRaw('COALESCE(ofw_user_id, id), CASE WHEN ofw_user_id IS NULL THEN \'case\' ELSE \'account\' END');
    }

    private function currentAge(CaseFile $case): ?int
    {
        $birthDate = $case->date_of_birth;
        $today = Carbon::today(config('app.timezone'));

        return $birthDate === null || $birthDate->toDateString() > $today->toDateString()
            ? null
            : (int) Carbon::parse($birthDate->toDateString(), config('app.timezone'))->diffInYears($today);
    }

    public function index(array $filters): array
    {
        $groups = $this->groups();
        $kpis = DB::query()->fromSub(clone $groups, 'g')
            ->selectRaw('COALESCE(SUM(has_ofw), 0) AS ofw_clients, COALESCE(SUM(has_next_of_kin), 0) AS next_of_kin_clients, COALESCE(SUM(vulnerable), 0) AS vulnerable_clients')
            ->selectRaw("COALESCE(SUM(CASE WHEN kind = 'account' AND case_count >= 2 THEN 1 ELSE 0 END), 0) AS returning_clients")
            ->first();

        $query = DB::query()->fromSub($groups, 'g')
            ->leftJoin('users as u', function ($join) {
                $join->on('u.id', '=', 'g.id')->where('g.kind', '=', 'account');
            });

        if ($filters['status'] !== 'Any') {
            if (in_array($filters['status'], ['Open', 'Both'])) {
                $query->where('g.has_open', 1);
            }
            if (in_array($filters['status'], ['Closed', 'Both'])) {
                $query->where('g.has_closed', 1);
            }
        }

        if ($filters['search'] !== '') {
            $search = '%'.str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $filters['search']).'%';
            $query->where(function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('u.is_deleted', false)->whereNull('u.deleted_at')->where('u.email', 'ilike', $search);
                })->orWhereExists(function ($query) use ($search) {
                    $query->selectRaw('1')->from('cases as matched')
                        ->where('matched.is_deleted', false)->whereNull('matched.deleted_at')
                        ->whereIn('matched.status', ['OPEN', 'CLOSED'])
                        ->whereRaw('COALESCE(matched.ofw_user_id, matched.id) = g.id')
                        ->where(function ($query) use ($search) {
                            foreach (['first_name', 'middle_name', 'last_name', 'case_number'] as $column) {
                                $query->orWhere('matched.'.$column, 'ilike', $search);
                            }
                            $query->orWhereRaw("concat_ws(' ', matched.first_name, matched.middle_name, matched.last_name) ilike ?", [$search]);
                        });
                });
            });
        }

        $clients = $query->select('g.*')->selectRaw("CASE WHEN g.kind = 'case' THEN 'No account linked' WHEN u.id IS NULL OR u.is_deleted = true OR u.deleted_at IS NOT NULL THEN 'Unavailable' WHEN u.is_active = false THEN 'Inactive' ELSE 'Active' END AS account_state")
            ->orderByDesc('g.latest_case_at')->orderBy('g.kind')->orderBy('g.id')->paginate(15)->withQueryString();

        $clients->getCollection()->transform(function ($row) {
            $case = $this->historyQuery($row->kind, $row->id)->orderByDesc('created_at')->orderByDesc('id')->first(['first_name', 'middle_name', 'last_name', 'suffix', 'date_of_birth', 'email', 'contact_number', 'address_city_municipality', 'address_province', 'employment_country', 'employment_status']);
            $row->current_age = $this->currentAge($case);
            $row->name = User::composeName($case->first_name, $case->middle_name, $case->last_name, $case->suffix);
            foreach (['email', 'contact_number', 'address_city_municipality', 'address_province', 'employment_country', 'employment_status'] as $field) {
                $row->{$field} = $case->{$field};
            }
            $row->show_url = route('clients.show', [$row->kind, $row->id]);
            $row->case_count = (int) $row->case_count;
            $row->open_case_count = (int) $row->open_case_count;
            $row->closed_case_count = (int) $row->closed_case_count;
            $row->has_open = (bool) $row->has_open;
            $row->has_closed = (bool) $row->has_closed;
            $row->vulnerability_indicators = [];
            foreach (VulnerabilityRule::VALUES as $index => $marker) {
                if ($row->{'marker_'.$index}) {
                    $row->vulnerability_indicators[] = $marker;
                }
                unset($row->{'marker_'.$index});
            }
            unset($row->vulnerable, $row->has_ofw, $row->has_next_of_kin);

            return $row;
        });

        return ['clients' => $clients, 'kpis' => (array) $kpis, 'filters' => $filters];
    }

    private function historyQuery(string $kind, string $id)
    {
        abort_unless(in_array($kind, ['account', 'case'], true), 404);

        $query = CaseFile::query()->where('is_deleted', false)->whereIn('status', ['OPEN', 'CLOSED']);

        return $kind === 'account' ? $query->where('ofw_user_id', $id) : $query->whereNull('ofw_user_id')->whereKey($id);
    }

    public function show(string $kind, string $id): array
    {
        $history = $this->historyQuery($kind, $id);
        $source = (clone $history)->orderByDesc('created_at')->orderByDesc('id')->firstOrFail();
        $account = $kind === 'account' ? User::withTrashed()->find($id) : null;
        $state = $kind === 'case' ? 'No account linked' : ($account === null || $account->is_deleted || $account->trashed() ? 'Unavailable' : ($account->is_active ? 'Active' : 'Inactive'));
        $cases = $history->with('reviewer:id,name')->orderByDesc('created_at')->orderByDesc('id')->paginate(15)
            ->through(fn (CaseFile $case) => [
                'id' => $case->id, 'case_number' => $case->case_number,
                'intake_date' => $case->intake_date?->toDateString(), 'created_at' => $case->created_at,
                'status' => $case->status, 'closed_at' => $case->closed_at,
                'client_type' => $case->client_type, 'approver' => $case->reviewer?->name,
                'show_url' => route('cases.show', $case->id),
            ]);

        return ['client' => [
            'kind' => $kind, 'id' => $id,
            'profile' => [...collect(self::PROFILE_FIELDS)->mapWithKeys(fn ($field) => [$field => $source->{$field} instanceof \DateTimeInterface ? $source->{$field}->format('Y-m-d') : $source->{$field}])->all(), 'current_age' => $this->currentAge($source)],
            'source_case' => ['id' => $source->id, 'case_number' => $source->case_number, 'intake_date' => $source->intake_date?->toDateString(), 'created_at' => $source->created_at],
            'account' => [
                'state' => $state,
                'email' => in_array($state, ['Active', 'Inactive'], true) ? $account->email : null,
                'avatar_url' => in_array($state, ['Active', 'Inactive'], true) ? $account->avatar_url : null,
            ],
        ], 'cases' => $cases];
    }
}
