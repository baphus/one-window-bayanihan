import {
    BarRows,
    EmptyLine,
    ensureDistinctHues,
    LoadingBlock,
    ReportCard,
    StackedBars,
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
        <ReportCard title="Agency numbers" takeaway={top ? `${top.agency} handles the most referrals.` : null}>
            {!settled ? <LoadingBlock /> : tableRows.length === 0 ? (
                <EmptyLine message="No agency data to show yet." />
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full border-collapse text-sm">
                        <thead><tr className="bg-primary text-white">
                            <th className="border border-slate-300 px-3 py-2 text-left">Agency</th>
                            <th className="border border-slate-300 px-3 py-2 text-right">Active</th>
                            <th className="border border-slate-300 px-3 py-2 text-right">Finished</th>
                        </tr></thead>
                        <tbody>{tableRows.map((row) => (
                            <tr key={row.key} className="odd:bg-slate-50 dark:odd:bg-slate-800">
                                <td className="border border-slate-300 px-3 py-2 font-bold">{row.agency}</td>
                                <td className="border border-slate-300 px-3 py-2 text-right font-bold">{row.active}</td>
                                <td className="border border-slate-300 px-3 py-2 text-right font-bold">{row.finished}</td>
                            </tr>
                        ))}</tbody>
                    </table>
                </div>
            )}
        </ReportCard>
    );
}

function AgencyPictureSection() {
    const [scorecard, settled] = useSettledProp('agencyScorecard');
    const rows = ensureDistinctHues((Array.isArray(scorecard) ? scorecard : []).map((row, index) => ({
        key: `${row.agency}-${index}`,
        label: row.agency ?? 'Unknown',
        count: Math.max(0, Number(row.total ?? 0) - Number(row.completed ?? 0)) + Number(row.completed ?? 0),
    })));

    return (
        <ReportCard title="Referral picture per agency" takeaway="Total referrals by agency.">
            {!settled ? <LoadingBlock /> : totalOf(rows) === 0 ? (
                <EmptyLine message="No agency data to show yet." />
            ) : <BarRows items={rows} vertical />}
        </ReportCard>
    );
}

function MostUsedServicesSection() {
    const [services, settled] = useSettledProp('mostUsedServices');
    const rows = ensureDistinctHues((Array.isArray(services) ? services : []).map((service, index) => ({
        key: index,
        label: `${service.name} — ${service.agency}`,
        count: Number(service.count ?? 0),
    })));

    return (
        <ReportCard title="Most used services" takeaway="Applied services by agency.">
            {!settled ? <LoadingBlock /> : totalOf(rows) === 0 ? (
                <EmptyLine message="No applied services to show yet." />
            ) : <BarRows items={rows} />}
        </ReportCard>
    );
}

function OpenReferralBacklogSection() {
    const [backlog, settled] = useSettledProp('openReferralBacklog');
    const statuses = [
        { key: 'pending', label: 'Pending', hex: '#d97706' },
        { key: 'processing', label: 'Processing', hex: '#005288' },
        { key: 'for_compliance', label: 'For compliance', hex: '#ea580c' },
    ];
    const rows = (Array.isArray(backlog) ? backlog : []).map((row) => {
        const segments = statuses.map((status) => ({ ...status, count: Number(row[status.key] ?? 0) }));
        return { key: row.label, label: row.label, total: totalOf(segments), segments };
    });

    return (
        <ReportCard title="Current open-referral backlog" takeaway="Age since referral creation. Current snapshot; selected date range does not apply.">
            {!settled ? <LoadingBlock /> : rows.length === 0 || rows.every((row) => row.total === 0) ? (
                <EmptyLine message="No open referrals to show yet." />
            ) : <StackedBars rows={rows} />}
        </ReportCard>
    );
}

export default function ReferralsTab() {
    return (
        <div className="grid min-w-0 gap-4 xl:grid-cols-2">
            <AgencyPictureSection />
            <AgencyTableSection />
            <div className="xl:col-span-2">
                <MostUsedServicesSection />
            </div>
            <OpenReferralBacklogSection />
        </div>
    );
}
