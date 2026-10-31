import { useMemo, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { Hourglass, Info } from 'lucide-react';
import OfwPortalLayout from '@/Layouts/OfwPortalLayout';
import OfwBreadcrumbs from '@/Components/OfwBreadcrumbs';
import StatusBadge from '@/Components/ui/StatusBadge';
import { EmptyState, FilterChip } from '@/Components/Dashboard/primitives';
import { usePersistedViewMode } from '@/Hooks/usePersistedViewState';
import safeRoute from '@/utils/safeRoute';

// Laravel paginator labels are server-escaped strings limited to page
// numbers plus &laquo; / &raquo; / &hellip; entities — decode them as text
// instead of injecting them with dangerouslySetInnerHTML.
function paginationLabel(label) {
    return String(label ?? '')
        .replace(/&laquo;/g, '«')
        .replace(/&raquo;/g, '»')
        .replace(/&hellip;/g, '…')
        .replace(/<[^>]*>/g, '');
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-PH', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

/**
 * Map a case status onto the three filter buckets the citizen portal
 * exposes: All / Open / Closed. OPEN and PENDING_REVIEW count as open;
 * CLOSED and ARCHIVED count as closed. This mirrors the bucketing the
 * backend already does for the overview counts.
 */
function caseBucket(status) {
    const normalized = String(status ?? '').toUpperCase();
    if (normalized === 'CLOSED' || normalized === 'ARCHIVED') return 'closed';
    return 'open';
}

function statusBadge(status) {
    const normalized = String(status ?? '').toUpperCase();
    const isUnderReview = normalized === 'PENDING_REVIEW';

    return (
        <StatusBadge
            variant="pill"
            status={status}
            showIcon={isUnderReview}
            label={isUnderReview ? 'Under Review' : undefined}
            icon={isUnderReview ? Hourglass : undefined}
        />
    );
}

/**
 * Segmented list/grid switch. The reference presents the case list as a
 * table, so this is offered as a secondary control that remembers the
 * citizen's choice the same way the rest of the portal does.
 */
function ViewModeToggle({ viewMode, onChange }) {
    const options = [
        { key: 'list', icon: 'view_list', label: 'List view' },
        { key: 'grid', icon: 'grid_view', label: 'Grid view' },
    ];

    return (
        <div
            role="group"
            aria-label="Case layout"
            className="flex items-center gap-0.5 rounded-full border border-slate-200 bg-slate-100 p-0.5"
        >
            {options.map((option) => {
                const active = viewMode === option.key;

                return (
                    <button
                        key={option.key}
                        type="button"
                        onClick={() => onChange(option.key)}
                        aria-pressed={active}
                        aria-label={option.label}
                        title={option.label}
                        className={`flex h-6 w-7 items-center justify-center rounded-full transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-1 ${
                            active
                                ? 'bg-white text-primary shadow-sm ring-1 ring-slate-200'
                                : 'text-slate-400 hover:text-slate-600'
                        }`}
                    >
                        <span className="material-symbols-outlined text-[16px] leading-none" aria-hidden="true">
                            {option.icon}
                        </span>
                    </button>
                );
            })}
        </div>
    );
}

/**
 * Card grid for the OFW case list. Each card links to the case detail as a
 * whole, so the footer affordance is a styled span rather than a nested
 * <Link> (nested interactive elements are invalid and trap focus).
 */
function CaseCardGrid({ cases }) {
    return (
        <div className="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2">
            {cases.map((caseItem) => {
                const href = route('ofw.case.show', caseItem.id);

                return (
                    <Link
                        key={caseItem.id}
                        href={href}
                        className="flex flex-col justify-between gap-4 rounded-lg border border-slate-200 bg-white p-4 transition-colors hover:border-slate-300 hover:bg-slate-50/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                    >
                        <div className="min-w-0">
                            <div className="flex items-start justify-between gap-2">
                                <span className="truncate font-headline text-[15px] font-extrabold tracking-tight text-primary">
                                    {caseItem.tracker_number ?? caseItem.case_number}
                                </span>
                                {statusBadge(caseItem.status)}
                            </div>

                            <p className="mt-1 text-[10px] font-bold uppercase tracking-widest text-slate-400">
                                Case {caseItem.case_number ?? 'Pending'}
                            </p>

                            <p className="mt-2 line-clamp-3 text-[13px] text-slate-600">
                                {caseItem.summary ?? 'No summary provided'}
                            </p>
                        </div>

                        <div className="flex items-center justify-between border-t border-slate-100 pt-3">
                            <span className="text-[11px] text-slate-400">
                                Submitted {formatDate(caseItem.created_at)}
                            </span>
                            <span className="text-[12px] font-bold text-primary">View</span>
                        </div>
                    </Link>
                );
            })}
        </div>
    );
}

/**
 * The case table: CASE / CATEGORY, CASE STATUS, UPDATED, ACTION.
 */
function CaseTable({ cases }) {
    return (
        <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-slate-200 text-left">
                <thead className="bg-slate-50">
                    <tr>
                        <th
                            scope="col"
                            className="whitespace-nowrap px-5 py-3 text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-500"
                        >
                            Case / Category
                        </th>
                        <th
                            scope="col"
                            className="whitespace-nowrap px-5 py-3 text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-500"
                        >
                            Case status
                        </th>
                        <th
                            scope="col"
                            className="whitespace-nowrap px-5 py-3 text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-500"
                        >
                            Updated
                        </th>
                        <th
                            scope="col"
                            className="whitespace-nowrap px-5 py-3 text-right text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-500"
                        >
                            Action
                        </th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 bg-white">
                    {cases.map((caseItem) => {
                        const href = route('ofw.case.show', caseItem.id);
                        const categories = caseItem.categories ?? [];

                        return (
                            <tr key={caseItem.id} className="transition-colors hover:bg-slate-50">
                                <td className="px-5 py-4 align-top">
                                    <Link
                                        href={href}
                                        className="font-headline text-[15px] font-extrabold tracking-tight text-primary hover:underline"
                                    >
                                        {caseItem.tracker_number ?? caseItem.case_number}
                                    </Link>
                                    <p className="mt-0.5 text-[13px] text-slate-700">Request for Assistance</p>
                                    <p className="mt-0.5 text-[13px] text-slate-500">
                                        {categories.length > 0
                                            ? categories.map((c) => c?.name ?? c).join(', ')
                                            : 'No category recorded'}
                                    </p>
                                    <p className="mt-1 text-[11px] text-slate-400">
                                        Submitted {formatDate(caseItem.created_at)}
                                    </p>
                                </td>
                                <td className="px-5 py-4 align-top">{statusBadge(caseItem.status)}</td>
                                <td className="whitespace-nowrap px-5 py-4 align-top text-[13px] text-slate-600">
                                    {formatDate(caseItem.updated_at ?? caseItem.created_at)}
                                </td>
                                <td className="px-5 py-4 text-right align-top">
                                    <Link
                                        href={href}
                                        className="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 py-2 text-[12px] font-bold text-primary transition-colors hover:border-slate-300 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                                    >
                                        View Case
                                    </Link>
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}

/** "Need help?" aside card. */
function HelpCard() {
    return (
        <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div className="px-5 py-5">
                <span
                    aria-hidden="true"
                    className="material-symbols-outlined text-[20px] leading-none text-slate-400"
                >
                    help
                </span>
                <h2 className="mt-3 font-headline text-[17px] font-extrabold tracking-tight text-slate-900">
                    Need help with your case?
                </h2>
                <p className="mt-2 text-[13px] leading-relaxed text-slate-600">
                    Contact DMW for assistance understanding your case status or responding to a request.
                </p>

                <Link
                    href={safeRoute('home', undefined, '/')}
                    className="mt-4 flex w-full items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-[13px] font-bold text-primary transition-colors hover:border-slate-300 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                >
                    Help Center
                </Link>

                <Link
                    href={safeRoute('contact', undefined, '/contact')}
                    className="mt-3 block text-center text-[13px] font-bold text-primary transition-colors hover:text-primary/80 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                >
                    Contact DMW
                </Link>

                <p className="mt-4 flex items-start gap-2 border-t border-slate-100 pt-4 text-[12px] leading-relaxed text-slate-500">
                    <span
                        aria-hidden="true"
                        className="material-symbols-outlined mt-px shrink-0 text-[16px] leading-none text-slate-400"
                    >
                        shield
                    </span>
                    <span>Keep your tracking number private. Share it only through official DMW channels.</span>
                </p>
            </div>
        </section>
    );
}

export default function Dashboard({ cases, caseStats }) {
    const { auth } = usePage().props;
    const caseList = cases?.data ?? [];
    const stats = caseStats ?? {};
    const fileCaseHref = safeRoute('ofw.intake', undefined, '/my-cases/intake');
    const [viewMode, setViewMode] = usePersistedViewMode('owb-view_ofw-cases', 'list');
    // Client-side search + filter over the current page only. The backend
    // remains the source of truth for the dataset and pagination; these only
    // narrow what is visible on the current page.
    const [query, setQuery] = useState('');
    const [filter, setFilter] = useState('all');

    const visibleCases = useMemo(() => {
        const needle = query.trim().toLowerCase();
        return caseList.filter((caseItem) => {
            if (filter !== 'all' && caseBucket(caseItem.status) !== filter) return false;
            if (!needle) return true;
            const haystack = [
                caseItem.tracker_number,
                caseItem.case_number,
                caseItem.summary,
                ...(caseItem.categories ?? []).map((c) => c?.name ?? c),
            ]
                .filter(Boolean)
                .join(' ')
                .toLowerCase();
            return haystack.includes(needle);
        });
    }, [caseList, query, filter]);

    const hasCases = caseList.length > 0;
    const total = Number(stats.total ?? 0);
    const open = Number(stats.under_review ?? 0) + Number(stats.in_progress ?? 0);
    const closed = Number(stats.completed ?? 0);
    const requestCount = caseList.length === 1 ? '1 request for assistance' : `${caseList.length} requests for assistance`;

    return (
        <OfwPortalLayout title="My Cases">
            <OfwBreadcrumbs items={[
                { label: 'My Cases' },
            ]} />

            <h1 className="mt-2 font-headline text-[28px] font-extrabold tracking-tight text-slate-900 sm:text-[32px]">
                My Cases
            </h1>
            <div className="mt-3">
                <p className="text-[16px] font-bold text-slate-900">Welcome, {auth?.user?.name ?? 'OFW'}</p>
                <p className="mt-1 text-[13px] text-slate-600">View your requests for assistance and follow their latest updates.</p>
            </div>

            {/* Cases + help aside */}
            <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <header className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                        <div>
                            <h2 className="font-headline text-[17px] font-extrabold tracking-tight text-slate-900">
                                Your cases
                            </h2>
                            <p className="mt-0.5 text-[12px] text-slate-500">{requestCount}</p>
                        </div>
                        <div className="flex items-center gap-2">
                            <Link
                                href={fileCaseHref}
                                className="inline-flex min-h-[34px] items-center gap-1.5 rounded-md bg-primary px-3 py-2 text-[12px] font-bold text-on-primary transition-colors hover:bg-primary/90"
                            >
                                <span className="material-symbols-outlined text-[16px]">add</span>
                                New case
                            </Link>
                            {hasCases ? (
                                <ViewModeToggle viewMode={viewMode} onChange={setViewMode} />
                            ) : null}
                        </div>
                    </header>

                    {hasCases ? (
                        <>
                            {/* Search + filters */}
                            <div className="space-y-3 border-b border-slate-100 px-5 py-4">
                                <div className="relative">
                                    <span
                                        aria-hidden="true"
                                        className="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[18px] leading-none text-slate-400"
                                    >
                                        search
                                    </span>
                                    <input
                                        type="search"
                                        value={query}
                                        onChange={(e) => setQuery(e.target.value)}
                                        placeholder="Search by tracking number or category"
                                        aria-label="Search your cases"
                                        className="w-full rounded-lg border border-slate-200 bg-white py-2.5 pl-9 pr-3 text-[13px] text-slate-900 placeholder:text-slate-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                                    />
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <FilterChip
                                        label="All cases"
                                        count={total}
                                        active={filter === 'all'}
                                        onClick={() => setFilter('all')}
                                    />
                                    <FilterChip
                                        label="Open"
                                        count={open}
                                        active={filter === 'open'}
                                        onClick={() => setFilter('open')}
                                    />
                                    <FilterChip
                                        label="Closed"
                                        count={closed}
                                        active={filter === 'closed'}
                                        onClick={() => setFilter('closed')}
                                    />
                                </div>
                            </div>

                            {visibleCases.length === 0 ? (
                                <EmptyState message="No cases on this page match your search or filter." />
                            ) : viewMode === 'grid' ? (
                                <CaseCardGrid cases={visibleCases} />
                            ) : (
                                <CaseTable cases={visibleCases} />
                            )}

                            <p className="border-t border-slate-100 px-5 py-3 text-[12px] text-slate-500">
                                Showing {visibleCases.length} of {caseList.length} case{caseList.length === 1 ? '' : 's'} · Your records only
                            </p>
                        </>
                    ) : (
                        <EmptyState
                            message="You have no cases yet."
                            href={fileCaseHref}
                            actionLabel="File one"
                            art
                        />
                    )}
                </section>

                <aside className="lg:sticky lg:top-24 lg:self-start">
                    <HelpCard />
                </aside>
            </div>

            {/* Protect your case information */}
            <div className="mt-6 flex items-start gap-3 rounded-lg border border-info/25 bg-info-container/40 px-5 py-4">
                <Info className="mt-0.5 h-4 w-4 shrink-0 text-info" aria-hidden="true" />
                <div className="text-[13px]">
                    <p className="font-bold text-slate-900">Protect your case information</p>
                    <p className="mt-0.5 text-slate-600">
                        Keep your tracking number and verification codes private. DMW will not ask you to
                        share a one-time code with another person.
                    </p>
                </div>
            </div>

            {/* Pagination links */}
            {cases?.links && cases.links.length > 3 && (
                <nav className="mt-6 flex items-center justify-center gap-1" aria-label="Pagination">
                    {cases.links.map((link) => {
                        // Paginator labels are unique per control (Previous /
                        // page numbers / Next), so the decoded text is a
                        // stable key — unlike the array index.
                        const label = paginationLabel(link.label);
                        return (
                            <Link
                                key={label}
                                href={link.url ?? '#'}
                                className={`rounded px-3 py-1.5 text-sm ${
                                    link.active
                                        ? 'bg-primary font-semibold text-white'
                                        : link.url
                                          ? 'text-slate-600 hover:bg-slate-100'
                                          : 'cursor-not-allowed text-slate-300'
                                }`}
                                preserveScroll
                            >
                                {label}
                            </Link>
                        );
                    })}
                </nav>
            )}

            <p className="mt-6 flex items-center gap-1.5 text-[11px] text-slate-400">
                <span
                    aria-hidden="true"
                    className="material-symbols-outlined text-[14px] leading-none"
                >
                    info
                </span>
                All case information on this page comes from your own records.
            </p>
        </OfwPortalLayout>
    );
}
