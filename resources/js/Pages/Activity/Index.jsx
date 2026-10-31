import Select from '@/Components/Select';
import { useEffect, useRef, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { formatDisplayDateTime } from '@/lib/utils';
import { formatRelativeTime } from '@/lib/relativeTime';

const ROLE_LABELS = {
    ADMIN: 'Admin',
    CASE_MANAGER: 'Case Manager',
    AGENCY: 'Agency',
    OFW: 'OFW',
};

function roleLabel(role) {
    if (!role) return '';

    return ROLE_LABELS[role] ?? role
        .toLowerCase()
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function cleanParams(filters) {
    return Object.fromEntries(
        Object.entries(filters).filter(([, value]) => value !== '' && value !== null && value !== undefined),
    );
}

// Same compact scale as the audit page so the two surfaces read as siblings.
const CONTROL_BASE = 'h-8 px-2.5 text-xs';
const LABEL_QUIET = 'text-[11px] font-medium uppercase tracking-wide text-slate-400';
const PAGE_BTN = 'h-8 inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-2.5 text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary';

export default function ActivityIndex({ events, filters = {}, availableModules = [], viewTitle = 'Activity' }) {
    const rows = events?.data ?? [];
    const from = events?.from ?? 0;
    const to = events?.to ?? 0;
    const total = events?.total ?? 0;
    const currentPage = events?.current_page ?? 1;
    const lastPage = events?.last_page ?? 1;

    /* Debounced search: type locally, fire after 300ms, reset to page 1. */
    const [localSearch, setLocalSearch] = useState(() => filters.search ?? '');
    const debounceRef = useRef(null);
    const filtersRef = useRef(filters);
    filtersRef.current = filters;

    // Sync local search from external changes (browser back, clear-all).
    useEffect(() => {
        setLocalSearch(filters.search ?? '');
    }, [filters.search]);

    useEffect(() => () => {
        if (debounceRef.current) clearTimeout(debounceRef.current);
    }, []);

    const updateFilters = (changes) => {
        router.get(
            route('activity.index'),
            cleanParams({ ...filtersRef.current, ...changes, page: changes.page ?? undefined }),
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const handleSearchChange = (event) => {
        const value = event.target.value;
        setLocalSearch(value);
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
            // Changing the term resets to page 1; preserve the other filters.
            updateFilters({ search: value, page: undefined });
        }, 300);
    };

    const clearSearch = () => {
        if (debounceRef.current) clearTimeout(debounceRef.current);
        setLocalSearch('');
        updateFilters({ search: '', page: undefined });
    };

    const hasActiveFilters = Object.values({
        search: filters.search,
        date_from: filters.date_from,
        date_to: filters.date_to,
        module: filters.module,
        // per_page is a preference, not a constraint — not counted.
    }).some((value) => value !== '' && value !== null && value !== undefined);

    const clearAll = () => {
        if (debounceRef.current) clearTimeout(debounceRef.current);
        setLocalSearch('');
        // Keep per_page (a preference); drop the actual filters.
        updateFilters({ search: '', date_from: '', date_to: '', module: '', page: undefined });
    };

    const goToPage = (page) => {
        updateFilters({ page });
    };

    return (
        <AppLayout title="Activity">
            <Head title="Activity" />
            <div className="mx-auto max-w-4xl">
                <div className="mb-5">
                    <h1 className="font-headline text-xl font-bold tracking-tight text-slate-900 md:text-2xl">{viewTitle}</h1>
                    <p className="mt-0.5 font-body text-xs text-slate-400">Track activity across cases and referrals.</p>
                </div>

                <div className="mb-4 rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                    <div className="flex flex-wrap items-end gap-x-4 gap-y-2.5">
                        {/* Search — first and widest */}
                        <div className="flex flex-col gap-1 flex-1 min-w-[12rem]">
                            <label htmlFor="activity-search" className={LABEL_QUIET}>Search</label>
                            <div className="relative">
                                <span className="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-[16px]" aria-hidden="true">
                                    search
                                </span>
                                <input
                                    id="activity-search"
                                    type="text"
                                    placeholder="Search description, actor, module..."
                                    value={localSearch}
                                    onChange={handleSearchChange}
                                    className={`${CONTROL_BASE} w-full pl-9 pr-8`}
                                />
                                {localSearch && (
                                    <button
                                        type="button"
                                        onClick={clearSearch}
                                        aria-label="Clear search"
                                        className="absolute right-1.5 top-1/2 -translate-y-1/2 inline-flex items-center justify-center h-6 w-6 rounded text-slate-400 hover:text-slate-600 hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                    >
                                        <span className="material-symbols-outlined text-[15px]">close</span>
                                    </button>
                                )}
                            </div>
                        </div>

                        <div className="flex flex-col gap-1">
                            <label htmlFor="activity-date-from" className={LABEL_QUIET}>From</label>
                            <input
                                id="activity-date-from"
                                type="date"
                                value={filters.date_from ?? ''}
                                onChange={(event) => updateFilters({ date_from: event.target.value, page: undefined })}
                                className={CONTROL_BASE}
                            />
                        </div>
                        <div className="flex flex-col gap-1">
                            <label htmlFor="activity-date-to" className={LABEL_QUIET}>To</label>
                            <input
                                id="activity-date-to"
                                type="date"
                                value={filters.date_to ?? ''}
                                onChange={(event) => updateFilters({ date_to: event.target.value, page: undefined })}
                                className={CONTROL_BASE}
                            />
                        </div>
                        <div className="flex flex-col gap-1">
                            <label htmlFor="activity-module" className={LABEL_QUIET}>Module</label>
                            <Select
                                id="activity-module"
                                value={filters.module ?? ''}
                                onChange={(event) => updateFilters({ module: event.target.value, page: undefined })}
                                className={`${CONTROL_BASE} pr-7`}
                            >
                                <option value="">All modules</option>
                                {availableModules.map((module) => (
                                    <option key={module.value} value={module.value}>{module.label}</option>
                                ))}
                            </Select>
                        </div>
                        <div className="flex flex-col gap-1">
                            <label htmlFor="activity-per-page" className={LABEL_QUIET}>Per page</label>
                            <Select
                                id="activity-per-page"
                                value={filters.per_page ?? events?.per_page ?? 25}
                                onChange={(event) => updateFilters({ per_page: event.target.value, page: undefined })}
                                className={`${CONTROL_BASE} pr-7`}
                            >
                                {[10, 25, 50, 100].map((count) => (
                                    <option key={count} value={count}>{count}</option>
                                ))}
                            </Select>
                        </div>

                        {hasActiveFilters && (
                            <div className="flex flex-col gap-1">
                                <span className={LABEL_QUIET} aria-hidden="true">&nbsp;</span>
                                <button
                                    type="button"
                                    onClick={clearAll}
                                    className="h-8 inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-2.5 text-xs font-medium text-slate-600 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                >
                                    <span className="material-symbols-outlined text-[15px]">close</span>
                                    Clear
                                </button>
                            </div>
                        )}
                    </div>
                </div>

                {rows.length > 0 ? (
                    <ol className="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white shadow-sm" aria-label="Activity feed">
                        {rows.map((event) => {
                            const occurredAt = event.occurredAt;
                            const absoluteTime = occurredAt ? formatDisplayDateTime(occurredAt) : 'Unknown time';
                            const actorRole = roleLabel(event.actorRole);

                            return (
                                <li key={event.id} className="p-3 sm:px-4 sm:py-3">
                                    <div className="flex flex-col gap-1.5 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-xs">
                                                <span className="font-semibold text-slate-800">{event.actor || 'System'}</span>
                                                {actorRole && <span className="text-slate-500">({actorRole})</span>}
                                                {event.moduleLabel && <span className="text-slate-400">· {event.moduleLabel}</span>}
                                            </div>
                                            <p className="mt-0.5 text-xs leading-5 text-slate-700">{event.description}</p>
                                            {event.link ? (
                                                <Link href={event.link} className="mt-1.5 inline-flex text-xs font-semibold text-primary hover:underline">
                                                    View record
                                                </Link>
                                            ) : null}
                                        </div>
                                        <time
                                            dateTime={occurredAt}
                                            title={absoluteTime}
                                            className="shrink-0 text-[11px] text-slate-400 sm:text-right"
                                        >
                                            {occurredAt ? formatRelativeTime(occurredAt) : 'Unknown time'}
                                        </time>
                                    </div>
                                </li>
                            );
                        })}
                    </ol>
                ) : (
                    <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-10 text-center">
                        <span className="material-symbols-outlined mb-3 block text-[40px] text-slate-400">history</span>
                        <h2 className="mb-1 text-base font-medium text-slate-900">No activity found</h2>
                        <p className="text-xs text-slate-500">Try adjusting your filters or check back later.</p>
                    </div>
                )}

                {/* Pagination footer — same language as the audit page */}
                {total > 0 && (
                    <div className="mt-4 flex flex-col gap-3 rounded-md border border-slate-300 bg-slate-50 px-4 py-2.5 sm:flex-row sm:items-center sm:justify-between">
                        <div className="text-xs text-slate-500 text-left">
                            Showing <span className="font-semibold text-slate-700">{from}–{to}</span> of <span className="font-semibold text-slate-700">{total.toLocaleString()}</span> records
                        </div>
                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                disabled={currentPage <= 1}
                                onClick={() => goToPage(currentPage - 1)}
                                className={PAGE_BTN}
                            >
                                <span className="material-symbols-outlined text-[16px]">chevron_left</span>
                                <span className="hidden sm:inline">Previous</span>
                            </button>
                            <span className="text-xs text-slate-500 tabular-nums" aria-label={`Page ${currentPage} of ${lastPage}`}>
                                {currentPage} / {lastPage}
                            </span>
                            <button
                                type="button"
                                disabled={currentPage >= lastPage}
                                onClick={() => goToPage(currentPage + 1)}
                                className={PAGE_BTN}
                            >
                                <span className="hidden sm:inline">Next</span>
                                <span className="material-symbols-outlined text-[16px]">chevron_right</span>
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
