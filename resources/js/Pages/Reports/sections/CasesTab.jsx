import { usePage } from '@inertiajs/react';
import {
    DonutWithList,
    EmptyLine,
    ensureDistinctHues,
    FunnelSteps,
    LoadingBlock,
    ReportCard,
    topNamedWithTail,
    topSlice,
    toSlices,
    totalOf,
    useSettledProp,
} from '@/Pages/Reports/sections/SeniorReport';
import LazyTrendChart from '@/Pages/Reports/sections/LazyTrendChart';
import ReferralTrendsSection from '@/Pages/Reports/sections/ReferralTrendsSection';
import GeographicMapSection from '@/Pages/Reports/sections/GeographicMapSection';

function StatusSection() {
    const [distribution, settled] = useSettledProp('referralStatusDistribution');
    if (!settled) {
        return (
            <ReportCard title="Where referrals stand">
                <LoadingBlock />
            </ReportCard>
        );
    }
    const slices = toSlices(distribution, { humanizeLabels: true });
    if (slices.length === 0 || totalOf(slices) === 0) {
        return (
            <ReportCard title="Where referrals stand">
                <EmptyLine message="No referral data to show yet." />
            </ReportCard>
        );
    }
    const top = topSlice(slices);
    return (
        <ReportCard
            title="Where referrals stand"
            takeaway={`${top.label} is the largest group at ${top.percent}%.`}
            dataTour="reports-charts"
        >
            <div className="mb-6">
                <FunnelSteps slices={slices} />
            </div>
            <DonutWithList slices={slices} large />
        </ReportCard>
    );
}

function TrendsSection({ avgFinish }) {
    const { props } = usePage();
    const [closed, closedSettled] = useSettledProp('closedCasesOverTime');
    const filedData = Array.isArray(props.casesOverTime?.datasets?.[0]?.data)
        ? props.casesOverTime.datasets[0].data
        : [];
    const closedData = Array.isArray(closed?.datasets?.[0]?.data) ? closed.datasets[0].data : [];
    const hasClosed = closedData.length > 0;
    const months = Math.max(1, filedData.length);
    const gap = hasClosed
        ? Math.round(
            (filedData.reduce((sum, value) => sum + Number(value ?? 0), 0) -
                closedData.reduce((sum, value) => sum + Number(value ?? 0), 0)) / months,
        )
        : null;

    return (
        <ReportCard
            title="How we are doing over time"
            takeaway={
                gap == null
                    ? 'Cases filed and referrals finished, month by month.'
                    : `Filings run ahead of closures by about ${gap} cases a month.`
            }
        >
            <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <LazyTrendChart lazyKey="casesOverTime" title="Cases filed per month" />
                <ReferralTrendsSection />
            </div>
            <div className="mt-4">
                {!closedSettled ? (
                    <LoadingBlock />
                ) : hasClosed ? (
                    <LazyTrendChart lazyKey="closedCasesOverTime" title="Closed per month" />
                ) : (
                    <EmptyLine message="Closures per month are not available yet." />
                )}
            </div>
            {avgFinish}
        </ReportCard>
    );
}

function AvgFinishLine() {
    const [value, settled] = useSettledProp('avgReferralCompletion');
    if (!settled) return <LoadingBlock />;
    if (value === null || value === undefined) {
        return <EmptyLine message="Average finish time is not available yet." />;
    }
    return (
        <p className="mt-4 text-sm text-slate-500 dark:text-slate-400">
            Average finish time: <strong>{Number(value)} days</strong>
        </p>
    );
}

function MiniBox({ heading, lines }) {
    return (
        <div className="rounded-xl border-2 border-slate-300 p-4 dark:border-slate-600">
            <h3 className="text-xs font-extrabold uppercase tracking-wider text-primary">{heading}</h3>
            <div className="mt-2 space-y-1 text-sm">
                {lines.map((line) => (
                    <p key={line.label}>
                        {line.label}: <strong>{line.value}</strong>
                    </p>
                ))}
            </div>
        </div>
    );
}

function DemographicsSection({ mapProps }) {
    const [clientType, clientTypeSettled] = useSettledProp('clientTypeDistribution');
    const [issues, issuesSettled] = useSettledProp('caseIssueDistribution');
    const [service, serviceSettled] = useSettledProp('mostRequestedService');
    const [age, ageSettled] = useSettledProp('ageGroupDistribution');
    const [vulnerability, vulnSettled] = useSettledProp('vulnerabilityDistribution');
    const { props } = usePage();
    const gender = props.genderDistribution ?? null;

    const clientTypeSlices = toSlices(clientType);
    const hasMiniData = clientTypeSlices.length > 0 || service != null;
    const minisSettled = clientTypeSettled && serviceSettled;
    const issueRows = Array.isArray(issues) ? issues : [];
    const issueTotal = issueRows.reduce((sum, row) => sum + Number(row?.count ?? 0), 0);
    const issueSlices = ensureDistinctHues(
        issueRows
            .map((row, index) => ({
                key: row.name ?? index,
                label: row.name,
                count: Number(row.count ?? 0),
                hex: row.color ?? null,
                percent: issueTotal > 0 ? Math.round((Number(row.count ?? 0) / issueTotal) * 100) : 0,
            }))
            .sort((a, b) => b.count - a.count),
    );
    const ageSlices = toSlices(age);
    const ageTotal = totalOf(ageSlices);
    const vulnSlices = toSlices(vulnerability, { exclude: ['None'] }).sort((a, b) => b.count - a.count);
    const sexSlices = toSlices(gender, { humanizeLabels: true }).filter((slice) => slice.count > 0);

    return (
        <ReportCard title="Who we serve">
            {!minisSettled ? (
                <LoadingBlock />
            ) : !hasMiniData ? (
                <EmptyLine message="No client data to show yet." />
            ) : (
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <MiniBox
                        heading="Clients"
                        lines={clientTypeSlices.map((slice) => ({ label: slice.label, value: slice.count }))}
                    />
                    <MiniBox
                        heading="Top service"
                        lines={service ? [{ label: service.name, value: service.value }] : []}
                    />
                </div>
            )}

            <h3 className="mb-2 mt-6 text-xs font-extrabold uppercase tracking-wider text-primary">
                Top needs
            </h3>
            {!issuesSettled ? (
                <LoadingBlock />
            ) : issueSlices.length === 0 || totalOf(issueSlices) === 0 ? (
                <EmptyLine message="No issue data to show yet." />
            ) : (
                <>
                    <p className="mb-3 text-sm text-slate-500 dark:text-slate-400">
                        {topSlice(issueSlices).label} is the most common need.
                    </p>
                    <DonutWithList slices={issueSlices} showAll />
                </>
            )}

            {sexSlices.length > 0 ? (
                <div className="mt-6">
                    <h3 className="mb-3 text-xs font-extrabold uppercase tracking-wider text-primary">By sex</h3>
                    <DonutWithList slices={sexSlices} />
                </div>
            ) : null}

            <h3 className="mb-2 mt-6 text-xs font-extrabold uppercase tracking-wider text-primary">
                Age groups{ageTotal > 0 ? ` (of ${ageTotal} clients with age recorded)` : ''}
            </h3>
            {!ageSettled ? (
                <LoadingBlock />
            ) : ageSlices.length === 0 || ageTotal === 0 ? (
                <EmptyLine message="No age data to show yet." />
            ) : (
                <>
                    <p className="mb-3 text-sm text-slate-500 dark:text-slate-400">
                        Most clients are aged {topSlice(ageSlices).label}.
                    </p>
                    <DonutWithList slices={ageSlices} />
                </>
            )}

            <h3 className="mb-2 mt-6 text-xs font-extrabold uppercase tracking-wider text-primary">
                Clients needing extra care (a client may be counted more than once)
            </h3>
            {!vulnSettled ? (
                <LoadingBlock />
            ) : vulnSlices.length === 0 || totalOf(vulnSlices) === 0 ? (
                <EmptyLine message="No extra-care data to show yet." />
            ) : (
                <>
                    <p className="mb-3 text-sm text-slate-500 dark:text-slate-400">
                        {topSlice(vulnSlices).label} is the largest extra-care group.
                    </p>
                    <DonutWithList slices={vulnSlices} />
                </>
            )}

            <div className="mt-6">
                <GeographicMapSection {...mapProps} />
            </div>
        </ReportCard>
    );
}

/**
 * Infographic-style ranked list for "Last country worked in": rank numerals
 * with count + percent and bars scaled to #1. A genuine long tail renders as
 * one honest aggregate note — never as a fake 6th country.
 */
function CountryRankList({ top, tailCount, tailCountries, tailSingle = 'country', tailPlural = 'countries' }) {
    const peak = Math.max(1, ...top.map((slice) => Number(slice.count ?? 0)));
    return (
        <div>
            <ol className="space-y-4">
                {top.map((slice, index) => (
                    <li key={slice.key}>
                        <div className="flex items-baseline gap-3">
                            <span className="w-6 shrink-0 text-center text-sm font-black text-primary">
                                {index + 1}
                            </span>
                            <span className="min-w-0 flex-1 truncate text-sm font-bold text-slate-800 dark:text-slate-200">
                                {slice.label}
                            </span>
                            <span className="shrink-0 text-sm font-black text-slate-900 dark:text-slate-100">
                                {slice.count} ({slice.percent}%)
                            </span>
                        </div>
                        <div className="ml-9 mt-1.5 h-3 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                            <div
                                className="h-full rounded-full"
                                style={{
                                    width: `${Math.max(2, Math.round((Number(slice.count ?? 0) / peak) * 100))}%`,
                                    backgroundColor: slice.hex ?? '#0b5a8c',
                                }}
                            />
                        </div>
                    </li>
                ))}
            </ol>
            {tailCount > 0 && tailCountries > 0 ? (
                <p className="ml-9 mt-4 border-t border-dashed border-slate-300 pt-3 text-sm text-slate-500 dark:border-slate-600 dark:text-slate-400">
                    Plus <strong>{tailCount}</strong> more clients across {tailCountries} other{' '}
                    {tailCountries === 1 ? tailSingle : tailPlural}.
                </p>
            ) : null}
        </div>
    );
}

function JobsSection() {
    const [countries, countriesSettled] = useSettledProp('employmentDistribution');
    const [occupations, occupationsSettled] = useSettledProp('employmentOccupationBreakdown');

    const countrySlices = toSlices(countries);
    const {
        top: topCountries,
        tailCount: countryTailCount,
        tailCountries: countryTailCountries,
    } = topNamedWithTail(countrySlices, 5);
    const topCountry = topSlice(topCountries);
    const occupationSlices = toSlices(occupations);
    const {
        top: topJobs,
        tailCount: jobTailCount,
        tailCountries: jobTailTypes,
    } = topNamedWithTail(
        occupationSlices,
        5,
        occupations?.total_distinct != null ? occupations.total_distinct : null,
    );
    const topJob = topSlice(topJobs);

    return (
        <ReportCard title="Work and jobs">
            <h3 className="mb-2 text-xs font-extrabold uppercase tracking-wider text-primary">Last country worked in</h3>
            {!countriesSettled ? (
                <LoadingBlock />
            ) : countrySlices.length === 0 || totalOf(countrySlices) === 0 ? (
                <EmptyLine message="No country data to show yet." />
            ) : (
                <>
                    <p className="mb-3 text-sm text-slate-500 dark:text-slate-400">
                        Most clients last worked in {topCountry.label} ({topCountry.count}).
                    </p>
                    <CountryRankList
                        top={topCountries}
                        tailCount={countryTailCount}
                        tailCountries={countryTailCountries}
                    />
                </>
            )}

            <h3 className="mb-2 mt-6 text-xs font-extrabold uppercase tracking-wider text-primary">Common jobs</h3>
            {!occupationsSettled ? (
                <LoadingBlock />
            ) : occupationSlices.length === 0 || totalOf(occupationSlices) === 0 ? (
                <EmptyLine message="No job data to show yet." />
            ) : (
                <>
                    <p className="mb-3 text-sm text-slate-500 dark:text-slate-400">
                        {topJob.label} ({topJob.count}) is the most common job.
                    </p>
                    <CountryRankList
                        top={topJobs}
                        tailCount={jobTailCount}
                        tailCountries={jobTailTypes}
                        tailSingle="job type"
                        tailPlural="job types"
                    />
                </>
            )}
        </ReportCard>
    );
}

function AssistanceSection() {
    const [categories, settled] = useSettledProp('categoryDistribution');
    const rows = Array.isArray(categories) ? categories : [];
    const items = ensureDistinctHues(
        rows
            .map((row, index) => ({
                key: row.name ?? index,
                label: row.name,
                count: Number(row.count ?? 0),
                hex: row.color ?? null,
                percent: Number(row.percentage ?? 0),
            }))
            .sort((a, b) => b.count - a.count),
    );

    return (
        <ReportCard
            title="Assistance mix"
            takeaway={totalOf(items) > 0 ? `${topSlice(items).label} is the most given assistance.` : null}
        >
            {!settled ? (
                <LoadingBlock />
            ) : totalOf(items) === 0 ? (
                <EmptyLine message="No assistance data to show yet." />
            ) : (
                <DonutWithList slices={items} showAll />
            )}
        </ReportCard>
    );
}

function CaseMixSection() {
    const [status, statusSettled] = useSettledProp('caseStatusDistribution');
    const [clientType, clientTypeSettled] = useSettledProp('clientTypeDistribution');
    const statusSlices = toSlices(status, { humanizeLabels: true });
    const clientTypeSlices = toSlices(clientType, { humanizeLabels: true });

    return (
        <ReportCard
            title="Case mix"
            takeaway={
                totalOf(statusSlices) > 0
                    ? `Most cases are ${topSlice(statusSlices).label.toLowerCase()}.`
                    : null
            }
        >
            <div className="grid grid-cols-1 gap-8 xl:grid-cols-2">
                <div>
                    <h3 className="mb-3 text-xs font-extrabold uppercase tracking-wider text-primary">By status</h3>
                    {!statusSettled ? (
                        <LoadingBlock />
                    ) : statusSlices.length === 0 || totalOf(statusSlices) === 0 ? (
                        <EmptyLine message="No case status data to show yet." />
                    ) : (
                        <DonutWithList slices={statusSlices} />
                    )}
                </div>
                <div>
                    <h3 className="mb-3 text-xs font-extrabold uppercase tracking-wider text-primary">By client type</h3>
                    {!clientTypeSettled ? (
                        <LoadingBlock />
                    ) : clientTypeSlices.length === 0 || totalOf(clientTypeSlices) === 0 ? (
                        <EmptyLine message="No client type data to show yet." />
                    ) : (
                        <DonutWithList slices={clientTypeSlices} />
                    )}
                </div>
            </div>
        </ReportCard>
    );
}

export default function CasesTab({ mapProps }) {
    return (
        <div className="space-y-5">
            <StatusSection />
            <CaseMixSection />
            <TrendsSection avgFinish={<AvgFinishLine />} />
            <DemographicsSection mapProps={mapProps} />
            <JobsSection />
            <AssistanceSection />
        </div>
    );
}
