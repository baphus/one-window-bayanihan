<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\AuditLogFormatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Security events from the audit log, in one place.
 *
 * There is no `security` audit module — there never was a write site for it —
 * so this page cannot filter on a module name. It reads the (module, action)
 * set declared in config/security_events.php instead, which is the one
 * definition of "a security event" in the application.
 *
 * Read-only. Audit rows are hash-chained (prev_hash/chain_seq); nothing here
 * reclassifies or rewrites them, it only presents rows that already exist.
 */
class SecurityLogController extends Controller
{
    use ResolvesPerPage;

    public function index(Request $request, AuditLogFormatter $formatter)
    {
        $filters = $request->validate([
            'module' => ['nullable', 'string', 'max:64'],
            'action' => ['nullable', 'string', 'max:64'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'per_page' => ['nullable', 'integer'],
        ]);

        $query = $this->securityQuery();

        if (! empty($filters['module'])) {
            $query->where('module', $filters['module']);
        }

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('timestamp', '>=', $filters['date_from'].' 00:00:00');
        }

        if (! empty($filters['date_to'])) {
            $query->where('timestamp', '<=', $filters['date_to'].' 23:59:59');
        }

        // Deterministic ordering so offset pages never shift between requests.
        $query->orderByDesc('timestamp')->orderByDesc('id');

        $logs = $query->with('user')
            ->paginate($this->perPage($request, 25))
            ->appends($request->except(['page', 'cursor']));

        $logs->setCollection(
            $logs->getCollection()
                ->map(fn (AuditLog $log) => $formatter->formatForAuditResponse($log, true))
                ->values()
        );

        return Inertia::render('Admin/SecurityLog/Index', [
            'logs' => $logs,
            'moduleLabels' => collect(array_keys((array) config('security_events.events', [])))
                ->mapWithKeys(fn ($module) => [$module => $formatter->formatModule($module)])
                ->toArray(),
            'actions' => $this->availableActions(),
            'filterValues' => (object) $request->only(['module', 'action', 'date_from', 'date_to', 'per_page']),
        ]);
    }

    /**
     * Rows matching the declared security event set.
     *
     * Two ways in, deliberately: an explicit (module, action) pair, or the
     * audit system's own `security` category. The category covers events written
     * under USER (admin password resets, email changes) whose action names do
     * not imply security on their own.
     */
    private function securityQuery(): Builder
    {
        $events = (array) config('security_events.events', []);
        $categories = (array) config('security_events.categories', []);

        return AuditLog::query()->where(function (Builder $outer) use ($events, $categories) {
            foreach ($events as $module => $actions) {
                $outer->orWhere(function (Builder $inner) use ($module, $actions) {
                    $inner->where('module', $module)->whereIn('action', (array) $actions);
                });
            }

            if ($categories !== []) {
                $outer->orWhereIn('category', $categories);
            }
        });
    }

    /**
     * Actions that actually appear for the security modules.
     *
     * Taken from the data rather than the config so a filter can never offer an
     * action nothing has ever been recorded with.
     *
     * @return array<int, string>
     */
    private function availableActions(): array
    {
        $modules = array_keys((array) config('security_events.events', []));

        return AuditLog::query()
            ->whereIn('module', $modules)
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->values()
            ->toArray();
    }
}
