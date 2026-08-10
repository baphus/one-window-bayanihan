import { Link, usePage } from '@inertiajs/react';
import { Hourglass } from 'lucide-react';
import OfwLayout from '@/Layouts/OfwLayout';
import StatusBadge from '@/Components/ui/StatusBadge';

function formatDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-PH', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function CaseCard({ caseItem }) {
    const isUnderReview = caseItem.status === 'DRAFT' && caseItem.source === 'self_filed';
    const agency = caseItem.referrals?.[0]?.agency;

    const inProgress = ['OPEN', 'PENDING', 'PROCESSING', 'FOR_COMPLIANCE', 'IN_PROGRESS', 'BEING_PREPARED'].includes(
        caseItem.status,
    );
    const completed = ['CLOSED', 'COMPLETED', 'RESOLVED'].includes(caseItem.status);

    const tile = isUnderReview
        ? { icon: 'hourglass_top', className: 'bg-amber-100 text-amber-700' }
        : completed
          ? { icon: 'task_alt', className: 'bg-emerald-100 text-emerald-700' }
          : inProgress
            ? { icon: 'progress_activity', className: 'bg-blue-100 text-blue-600' }
            : { icon: 'description', className: 'bg-primary-fixed text-primary' };

    return (
        <Link
            href={route('ofw.case.show', caseItem.id)}
            className="group block rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md"
        >
            <div className="flex items-start gap-4">
                {/* Status icon tile */}
                <span className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-circle ${tile.className}`}>
                    <span className="material-symbols-outlined text-[22px]" aria-hidden="true">{tile.icon}</span>
                </span>

                <div className="min-w-0 flex-1">
                    <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0">
                            {/* Case number or fallback */}
                            <p className="font-headline text-[15px] font-extrabold tracking-tight text-slate-900">
                                {caseItem.case_number
                                    ? `Case #${caseItem.case_number}`
                                    : 'Case (pending number)'}
                            </p>
                            {caseItem.summary && (
                                <p className="mt-0.5 truncate text-sm text-slate-500">{caseItem.summary}</p>
                            )}
                        </div>

                        <StatusBadge
                            variant="pill"
                            status={caseItem.status}
                            showIcon={isUnderReview}
                            label={isUnderReview ? 'Under Review' : undefined}
                            icon={isUnderReview ? Hourglass : undefined}
                        />
                    </div>

                    {/* Meta row: submitted date · agency · source */}
                    <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-slate-500">
                        <span className="inline-flex items-center gap-1">
                            <span className="material-symbols-outlined text-[14px]" aria-hidden="true">calendar_today</span>
                            Submitted {formatDate(caseItem.created_at)}
                        </span>
                        {agency && (
                            <span className="inline-flex items-center gap-1">
                                <span className="material-symbols-outlined text-[14px]" aria-hidden="true">apartment</span>
                                {agency.name}
                            </span>
                        )}
                        <span className="inline-flex items-center gap-1">
                            <span className="material-symbols-outlined text-[14px]" aria-hidden="true">
                                {caseItem.source === 'self_filed' ? 'person' : 'business'}
                            </span>
                            {caseItem.source === 'self_filed' ? 'Self-Filed' : 'Filed by Office'}
                        </span>
                    </div>

                    {/* Categories */}
                    {caseItem.categories?.length > 0 && (
                        <div className="mt-3 flex flex-wrap gap-1.5">
                            {caseItem.categories.map((category) => (
                                <span
                                    key={category.id ?? category.name}
                                    className="rounded-full bg-blue-50 px-2.5 py-0.5 text-[11px] font-medium text-blue-700"
                                >
                                    {category.name}
                                </span>
                            ))}
                        </div>
                    )}

                    {/* Under Review message for self-filed drafts */}
                    {isUnderReview && (
                        <p className="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-700">
                            <span className="material-symbols-outlined text-[14px]" aria-hidden="true">hourglass_top</span>
                            A Case Manager is reviewing your submission
                        </p>
                    )}
                </div>
            </div>
        </Link>
    );
}

function StatCard({ icon, label, value, iconClass }) {
    return (
        <div className="rounded-md border border-slate-300 bg-white p-4 shadow-sm">
            <div className="flex items-start justify-between gap-2">
                <p className="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">{label}</p>
                <span className={`material-symbols-outlined shrink-0 text-[20px] ${iconClass}`}>{icon}</span>
            </div>
            <p className="mt-2 font-headline text-3xl font-extrabold tracking-tight text-slate-900">
                {value ?? 0}
            </p>
        </div>
    );
}

function EmptyState() {
    return (
        <div className="mt-3 rounded-md border border-dashed border-slate-300 bg-slate-50/50 px-6 py-16 text-center">
            <span className="material-symbols-outlined text-3xl text-slate-300">folder_off</span>
            <h2 className="mt-3 font-headline text-lg font-bold text-slate-900">No cases yet</h2>
            <p className="mx-auto mt-2 max-w-sm text-sm text-slate-500">
                You haven't filed any cases yet. Start by filing a new case and a Case Manager will review it.
            </p>
            <Link
                href="/intake"
                className="mt-6 inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary-container"
            >
                <span className="material-symbols-outlined text-[18px]">add_circle</span>
                File a New Case
            </Link>
        </div>
    );
}

export default function Dashboard({ cases, caseStats }) {
    const { auth } = usePage().props;
    const caseList = cases?.data ?? [];
    const stats = caseStats ?? {};

    return (
        <OfwLayout title="My Cases">
            {/* Navy record header — matches the case detail page */}
            <header className="mt-4 rounded-lg bg-primary px-6 py-8 text-white shadow-2xl sm:px-8">
                <div className="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-[11px] font-bold uppercase tracking-[0.2em] text-primary-fixed-dim">Case Dashboard</p>
                        <h1 className="mt-2 font-headline text-2xl font-extrabold tracking-tight sm:text-3xl">
                            Welcome back, {auth?.user?.name}
                        </h1>
                        <p className="mt-1.5 text-sm text-primary-fixed/90">
                            Here's where your requests stand today.
                        </p>
                    </div>
                    <div className="flex shrink-0 flex-wrap items-center gap-3 self-start sm:self-center">
                        <Link
                            href={route('ofw.profile.edit')}
                            className="inline-flex items-center gap-1.5 rounded-lg border border-white/40 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-white/10"
                        >
                            <span className="material-symbols-outlined text-[18px]">person</span>
                            View Profile
                        </Link>
                        {caseList.length > 0 && (
                            <Link
                                href="/intake"
                                className="inline-flex items-center gap-1.5 rounded-lg bg-white px-4 py-2 text-sm font-semibold text-primary shadow-sm transition-colors hover:bg-primary-fixed"
                            >
                                <span className="material-symbols-outlined text-[18px]">add</span>
                                File a New Case
                            </Link>
                        )}
                    </div>
                </div>
            </header>

            {/* Overview stat cards */}
            <section className="mt-8">
                <h2 className="flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">
                    <span className="h-2 w-2 rounded-full bg-primary"></span>
                    Overview
                </h2>
                <div className="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <StatCard icon="inbox" label="Total Requests" value={stats.total} iconClass="text-primary" />
                    <StatCard icon="hourglass_top" label="Under Review" value={stats.under_review} iconClass="text-amber-500" />
                    <StatCard icon="progress_activity" label="In Progress" value={stats.in_progress} iconClass="text-blue-500" />
                    <StatCard icon="task_alt" label="Completed" value={stats.completed} iconClass="text-emerald-500" />
                </div>
            </section>

            {/* Case list section */}
            <section className="mt-8">
                <h2 className="flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">
                    <span className="h-2 w-2 rounded-full bg-primary"></span>
                    Your cases
                </h2>
                {caseList.length === 0 ? (
                    <EmptyState />
                ) : (
                    <div className="mt-3 space-y-2">
                        {caseList.map((caseItem) => (
                            <CaseCard key={caseItem.id} caseItem={caseItem} />
                        ))}
                    </div>
                )}
            </section>

            {/* Pagination links */}
            {cases?.links && cases.links.length > 3 && (
                <nav className="mt-8 flex items-center justify-center gap-1" aria-label="Pagination">
                    {cases.links.map((link, index) => (
                        <Link
                            key={index}
                            href={link.url ?? '#'}
                            className={`rounded px-3 py-1.5 text-sm ${
                                link.active
                                    ? 'bg-primary font-semibold text-white'
                                    : link.url
                                      ? 'text-slate-600 hover:bg-slate-100'
                                      : 'cursor-not-allowed text-slate-300'
                            }`}
                            preserveScroll
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </nav>
            )}
        </OfwLayout>
    );
}
