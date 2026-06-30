<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Services\AuditLogFormatter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $formatter = app(AuditLogFormatter::class);

        $query = AuditLog::with('user');

        if (! $user->isAdmin()) {
            if ($user->isCaseManager()) {
                $caseIds = CaseFile::where('user_id', $user->id)->pluck('id');
                $referralIds = Referral::whereIn('case_id', $caseIds)->pluck('id');
                $entityIds = $caseIds->concat($referralIds)->unique()->values()->toArray();
                $query->whereIn('entity_id', $entityIds);
            } elseif ($user->agcy_id) {
                $referralIds = Referral::where('agcy_id', $user->agcy_id)->pluck('id');
                $query->whereIn('entity_id', $referralIds->toArray());
            }
        }

        $query->when($request->filled('action'), function ($q) use ($request) {
            $actions = explode(',', $request->input('action'));
            $q->whereIn('action', $actions);
        });

        $query->when($request->filled('module'), function ($q) use ($request) {
            $modules = explode(',', $request->input('module'));
            $expanded = collect($modules)
                ->flatMap(fn ($m) => static::moduleAliases($m))
                ->unique()
                ->values()
                ->toArray();
            $q->whereIn('module', $expanded);
        });

        $query->when($request->filled('user_id'), function ($q) use ($request) {
            $q->where('user_id', $request->input('user_id'));
        });

        $query->when($request->filled('date_from'), function ($q) use ($request) {
            $q->where('timestamp', '>=', $request->input('date_from'));
        });

        $query->when($request->filled('date_to'), function ($q) use ($request) {
            $q->where('timestamp', '<=', $request->input('date_to').' 23:59:59');
        });

        $query->when($request->filled('search'), function ($q) use ($request) {
            $search = $request->input('search');
            $q->where('description', 'ILIKE', "%{$search}%");
        });

        $query->orderBy('timestamp', 'desc');

        $perPage = min((int) $request->input('per_page', 15), 100);
        $logs = $query->paginate($perPage);

        $logs->getCollection()->transform(function ($log) use ($formatter) {
            if ($log->description === null) {
                try {
                    $log->description = $formatter->format($log);
                    $log->save();
                } catch (\Throwable $e) {
                    $log->description = $log->action.' '.$log->module;
                }
            }

            $display = $formatter->formatForDisplay($log);
            $log->message = $display['message'];
            $log->detail = $display['detail'];
            $log->actor = $display['actor'];
            $log->hasChanges = $display['hasChanges'];
            $log->formatted_module = $display['module'];

            if ($log->old_value || $log->new_value) {
                $fields = array_unique(array_merge(
                    array_keys($log->old_value ?? []),
                    array_keys($log->new_value ?? [])
                ));
                $log->formatted_fields = collect($fields)
                    ->mapWithKeys(fn ($f) => [$f => $formatter->formatFieldName($f)])
                    ->toArray();
            } else {
                $log->formatted_fields = [];
            }

            return $log;
        });

        $availableActions = AuditLog::distinct()->pluck('action')->values()->toArray();

        $canonicalMap = [
            'case_files' => 'case', 'case' => 'case',
            'clients' => 'client', 'client' => 'client',
            'client_addresses' => 'client_address', 'client_address' => 'client_address',
            'client_employments' => 'client_employment', 'client_employment' => 'client_employment',
            'referrals' => 'referral', 'referral' => 'referral',
            'milestones' => 'milestone', 'milestone' => 'milestone',
            'referral_attachments' => 'referral_attachment', 'referral_attachment' => 'referral_attachment',
            'agencies' => 'agency', 'agency' => 'agency',
            'users' => 'user', 'user' => 'user',
            'services' => 'service', 'service' => 'service',
            'helpdesk_articles' => 'helpdesk_article', 'helpdesk_article' => 'helpdesk_article',
        ];

        $availableModulesRaw = AuditLog::distinct()->pluck('module')->values()->toArray();
        $availableModules = collect($availableModulesRaw)
            ->map(fn ($m) => $canonicalMap[$m] ?? $m)
            ->unique()
            ->sort()
            ->values()
            ->toArray();
        $availableModulesLabels = collect($availableModules)->mapWithKeys(fn ($m) => [
            $m => $formatter->formatModule($m),
        ])->toArray();

        return Inertia::render('AuditLog/Index', [
            'logs' => $logs,
            'availableActions' => Inertia::lazy(fn () => $availableActions),
            'availableModules' => Inertia::lazy(fn () => $availableModules),
            'availableModulesLabels' => Inertia::lazy(fn () => $availableModulesLabels),
            'filterValues' => $request->only(['action', 'module', 'user_id', 'date_from', 'date_to', 'search', 'per_page']),
        ]);
    }

    public static function moduleAliases(string $module): array
    {
        return match ($module) {
            'case_files', 'case' => ['case_files', 'case'],
            'clients', 'client' => ['clients', 'client'],
            'client_addresses', 'client_address' => ['client_addresses', 'client_address'],
            'client_employments', 'client_employment' => ['client_employments', 'client_employment'],
            'referrals', 'referral' => ['referrals', 'referral'],
            'milestones', 'milestone' => ['milestones', 'milestone'],
            'referral_attachments', 'referral_attachment' => ['referral_attachments', 'referral_attachment'],
            'agencies', 'agency' => ['agencies', 'agency'],
            'users', 'user' => ['users', 'user'],
            'services', 'service' => ['services', 'service'],
            'helpdesk_articles', 'helpdesk_article' => ['helpdesk_articles', 'helpdesk_article'],
            default => [$module],
        };
    }
}
