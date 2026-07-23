<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\AuditModule;
use App\Helpers\CacheHelper;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Milestone;
use App\Models\Referral;
use App\Models\ReferralAttachment;
use App\Services\AuditCategory;
use App\Services\AuditLogFormatter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    /** Categories shown when the viewer applies no explicit category filter. */
    public const DEFAULT_CATEGORIES = [AuditCategory::SECURITY, AuditCategory::DATA, AuditCategory::ADMIN];

    public function index(Request $request)
    {
        $user = $request->user();
        $formatter = app(AuditLogFormatter::class);
        $isAdmin = $user->isAdmin();

        $query = $this->buildFilteredQuery($request, $user)->with('user');

        // Make cursor boundaries deterministic when events share a timestamp.
        $query->orderBy('timestamp', 'desc')->orderBy('id', 'desc');

        $perPage = $this->perPage($request, 15);
        // Cursor links must carry the active filters and page size; omit only
        // the current cursor, which the paginator replaces for each link.
        $logs = $query->cursorPaginate($perPage)->appends($request->except('cursor'));

        $logs->getCollection()->transform(function ($log) use ($formatter) {
            $display = $formatter->formatForDisplay($log);
            $log->message = $display['message'];
            $log->detail = $display['detail'];
            $log->actor = $display['actor'];
            $log->hasChanges = $display['hasChanges'];
            $log->formatted_module = $display['module'];
            $log->changes = $display['changes'];

            return $log;
        });

        // Filter facets. Admins draw from the whole table (cached, shared);
        // scoped roles draw only from the rows they can actually see, so the
        // filter chips never offer options that would return nothing.
        if ($isAdmin || $user->isCaseManager()) {
            $availableActions = CacheHelper::safeRemember(
                'audit_log_available_actions',
                now()->addHours(24),
                fn () => AuditLog::distinct()->pluck('action')->values()->toArray()
            );
            $availableModulesRaw = CacheHelper::safeRemember(
                'audit_log_available_modules',
                now()->addHours(24),
                fn () => AuditLog::distinct()->pluck('module')->values()->toArray()
            );
        } else {
            $scopedIds = $this->scopedEntityIds($user);
            $availableActions = AuditLog::whereIn('entity_id', $scopedIds)->distinct()->pluck('action')->values()->toArray();
            $availableModulesRaw = AuditLog::whereIn('entity_id', $scopedIds)->distinct()->pluck('module')->values()->toArray();
        }

        // Collapse every stored spelling to its canonical module (AuditModule),
        // falling back to a lower-cased spelling for anything unrecognised.
        $availableModules = collect($availableModulesRaw)
            ->map(fn ($m) => AuditModule::tryFromLegacy((string) $m)?->value ?? strtolower((string) $m))
            ->unique()
            ->sort()
            ->values()
            ->toArray();
        $availableModulesLabels = collect($availableModules)->mapWithKeys(fn ($m) => [
            $m => $formatter->formatModule($m),
        ])->toArray();

        // Admins see the full audit trail ("Audit Logs"); scoped roles see a
        // filtered "Activity Log" of what they are responsible for.
        $viewTitle = $isAdmin ? 'Audit Logs' : 'Activity Log';
        $viewSubtitle = match (true) {
            $isAdmin => 'Complete, tamper-evident record of all system activity.',
            $user->isCaseManager() => 'Complete, tamper-evident record of all system activity.',
            $user->isAgency() => "Activity on your agency's referrals and the cases they belong to.",
            default => 'Your recorded activity.',
        };

        return Inertia::render('AuditLog/Index', [
            'logs' => $logs,
            'availableActions' => Inertia::lazy(fn () => $availableActions),
            'availableModules' => Inertia::lazy(fn () => $availableModules),
            'availableModulesLabels' => Inertia::lazy(fn () => $availableModulesLabels),
            'availableCategories' => AuditCategory::ALL,
            'activeCategories' => $this->requestedCategories($request),
            'canExport' => $isAdmin,
            'isScoped' => ! $isAdmin,
            'viewTitle' => $viewTitle,
            'viewSubtitle' => $viewSubtitle,
            'exportDefaultDays' => (int) config('audit.export.default_days'),
            'exportMaxDays' => (int) config('audit.retention_days'),
            'filterValues' => (object) $request->only(['action', 'module', 'category', 'user_id', 'date_from', 'date_to', 'search', 'per_page']),
        ]);
    }

    /**
     * Streamed CSV export of the current filter selection. Admin-only; an
     * explicit bounded date range is required and every attempt — success
     * or rejection — is itself audit-logged.
     */
    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin(), 403);

        $maxDays = (int) config('audit.retention_days');
        $maxRows = (int) config('audit.export.max_rows');

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        if (! $dateFrom || ! $dateTo) {
            $this->logExportAttempt($request, 'rejected: missing explicit date range');
            abort(422, 'An explicit date range (date_from and date_to) is required for audit exports.');
        }

        // Strict format: these strings also feed buildFilteredQuery raw,
        // so anything other than Y-m-d must be a 422, not a SQL error.
        foreach (['date_from' => $dateFrom, 'date_to' => $dateTo] as $field => $value) {
            try {
                $parsed = Carbon::createFromFormat('Y-m-d', $value);
            } catch (\Throwable) {
                $parsed = false;
            }
            if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
                $this->logExportAttempt($request, "rejected: malformed {$field}");
                abort(422, "The {$field} value must be a date in Y-m-d format.");
            }
        }

        $from = Carbon::createFromFormat('Y-m-d', $dateFrom)->startOfDay();
        $to = Carbon::createFromFormat('Y-m-d', $dateTo)->endOfDay();

        if ($from->gt($to)) {
            $this->logExportAttempt($request, 'rejected: date_from is after date_to');
            abort(422, 'date_from must not be after date_to.');
        }

        if ($from->diffInDays($to) > $maxDays) {
            $this->logExportAttempt($request, "rejected: range exceeds the {$maxDays}-day retention window");
            abort(422, "The export range may not exceed the {$maxDays}-day retention window.");
        }

        $query = $this->buildFilteredQuery($request, $user);

        $rowCount = (clone $query)->count();
        if ($rowCount > $maxRows) {
            $this->logExportAttempt($request, "rejected: {$rowCount} rows exceeds the {$maxRows}-row limit");
            abort(422, "This export would contain {$rowCount} rows (limit {$maxRows}). Narrow the date range or filters.");
        }

        $this->logExportAttempt($request, "exported {$rowCount} rows");

        $formatter = app(AuditLogFormatter::class);
        $filename = 'audit-logs-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($query, $formatter) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Timestamp (UTC)', 'Actor', 'Actor Email', 'Action', 'Module', 'Description', 'Entity ID', 'Category', 'IP Address', 'Changes']);

            foreach ($query->with('user')->orderBy('timestamp')->orderBy('id')->cursor() as $log) {
                $display = $formatter->formatForDisplay($log);
                fputcsv($out, array_map([$this, 'csvSafe'], [
                    $log->timestamp?->toIso8601String(),
                    $display['actor'],
                    $log->user?->email,
                    $display['action'],
                    $display['module'],
                    $display['message'],
                    $log->entity_id,
                    $log->category,
                    $log->ip_address,
                    $display['changes'] !== [] ? json_encode($display['changes'], JSON_UNESCAPED_SLASHES) : '',
                ]));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Shared filter pipeline for the viewer and the export, including per-role
     * scoping:
     *   ADMIN         — the complete audit trail.
     *   CASE_MANAGER  — activity on the cases they own and those cases' referrals.
     *   AGENCY        — their agency's referrals (+ milestones & attachments)
     *                   and the parent cases those referrals belong to.
     *   anything else — nothing (deny by default).
     */
    private function buildFilteredQuery(Request $request, $user)
    {
        $query = AuditLog::query();

        $entityIds = $this->scopedEntityIds($user);
        if (! empty($entityIds)) {
            $query->whereIn('entity_id', $entityIds);
        }

        $categories = $this->requestedCategories($request);
        if (count($categories) < count(AuditCategory::ALL)) {
            $query->where(function ($q) use ($categories) {
                $q->whereIn('category', $categories);
                // Rows predating the category backfill stay visible whenever
                // the business-data view is active.
                if (in_array(AuditCategory::DATA, $categories, true)) {
                    $q->orWhereNull('category');
                }
            });
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

        // Only apply date bounds when they are well-formed Y-m-d values, so a
        // malformed query param is ignored rather than raising a SQL cast error.
        $query->when($this->isValidYmd($request->input('date_from')), function ($q) use ($request) {
            $q->where('timestamp', '>=', $request->input('date_from'));
        });

        $query->when($this->isValidYmd($request->input('date_to')), function ($q) use ($request) {
            $q->where('timestamp', '<=', $request->input('date_to').' 23:59:59');
        });

        $query->when($request->filled('search'), function ($q) use ($request) {
            $search = $request->input('search');
            $q->where('description', 'ILIKE', "%{$search}%");
        });

        return $query;
    }

    /**
     * The audit-log entity_ids a non-admin user is allowed to see. Entity ids
     * are globally-unique UUIDs, so an id-set filter fully isolates each role.
     * An empty set means "see nothing" (whereIn([]) matches no rows).
     */
    private function scopedEntityIds($user): array
    {
        // ADMIN and CASE_MANAGER: see all audit logs
        if ($user->isAdmin() || $user->isCaseManager()) {
            return []; // Empty array = no filter applied (caller skips filter when empty)
        }

        if ($user->isAgency()) {
            // An agency user with no agency sees nothing. Use a sentinel that
            // never matches a real UUID so the caller's whereIn filter is
            // applied but matches zero rows (returning [] would mean "no
            // filter" — the same signal ADMIN/CASE_MANAGER use to see all).
            if (! $user->agcy_id) {
                return ['00000000-0000-0000-0000-000000000000'];
            }

            $agencyReferrals = Referral::where('agcy_id', $user->agcy_id)->get(['id', 'case_id']);
            $referralIds = $agencyReferrals->pluck('id');
            $caseIds = $agencyReferrals->pluck('case_id')->filter()->unique();

            return $this->caseScopeEntityIds($caseIds, $referralIds);
        }

        return [];
    }

    /**
     * Expand a set of cases + referrals to every audit entity_id under them:
     * the cases and referrals themselves plus those referrals' milestones and
     * attachments (soft-deleted attachments included for a complete trail).
     * Shared by the case-manager and agency scopes so both are equally complete.
     */
    private function caseScopeEntityIds($caseIds, $referralIds): array
    {
        $milestoneIds = Milestone::whereIn('refr_id', $referralIds)->pluck('id');
        $attachmentIds = ReferralAttachment::withTrashed()
            ->whereIn('referral_id', $referralIds)
            ->pluck('id');

        return collect($caseIds)
            ->concat($referralIds)
            ->concat($milestoneIds)
            ->concat($attachmentIds)
            ->unique()
            ->values()
            ->toArray();
    }

    /** True only for a strict, well-formed Y-m-d date string. */
    private function isValidYmd(?string $value): bool
    {
        if (! $value) {
            return false;
        }

        try {
            $parsed = Carbon::createFromFormat('Y-m-d', $value);
        } catch (\Throwable) {
            return false;
        }

        return $parsed !== false && $parsed->format('Y-m-d') === $value;
    }

    private function perPage(Request $request, int $default): int
    {
        $requested = (int) $request->input('per_page', $default);

        return in_array($requested, [15, 25, 50, 100], true) ? $requested : $default;
    }

    /**
     * Requested categories, defaulting to everything except system noise.
     */
    private function requestedCategories(Request $request): array
    {
        if (! $request->filled('category')) {
            return self::DEFAULT_CATEGORIES;
        }

        $requested = array_intersect(
            explode(',', $request->input('category')),
            AuditCategory::ALL
        );

        return $requested === [] ? self::DEFAULT_CATEGORIES : array_values($requested);
    }

    /**
     * Neutralize spreadsheet formula injection: user-influenced values
     * (names, descriptions) must not execute when the CSV opens in Excel.
     */
    private function csvSafe(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
            return "'".$value;
        }

        return $value;
    }

    private function logExportAttempt(Request $request, string $result): void
    {
        AuditLog::create([
            'action' => AuditAction::EXPORT->value,
            'module' => AuditModule::AUDIT->value,
            'entity_id' => $request->user()->id,
            'description' => sprintf('%s requested an audit log export — %s', $request->user()->name, $result),
            'new_value' => [
                'result' => $result,
                'filters' => $request->only(['action', 'module', 'category', 'user_id', 'date_from', 'date_to', 'search']),
            ],
            'user_id' => $request->user()->id,
            'timestamp' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => $request->attributes->get('correlation_id') ?? $request->header('X-Request-ID') ?? (string) Str::uuid(),
        ]);
    }

    /**
     * JSON endpoint: audit logs for a specific case and its referrals.
     */
    public function caseAuditLogs(string $id, Request $request)
    {
        $user = $request->user();
        $case = CaseFile::where('is_deleted', false)->findOrFail($id);

        // Authorization: Admin and Case Manager see all; Agency only if they have a referral for this case
        if (! $user->isAdmin() && ! $user->isCaseManager()) {
            $hasReferral = $case->referrals()
                ->where('agcy_id', $user->agcy_id)
                ->whereNotIn('status', ['COMPLETED', 'REJECTED'])
                ->exists();

            if (! $hasReferral) {
                abort(403, 'You do not have access to this case.');
            }
        }

        $caseModules = ['CASE', 'cases', 'case_files', 'case'];
        $referralModules = ['REFERRAL', 'referrals', 'referral'];

        // Get referral IDs belonging to this case
        $referralIds = Referral::where('case_id', $id)
            ->where('is_deleted', false)
            ->pluck('id')
            ->toArray();

        $query = AuditLog::where(function ($q) use ($id, $caseModules, $referralIds, $referralModules) {
            // Case logs
            $q->where(function ($sub) use ($id, $caseModules) {
                $sub->where('entity_id', $id)->whereIn('module', $caseModules);
            });
            // Related referral logs
            if (! empty($referralIds)) {
                $q->orWhere(function ($sub) use ($referralIds, $referralModules) {
                    $sub->whereIn('entity_id', $referralIds)->whereIn('module', $referralModules);
                });
            }
        });

        $query->with('user')->orderBy('timestamp', 'desc');

        $perPage = min((int) $request->input('per_page', 50), 100);
        $logs = $query->cursorPaginate($perPage);

        $formatter = app(AuditLogFormatter::class);
        $logs->getCollection()->transform(function ($log) use ($formatter) {
            $display = $formatter->formatForDisplay($log);
            $log->message = $display['message'];
            $log->detail = $display['detail'];
            $log->actor = $display['actor'];
            $log->hasChanges = $display['hasChanges'];
            $log->formatted_module = $display['module'];
            $log->changes = $display['changes'];

            return $log;
        });

        return response()->json($logs);
    }

    /**
     * JSON endpoint: audit logs for a specific referral and its milestones.
     */
    public function referralAuditLogs(string $id, Request $request)
    {
        $user = $request->user();
        $referral = Referral::where('is_deleted', false)->findOrFail($id);

        // Authorization: Admin and Case Manager see all; Agency only if agcy_id matches
        if (! $user->isAdmin() && ! $user->isCaseManager()) {
            if ($referral->agcy_id !== $user->agcy_id) {
                abort(403, 'You do not have access to this referral.');
            }
        }

        $referralModules = ['REFERRAL', 'referrals', 'referral'];
        $milestoneModules = ['milestone', 'milestones'];
        $attachmentModules = ['referral_attachment', 'referral_attachments'];
        // Get milestone IDs belonging to this referral
        $milestoneIds = $referral->milestones()->pluck('id')->toArray();

        // Get attachment IDs belonging to this referral (include soft-deleted for audit trail)
        $attachmentIds = ReferralAttachment::withTrashed()
            ->where('referral_id', $id)
            ->pluck('id')
            ->toArray();

        $query = AuditLog::where(function ($q) use ($id, $referralModules, $milestoneIds, $milestoneModules, $attachmentIds, $attachmentModules) {
            // Referral logs
            $q->where(function ($sub) use ($id, $referralModules) {
                $sub->where('entity_id', $id)->whereIn('module', $referralModules);
            });
            // Related milestone logs
            if (! empty($milestoneIds)) {
                $q->orWhere(function ($sub) use ($milestoneIds, $milestoneModules) {
                    $sub->whereIn('entity_id', $milestoneIds)->whereIn('module', $milestoneModules);
                });
            }
            // Related attachment logs
            if (! empty($attachmentIds)) {
                $q->orWhere(function ($sub) use ($attachmentIds, $attachmentModules) {
                    $sub->whereIn('entity_id', $attachmentIds)->whereIn('module', $attachmentModules);
                });
            }
        });

        $query->with('user')->orderBy('timestamp', 'desc');

        $perPage = min((int) $request->input('per_page', 50), 100);
        $logs = $query->cursorPaginate($perPage);

        $formatter = app(AuditLogFormatter::class);
        $logs->getCollection()->transform(function ($log) use ($formatter) {
            $display = $formatter->formatForDisplay($log);
            $log->message = $display['message'];
            $log->detail = $display['detail'];
            $log->actor = $display['actor'];
            $log->hasChanges = $display['hasChanges'];
            $log->formatted_module = $display['module'];
            $log->changes = $display['changes'];

            return $log;
        });

        return response()->json($logs);
    }

    /**
     * Every stored spelling (canonical + legacy aliases) a module filter should
     * match. Delegates to AuditModule; unrecognised modules match themselves.
     */
    public static function moduleAliases(string $module): array
    {
        return AuditModule::tryFromLegacy($module)?->aliases() ?? [$module];
    }
}
