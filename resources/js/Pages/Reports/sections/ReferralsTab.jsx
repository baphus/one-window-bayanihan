import { usePage } from '@inertiajs/react';
import {
    BarRows,
    EmptyLine,
    LoadingBlock,
    ReportCard,
    StackedBars,
    topSlice,
    toSlices,
    totalOf,
    useSettledProp,
} from '@/Pages/Reports/sections/SeniorReport';

function AgencyTableSection() {
    const [scorecard, settled] = useSettledProp('agencyScorecard');
    const rows = Array.isArray(scorecard) ? scorecard : [];
    const tableRows = rows.map((row, index) => ({
        key: row.agency ?? index,
        agency: row.agency ?? 'Unknown',
        active: Math.max(0, Number(row.total ?? 0) - Number(row.completed ?? 0)),
        finished: Number(row.completed ?? 0),
    }));
    const top = tableRows.slice().sort((a, b) => b.active + b.finished - (a.active + a.finished))[0] ?? null;

    return (
        <ReportCard
            title="Agency numbers"
            takeaway={top ? `${top.agency} handles the most referrals.` : null}
        >
            {!settled ? (
                <LoadingBlock />
            ) : tableRows.length === 0 ? (
                <EmptyLine message="No agency data to show yet." />
            ) : (
                <>
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-sm">
                            <thead>
                                <tr className="bg-primary text-white">
                                    <th className="border border-slate-300 px-3 py-2 text-left">Agency</th>
                                    <th className="border border-slate-300 px-3 py-2 text-right">Active</th>
                                    <th className="border border-slate-300 px-3 py-2 text-right">Finished</th>
                                </tr>
                            </thead>
                            <tbody>
                                {tableRows.map((row) => (
                                    <tr key={row.key} className="odd:bg-slate-50 dark:odd:bg-slate-800">
                                        <td className="border border-slate-300 px-3 py-2 font-bold">{row.agency}</td>
                                        <td className="border border-slate-300 px-3 py-2 text-right font-bold">{row.active}</td>
                                        <td className="border border-slate-300 px-3 py-2 text-right font-bold">{row.finished}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <p className="mt-2 text-xs text-slate-500 dark:text-slate-400">
                        Overdue per agency is not available yet.
                    </p>
                </>
            )}
        </ReportCard>
    );
}

function AgencyPictureSection() {
    const [scorecard, settled] = useSettledProp('agencyScorecard');
    const scorecardRows = Array.isArray(scorecard) ? scorecard : [];
    const perAgency = scorecardRows.map((row) => ({
        agency: row.agency ?? 'Unknown',
        active: Math.max(0, Number(row.total ?? 0) - Number(row.completed ?? 0)),
        finished: Number(row.completed ?? 0),
    }));
    const rows = perAgency.map((row, index) => ({
        key: `${row.agency}-${index}`,
        label: row.agency,
        total: row.active + row.finished,
        segments: [
            { key: 'active', label: 'Active', count: row.active, hex: '#0b5a8c' },
            { key: 'finished', label: 'Finished', count: row.finished, hex: '#059669' },
        ],
    }));
    const grandTotal = totalOf(rows.flatMap((row) => row.segments));
    const allFinishedAhead = perAgency.length > 0 && perAgency.every((row) => row.finished >= row.active);

    return (
        <ReportCard
            title="Referral picture per agency"
            takeaway={
                grandTotal === 0
                    ? null
                    : allFinishedAhead
                        ? 'Finished referrals outnumber active ones at every agency.'
                        : 'Some agencies still carry more active referrals than finished ones.'
            }
        >
            {!settled ? (
                <LoadingBlock />
            ) : grandTotal === 0 ? (
                <EmptyLine message="No agency data to show yet." />
            ) : (
                <StackedBars rows={rows} />
            )}
        </ReportCard>
    );
}

function RejectedSection() {
    const { props } = usePage();
    const rejected = Number(props.kpis?.rejectedReferrals ?? 0);
    const [reasons, settled] = useSettledProp('rejectionReasonDistribution');
    const slices = toSlices(reasons, { humanizeLabels: true }).sort((a, b) => b.count - a.count);
    const top = topSlice(slices);

    return (
        <ReportCard
            title="Rejected referrals"
            takeaway={top && top.count > 0 ? `${top.label} is the top reason.` : null}
        >
            <p className="text-2xl font-black text-slate-900 dark:text-slate-100">
                {rejected}
                <span className="ml-2 align-middle text-xs font-bold text-slate-500 dark:text-slate-400">
                    rejected
                </span>
            </p>
            <div className="mt-4">
                {!settled ? (
                    <LoadingBlock />
                ) : slices.length === 0 || totalOf(slices) === 0 ? (
                    <EmptyLine message="Reason breakdown is not available yet." />
                ) : (
                    <BarRows items={slices.map((slice) => ({ ...slice, hex: '#e11d48' }))} />
                )}
            </div>
        </ReportCard>
    );
}

function FirstResponseSection() {
    const [, settled] = useSettledProp('agencyFirstResponse');
    return (
        <ReportCard title="First response per agency">
            {!settled ? (
                <LoadingBlock />
            ) : (
                <EmptyLine message="First-response times are not available yet." />
            )}
        </ReportCard>
    );
}

function RequestTypeSection() {
    const [, settled] = useSettledProp('clientRequestTypeDistribution');
    return (
        <ReportCard title="Client requests by type">
            {!settled ? (
                <LoadingBlock />
            ) : (
                <EmptyLine message="Client request types are not available yet." />
            )}
        </ReportCard>
    );
}

export default function ReferralsTab() {
    return (
        <div className="space-y-5">
            <AgencyTableSection />
            <AgencyPictureSection />
            <RejectedSection />
            <FirstResponseSection />
            <RequestTypeSection />
        </div>
    );
}
