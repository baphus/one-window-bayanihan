import { Link, usePage } from '@inertiajs/react';
import { Hourglass } from 'lucide-react';
import OfwLayout from '@/Layouts/OfwLayout';
import StatusBadge from '@/Components/ui/StatusBadge';
import { DashboardTable, EmptyState, PageHeader, QuickActions, SectionCard, StatRow } from '@/Components/Dashboard/primitives';
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

export default function Dashboard({ cases, caseStats }) {
    const { auth } = usePage().props;
    const caseList = cases?.data ?? [];
    const stats = caseStats ?? {};
    const fileCaseHref = safeRoute('ofw.intake', undefined, '/my-cases/intake');

    return (
        <OfwLayout title="My Cases">
            <PageHeader
                eyebrow="Case Dashboard"
                title={`Welcome back, ${auth?.user?.name}`}
                subtitle="Here's where your requests stand today."
            >
                <QuickActions
                    actions={[
                        { href: route('ofw.profile.edit'), label: 'View Profile', icon: 'person' },
                        ...(caseList.length > 0
                            ? [{ href: fileCaseHref, label: 'File a New Case', icon: 'add', primary: true }]
                            : []),
                    ]}
                />
            </PageHeader>

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
            <SectionCard title="Your cases" dataTour="ofw-cases" bodyClassName="">
                {caseList.length === 0 ? (
                    <EmptyState
                        message="You haven't filed any cases yet. Start by filing a new case and a Case Manager will review it."
                        href={fileCaseHref}
                        actionLabel="File a New Case"
                    />
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
