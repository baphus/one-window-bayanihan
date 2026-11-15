import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { AuditTimeline } from '@/Components/AuditTimeline';

/**
 * Staff activity page. Renders the same AuditTimeline the system admin sees on
 * /audit-logs; the backend scopes the rows and the filter options to the
 * viewer's role, so no filter here can reach activity the role cannot see.
 */

const FILTER_KEYS = ['action', 'module', 'date_from', 'date_to', 'search', 'per_page', 'page'];

export default function ActivityIndex({
    events,
    filters = {},
    availableActions = [],
    availableModules = [],
    availableModulesLabels = {},
    viewTitle = 'Activity',
}) {
    const pagination = events
        ? {
            total: events.total,
            currentPage: events.current_page,
            totalPages: events.last_page,
            from: events.from,
            to: events.to,
            perPage: events.per_page,
        }
        : null;

    const handleFilterChange = (next) => {
        const url = new URL(window.location);
        FILTER_KEYS.forEach((key) => url.searchParams.delete(key));
        Object.entries(next).forEach(([key, value]) => {
            if (value !== '' && value !== null && value !== undefined) {
                url.searchParams.set(key, value);
            }
        });

        router.get(url.toString(), {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['events', 'filters'],
        });
    };

    return (
        <AppLayout title="Activity">
            <Head title="Activity" />
            <div className="mx-auto max-w-4xl">
                <div className="mb-5">
                    <h1 className="font-headline text-xl font-bold tracking-tight text-slate-900 md:text-2xl">{viewTitle}</h1>
                    <p className="mt-0.5 font-body text-xs text-slate-400">
                        Activity you can see across your cases and referrals.
                    </p>
                </div>

                <AuditTimeline
                    logs={events?.data ?? []}
                    availableActions={availableActions}
                    availableModules={availableModules}
                    availableModulesLabels={availableModulesLabels}
                    availableCategories={[]}
                    filterValues={filters}
                    onFilterChange={handleFilterChange}
                    pagination={pagination}
                    onPageChange={(page) => handleFilterChange({ ...filters, page })}
                    showActorFilter={false}
                    showRoleFilter={false}
                    showChangesFilter={false}
                />
            </div>
        </AppLayout>
    );
}
