import { Link, usePage } from '@inertiajs/react';
import { Hourglass } from 'lucide-react';
import OfwLayout from '@/Layouts/OfwLayout';
import StatusBadge from '@/Components/ui/StatusBadge';
import OfwNotificationBell from '@/Components/OfwNotificationBell';
import { DashboardTable, EmptyState, PageHeader, QuickActions, SectionCard, StatRow } from '@/Components/Dashboard/primitives';
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
 * Segmented list/grid switch rendered in the "Your cases" section header.
 * Mirrors the toggle used by UnifiedTable so the control reads the same
 * across staff and OFW surfaces.
 */
function ViewModeToggle({ viewMode, onChange }) {
    const options = [
        { key: 'list', icon: 'list', label: 'List view' },
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
        <div className="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
            {cases.map((caseItem) => {
                const isUnderReview = caseItem.status === 'PENDING_REVIEW';
                const href = route('ofw.case.show', caseItem.id);

                return (
                    <Link
                        key={caseItem.id}
                        href={href}
                        className="flex flex-col justify-between gap-4 rounded-xl border border-slate-200 bg-white p-4 transition-colors hover:border-slate-300 hover:bg-slate-50/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                    >
                        <div className="min-w-0">
                            <div className="flex items-start justify-between gap-2">
                                <span className="truncate text-sm font-bold text-primary">
                                    {caseItem.tracker_number ?? caseItem.case_number}
                                </span>
                                <StatusBadge
                                    variant="pill"
                                    status={caseItem.status}
                                    showIcon={isUnderReview}
                                    label={isUnderReview ? 'Under Review' : undefined}
                                    icon={isUnderReview ? Hourglass : undefined}
                                />
                            </div>

                            <p className="mt-1 text-[10px] font-bold uppercase tracking-widest text-slate-400">
                                Case {caseItem.case_number ?? 'Pending'}
                            </p>

                            <p className="mt-2 line-clamp-3 text-sm text-slate-600">
                                {caseItem.summary ?? 'No summary provided'}
                            </p>
                        </div>

                        <div className="flex items-center justify-between border-t border-slate-100 pt-3">
                            <span className="text-[11px] text-slate-400">
                                Submitted {formatDate(caseItem.created_at)}
                            </span>
                            <span className="text-xs font-bold text-primary">View</span>
                        </div>
                    </Link>
                );
            })}
        </div>
    );
}

export default function Dashboard({ cases, caseStats }) {
    const { auth } = usePage().props;
    const caseList = cases?.data ?? [];
    const stats = caseStats ?? {};
    const fileCaseHref = safeRoute('ofw.intake', undefined, '/my-cases/intake');
    // OFWs land on the card grid first — it reads better on phones than the
    // six-column table — but the choice is remembered once they pick one.
    const [viewMode, setViewMode] = usePersistedViewMode('owb-view_ofw-cases', 'grid');

    return (
        <OfwLayout title="My Cases">
            <div className="pt-3">
                <PageHeader
                    eyebrow="Case Dashboard"
                    title={`Welcome back, ${auth?.user?.name}`}
                    subtitle="Here's where your requests stand today."
                >
                    <div className="flex items-center gap-2">
                        <OfwNotificationBell />
                        <QuickActions
                            actions={[
                                { href: route('ofw.profile.edit'), label: 'View Profile', icon: 'person' },
                                ...(caseList.length > 0
                                    ? [{ href: fileCaseHref, label: 'File a New Case', icon: 'add', primary: true }]
                                    : []),
                            ]}
                        />
                    </div>
                </PageHeader>
            </div>

            {/* Overview stat cards */}
            <StatRow
                dataTour="ofw-overview"
                stats={[
                    { title: 'Total Requests', value: stats.total, icon: 'inbox', iconBg: 'bg-primary-fixed', iconColor: 'text-primary' },
                    { title: 'Under Review', value: stats.under_review, icon: 'hourglass_top', iconBg: 'bg-primary-fixed', iconColor: 'text-primary' },
                    { title: 'In Progress', value: stats.in_progress, icon: 'progress_activity', iconBg: 'bg-primary-fixed', iconColor: 'text-primary' },
                    { title: 'Completed', value: stats.completed, icon: 'task_alt', iconBg: 'bg-primary-fixed', iconColor: 'text-primary' },
                ]}
            />

            {/* Case list section */}
            <SectionCard
                title="Your cases"
                dataTour="ofw-cases"
                bodyClassName=""
                action={
                    caseList.length > 0 ? (
                        <ViewModeToggle viewMode={viewMode} onChange={setViewMode} />
                    ) : null
                }
            >
                {caseList.length === 0 ? (
                    <EmptyState
                        message="You haven't filed any cases yet. Start by filing a new case and a Case Manager will review it."
                        href={fileCaseHref}
                        actionLabel="File a New Case"
                    />
                ) : viewMode === 'grid' ? (
                    <CaseCardGrid cases={caseList} />
                ) : (
                    <DashboardTable
                            columns={[
                                { key: 'trackingId', label: 'Tracking ID' },
                                { key: 'caseNumber', label: 'Case number' },
                                { key: 'summary', label: 'Request' },
                                { key: 'status', label: 'Status' },
                                { key: 'submitted', label: 'Submitted' },
                                { key: 'action', label: '', className: 'text-right', cellClassName: 'text-right' },
                            ]}
                            rows={caseList.map((caseItem) => {
                                const isUnderReview = caseItem.status === 'PENDING_REVIEW';
                                const href = route('ofw.case.show', caseItem.id);

                                return {
                                    key: caseItem.id,
                                    trackingId: <Link href={href} className="font-bold text-primary hover:text-primary-container">{caseItem.tracker_number ?? caseItem.case_number}</Link>,
                                    caseNumber: caseItem.case_number ?? 'Pending',
                                    summary: <span className="block max-w-sm truncate">{caseItem.summary ?? 'No summary provided'}</span>,
                                    status: <StatusBadge variant="pill" status={caseItem.status} showIcon={isUnderReview} label={isUnderReview ? 'Under Review' : undefined} icon={isUnderReview ? Hourglass : undefined} />,
                                    submitted: formatDate(caseItem.created_at),
                                    action: <Link href={href} className="font-bold text-primary hover:text-primary-container">View</Link>,
                                };
                            })}
                            empty={<EmptyState message="No cases to show." />}
                    />
                )}
            </SectionCard>

            {/* Pagination links */}
            {cases?.links && cases.links.length > 3 && (
                <nav className="mt-8 flex items-center justify-center gap-1" aria-label="Pagination">
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
        </OfwLayout>
    );
}
