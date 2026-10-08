import { Link, usePage } from '@inertiajs/react';
import { Bar, Pie } from 'react-chartjs-2';
import { ArcElement, BarElement, CategoryScale, Chart as ChartJS, Legend, LinearScale, Tooltip } from 'chart.js';
import GettingStartedChecklist from '@/Components/GettingStartedChecklist';
import StatusBadge from '@/Components/ui/StatusBadge';
import { humanizeStatus } from '@/lib/statusLabels';
import { formatRelativeTime } from '@/lib/relativeTime';
import safeRoute from '@/utils/safeRoute';
import {
    EmptyState,
    DashboardTable,
    PageHeader,
    QuickActions,
    SectionCard,
    ViewAllLink,
    formatCount,
    safeArray,
    toneDot,
    toneHex,
} from '@/Components/Dashboard/primitives';

ChartJS.register(ArcElement, BarElement, CategoryScale, Legend, LinearScale, Tooltip);

const OVERDUE_DAYS = 5;
const REFERRAL_STATUSES = ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE', 'COMPLETED', 'REJECTED'];
const STATUS_TONES = {
    PENDING: 'amber',
    PROCESSING: 'blue',
    FOR_COMPLIANCE: 'orange',
    COMPLETED: 'emerald',
    REJECTED: 'rose',
};

function toAgeDays(value, fallback = 0) {
    if (value === undefined || value === null) {
        return fallback;
    }

    const parsed = Number(value);

    return Number.isFinite(parsed) && parsed >= 0 ? Math.floor(parsed) : fallback;
}

function AgeFlag({ days }) {
    const parsed = toAgeDays(days, 0);
    const overdue = parsed >= OVERDUE_DAYS;

    return (
        <span
            className={
                overdue
                    ? 'inline-flex items-center rounded-full bg-rose-600 px-2 py-0.5 text-[10px] font-black text-white'
                    : 'text-[10px] font-bold uppercase tracking-widest text-slate-400'
            }
        >
            {parsed}d
        </span>
    );
}

function formatReferredAt(iso) {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const short = new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric' }).format(date);

    return `${short} · ${formatRelativeTime(iso)}`;
}

function referralNote(item) {
    const stamp = formatReferredAt(item.referred_at);

    return [item.service, stamp || null].filter(Boolean).join(' · ');
}

function ReferralQueue({ title, referrals, emptyMessage, href, dataTour }) {
    const columns = [
        { key: 'tracking', label: 'Tracking ID' },
        { key: 'client', label: 'Client' },
        { key: 'service', label: 'Service' },
        { key: 'status', label: 'Status' },
        { key: 'age', label: 'Age', className: 'text-right', cellClassName: 'text-right' },
        { key: 'action', label: '', className: 'text-right', cellClassName: 'text-right' },
    ];
    const rows = referrals.map((item) => {
        const referralHref = item.href ?? safeRoute('referrals.show', item.id, `/referrals/${item.id}`);

        return {
            key: item.id,
            tracking: (
                <Link href={referralHref} className="font-bold text-primary hover:text-primary-container">
                    {item.tracking_number ?? item.case_number}
                </Link>
            ),
            client: <span className="font-semibold text-slate-900">{item.client_name}</span>,
            service: <span className="block max-w-xs truncate">{item.service}</span>,
            status: <StatusBadge status={item.status} label={humanizeStatus(item.status)} />,
            age: <AgeFlag days={item.age_days} />,
            action: (
                <Link href={referralHref} className="font-bold text-primary hover:text-primary-container">
                    Open
                </Link>
            ),
        };
    });

    return (
        <SectionCard title={title} dataTour={dataTour} action={<ViewAllLink href={href} />} bodyClassName="">
            <DashboardTable
                columns={columns}
                rows={rows}
                empty={<EmptyState message={emptyMessage} href={safeRoute('referrals.index', undefined, '/referrals')} actionLabel="Open referrals" />}
            />
        </SectionCard>
    );
}

function ReferralStatusChart({ distribution }) {
    const slices = REFERRAL_STATUSES.map((status) => {
        const item = distribution.find((entry) => entry.status === status) ?? {};

        return {
            status,
            label: humanizeStatus(status),
            count: Number(item.count ?? 0),
            color: toneHex(STATUS_TONES[status]),
        };
    }).filter((slice) => slice.count > 0);

    if (slices.length === 0) {
        return <p className="text-sm text-slate-500">Status data appears once referrals are assigned to your agency.</p>;
    }

    return (
        <div>
            <div className="h-48">
                <Pie
                    data={{
                        labels: slices.map((slice) => slice.label),
                        datasets: [{
                            data: slices.map((slice) => slice.count),
                            backgroundColor: slices.map((slice) => slice.color),
                            borderColor: '#ffffff',
                            borderWidth: 2,
                        }],
                    }}
                    options={{ responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }}
                />
            </div>
            <ul className="mt-3 space-y-2">
                {slices.map((slice) => (
                    <li key={slice.status} className="flex items-center justify-between gap-3 text-xs">
                        <span className="flex min-w-0 items-center gap-2 font-semibold text-slate-700">
                            <span className="h-2.5 w-2.5 shrink-0 rounded-full" style={{ backgroundColor: slice.color }} />
                            <span className="truncate">{slice.label}</span>
                        </span>
                        <span className="shrink-0 font-black text-slate-900">{formatCount(slice.count)}</span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

function ServiceDemandChart({ services }) {
    if (services.length === 0) {
        return <p className="text-sm text-slate-500">Service demand appears once services are added to referrals.</p>;
    }

    return (
        <div className="h-64">
            <Bar
                data={{
                    labels: services.map((service) => service.serviceName),
                    datasets: [{
                        data: services.map((service) => Number(service.totalCount ?? 0)),
                        backgroundColor: '#005288',
                        borderRadius: 4,
                        maxBarThickness: 24,
                    }],
                }}
                options={{
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } }, grid: { color: '#f1f5f9' } },
                        y: { ticks: { font: { size: 10 } }, grid: { display: false } },
                    },
                }}
            />
        </div>
    );
}

export default function AgencyDashboard({ dashboard = {} }) {
    const { auth } = usePage().props;
    const firstName = auth?.user?.name?.split(' ')[0] ?? 'there';
    const pendingReferrals = safeArray(dashboard.pendingReferrals).slice(0, 5);
    const processingReferrals = safeArray(dashboard.processingReferrals).slice(0, 5);
    const overdueReferrals = safeArray(dashboard.overdueReferrals).slice(0, 5);
    const serviceDemand = safeArray(dashboard.serviceDemand).slice(0, 6);
    const queueCells = ['pendingReferrals', 'processingReferrals', 'overdueReferrals']
        .map((key) => safeArray(dashboard.workQueue).find((item) => item.key === key))
        .filter(Boolean);

    return (
        <div className="mx-auto max-w-7xl pb-8">
            <GettingStartedChecklist />

            <PageHeader
                eyebrow="Agency focal"
                title={`Welcome back, ${firstName}`}
                subtitle="Keep referral work moving with one focused view of your agency queue."
            >
                <QuickActions
                    actions={[
                        { href: '/referrals', route: 'referrals.index', label: 'Open referrals', icon: 'send', primary: true },
                        { href: '/overdue-referrals', route: 'overdue-referrals.index', label: 'Overdue', icon: 'warning' },
                        { href: '/reports', route: 'reports.index', label: 'Reports', icon: 'bar_chart' },
                    ]}
                />
            </PageHeader>

            <SectionCard title="Referral queue" dataTour="dashboard-work-queue" bodyClassName="">
                <div data-tour="dashboard-stats" className="grid gap-px overflow-hidden rounded-b-xl bg-slate-100 sm:grid-cols-3">
                    {queueCells.map((item, index) => {
                        const count = Number(item.count ?? 0);
                        const urgent = count > 0 && item.tone === 'rose';

                        return (
                            <Link
                                key={item.key ?? index}
                                href={item.href ?? '#'}
                                className={`group flex flex-col gap-1 px-4 py-3.5 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary ${
                                    urgent ? 'bg-rose-50/40' : 'bg-white'
                                }`}
                            >
                                <span className="flex items-center gap-1.5">
                                    <span className={`h-1.5 w-1.5 rounded-full ${toneDot(item.tone)}`} />
                                    <span className="truncate text-[10px] font-bold uppercase tracking-widest text-slate-400">{item.label}</span>
                                </span>
                                <span className={`text-xl font-black ${count === 0 ? 'text-slate-300' : urgent ? 'text-rose-600' : 'text-slate-900'}`}>
                                    {formatCount(count)}
                                </span>
                                <span className={`hidden truncate text-[11px] md:block ${urgent ? 'font-medium text-rose-500' : 'text-slate-500'}`}>
                                    {item.note}
                                </span>
                            </Link>
                        );
                    })}
                </div>
            </SectionCard>

            <div className="mt-6 grid gap-6 xl:grid-cols-12">
                <div className="xl:col-span-8">
                    <ReferralQueue
                        title="Pending referrals"
                        dataTour="dashboard-agency-referrals"
                        referrals={pendingReferrals}
                        emptyMessage="No pending referrals."
                        href={safeRoute('referrals.index', { status: 'PENDING' }, '/referrals?status=PENDING')}
                    />
                </div>
                <div className="xl:col-span-4">
                    <SectionCard title="Referrals by status">
                        <ReferralStatusChart distribution={safeArray(dashboard.referralStatusDistribution)} />
                    </SectionCard>
                </div>
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-12">
                <div className="xl:col-span-8">
                    <ReferralQueue
                        title="Processing referrals"
                        referrals={processingReferrals}
                        emptyMessage="No referrals are currently being processed."
                        href={safeRoute('referrals.index', { status: 'PROCESSING' }, '/referrals?status=PROCESSING')}
                    />
                </div>
                <div className="xl:col-span-4">
                    <SectionCard title="Most used services">
                        <ServiceDemandChart services={serviceDemand} />
                    </SectionCard>
                </div>
            </div>

            <div className="mt-6">
                <ReferralQueue
                    title="Overdue referrals"
                    referrals={overdueReferrals}
                    emptyMessage="Nothing is overdue."
                    href={safeRoute('overdue-referrals.index', undefined, '/overdue-referrals')}
                />
            </div>
        </div>
    );
}
