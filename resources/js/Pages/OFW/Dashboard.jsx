import { Link } from '@inertiajs/react';
import OfwLayout from '@/Layouts/OfwLayout';

const STATUS_BADGES = {
    DRAFT: { label: 'Draft', bg: 'bg-gray-100', text: 'text-gray-700' },
    BEING_PREPARED: { label: 'Being Prepared', bg: 'bg-blue-50', text: 'text-blue-700' },
    IN_PROGRESS: { label: 'In Progress', bg: 'bg-indigo-50', text: 'text-indigo-700' },
    RESOLVED: { label: 'Resolved', bg: 'bg-emerald-50', text: 'text-emerald-700' },
    ARCHIVED: { label: 'Archived', bg: 'bg-slate-100', text: 'text-slate-600' },
};

function formatDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-PH', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function StatusBadge({ status, source }) {
    // Self-filed drafts show as "Under Review" with amber badge
    if (status === 'DRAFT' && source === 'self_filed') {
        return (
            <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">
                <span className="material-symbols-outlined text-[14px]">hourglass_top</span>
                Under Review
            </span>
        );
    }

    const badge = STATUS_BADGES[status] ?? STATUS_BADGES.DRAFT;
    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ${badge.bg} ${badge.text}`}>
            {badge.label}
        </span>
    );
}

function CaseCard({ caseItem }) {
    const isUnderReview = caseItem.status === 'DRAFT' && caseItem.source === 'self_filed';

    return (
        <Link
            href={route('ofw.case.show', caseItem.id)}
            className="block rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition-all hover:border-blue-300 hover:shadow-md"
        >
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                    {/* Case number or fallback */}
                    <p className="text-sm font-bold text-gray-900">
                        {caseItem.case_number
                            ? `Case #${caseItem.case_number}`
                            : 'Case (pending number)'}
                    </p>

                    {/* Submission date */}
                    <p className="mt-1 text-xs text-gray-500">
                        Submitted {formatDate(caseItem.created_at)}
                    </p>
                </div>

                <StatusBadge status={caseItem.status} source={caseItem.source} />
            </div>

            {/* Under Review message for self-filed drafts */}
            {isUnderReview && (
                <p className="mt-3 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-700">
                    A Case Manager is reviewing your submission
                </p>
            )}

            {/* Categories */}
            {caseItem.categories?.length > 0 && (
                <div className="mt-3 flex flex-wrap gap-1.5">
                    {caseItem.categories.map((category) => (
                        <span
                            key={category.id ?? category.name}
                            className="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600"
                        >
                            {category.name}
                        </span>
                    ))}
                </div>
            )}

            {/* Source indicator */}
            <div className="mt-3 flex items-center gap-1.5 text-xs text-gray-500">
                <span className="material-symbols-outlined text-[14px]">
                    {caseItem.source === 'self_filed' ? 'person' : 'apartment'}
                </span>
                {caseItem.source === 'self_filed' ? 'Self-Filed' : 'Filed by Office'}
            </div>
        </Link>
    );
}

function EmptyState() {
    return (
        <div className="rounded-lg border-2 border-dashed border-gray-200 bg-white px-6 py-16 text-center">
            <span className="material-symbols-outlined text-5xl text-gray-300">folder_off</span>
            <h2 className="mt-4 text-lg font-semibold text-gray-800">No cases yet</h2>
            <p className="mx-auto mt-2 max-w-sm text-sm text-gray-500">
                You haven't filed any cases yet. Start by filing a new case and a Case Manager will review it.
            </p>
            <Link
                href="/intake"
                className="mt-6 inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700"
            >
                <span className="material-symbols-outlined text-[18px]">add_circle</span>
                File a New Case
            </Link>
        </div>
    );
}

export default function Dashboard({ cases }) {
    const caseList = cases?.data ?? [];

    return (
        <OfwLayout title="My Cases">
            {/* Page header */}
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-bold text-gray-900">My Cases</h1>
                    <p className="mt-1 text-sm text-gray-500">
                        Track the progress of your cases
                    </p>
                </div>
                {caseList.length > 0 && (
                    <Link
                        href="/intake"
                        className="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700"
                    >
                        <span className="material-symbols-outlined text-[18px]">add</span>
                        File a New Case
                    </Link>
                )}
            </div>

            {/* Case list or empty state */}
            <div className="mt-6">
                {caseList.length === 0 ? (
                    <EmptyState />
                ) : (
                    <div className="space-y-3">
                        {caseList.map((caseItem) => (
                            <CaseCard key={caseItem.id} caseItem={caseItem} />
                        ))}
                    </div>
                )}
            </div>

            {/* Pagination links */}
            {cases?.links && cases.links.length > 3 && (
                <nav className="mt-8 flex items-center justify-center gap-1" aria-label="Pagination">
                    {cases.links.map((link, index) => (
                        <Link
                            key={index}
                            href={link.url ?? '#'}
                            className={`rounded px-3 py-1.5 text-sm ${
                                link.active
                                    ? 'bg-blue-600 font-semibold text-white'
                                    : link.url
                                      ? 'text-gray-600 hover:bg-gray-100'
                                      : 'cursor-not-allowed text-gray-300'
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
