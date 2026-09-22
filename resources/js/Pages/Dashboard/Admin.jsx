import { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import GettingStartedChecklist from '@/Components/GettingStartedChecklist';
import StatusBadge from '@/Components/ui/StatusBadge';
import { formatDisplayDate, formatDisplayDateTime } from '@/lib/utils';
import {
    ActivityFeed,
    AgencyScorecard,
    BarList,
    CaseActivityRow,
    CollapsibleSectionCard,
    EmptyState,
    EntityList,
    FilterChip,
    MaterialSymbol,
    PageHeader,
    PriorityReferralRow,
    QuickActions,
    SectionCard,
    StatRow,
    StatusDonut,
    TriageStrip,
    ViewAllLink,
    formatCount,
    safeArray,
} from '@/Components/Dashboard/primitives';

const QUEUE_ROUTES = {
    openCases: '/cases?status=OPEN',
    pendingReferrals: '/referrals?status=PENDING',
    processingReferrals: '/referrals?status=PROCESSING',
    forComplianceReferrals: '/referrals?status=FOR_COMPLIANCE',
    overdueReferrals: '/overdue-referrals',
};

const ATTENTION_STATUSES = ['REJECTED', 'FOR_COMPLIANCE'];

const ADMIN_TOOLS = [
    { label: 'Users', description: 'Roles, verification, access', href: '/admin/users', icon: 'group' },
    { label: 'Agencies', description: 'Partner profiles and activation', href: '/admin/agencies', icon: 'business' },
    { label: 'Services', description: 'Catalog and requirements', href: '/admin/services', icon: 'inventory_2' },
    { label: 'Audit logs', description: 'Review system changes', href: '/audit-logs', icon: 'history' },
    { label: 'Sessions', description: 'Monitor signed-in users', href: '/admin/system/active-sessions', icon: 'devices' },
];

function buildQueueItems(dashboard, stats) {
    const supplied = safeArray(dashboard.operationalQueues);

    if (supplied.length > 0) {
        return supplied.map((item) => {
            const count = Number(item.count ?? 0);
            const worstAge = item.worstAgeDays != null ? Number(item.worstAgeDays) : null;

            // Dynamic note: "Oldest Xd · Y total" when severity data present
            let note;
            if (worstAge != null) {
                note = `Oldest ${worstAge}d`;
                if (count > 0) note += ` · ${count} total`;
            } else {
                note = item.note ?? '';
            }

            // Escalate tone based on severity thresholds
            let tone = item.tone ?? 'slate';
            if (item.key === 'overdueReferrals') {
                if (count > 0) tone = 'rose';
            } else if (worstAge != null && worstAge >= 7) {
                tone = 'rose';
            } else if (worstAge != null && worstAge >= 5 && tone === 'blue') {
                tone = 'amber';
            }

            return {
                ...item,
                note,
                tone,
                worstAgeDays: worstAge,
                href: item.href ?? QUEUE_ROUTES[item.key] ?? '/referrals',
            };
        });
    }

    return [
        { key: 'openCases', label: 'Open cases', count: stats.openCases ?? stats.totalOpenCases ?? 0, note: 'Cases still being handled.', tone: 'blue', href: '/cases?status=OPEN' },
        { key: 'pendingReferrals', label: 'Pending referrals', count: stats.pendingReferrals ?? 0, note: 'Waiting for agency action.', tone: 'amber', href: '/referrals?status=PENDING' },
        { key: 'processingReferrals', label: 'Processing', count: stats.processingReferrals ?? 0, note: 'Currently being worked by agencies.', tone: 'cyan', href: '/referrals?status=PROCESSING' },
        { key: 'forComplianceReferrals', label: 'For compliance', count: stats.forComplianceReferrals ?? 0, note: 'Need missing requirements.', tone: 'orange', href: '/referrals?status=FOR_COMPLIANCE' },
        { key: 'overdueReferrals', label: 'Overdue', count: stats.overdueReferrals ?? 0, note: 'Past the expected response window.', tone: (stats.overdueReferrals ?? 0) > 0 ? 'rose' : 'slate', href: '/overdue-referrals' },
    ];
}

function needsAttention(item) {
    return item.is_overdue || ATTENTION_STATUSES.includes(item.worst_referral_status);
}

function agingBandTone(bandLabel) {
    const label = String(bandLabel ?? '');
    if (label.includes('11')) return 'rose';
    if (label.includes('6')) return 'orange';
    if (label.includes('3')) return 'amber';
    return 'blue';
}

const MODULE_GROUPS = {
    Cases: ['case', 'case_category', 'case_issue', 'case_status', 'case_document'],
    Referrals: ['referral', 'referral_comment', 'referral_attachment', 'referral_client_request', 'referral_client_request_item', 'referral_client_message', 'referral_client_access_link', 'referral_service_requirement'],
    Users: ['user', 'auth', 'session', 'mfa', 'security'],
    Agencies: ['agency', 'service', 'service_requirement'],
};

function categorizeModule(mod) {
    const lower = String(mod ?? '').toLowerCase();
    for (const [group, modules] of Object.entries(MODULE_GROUPS)) {
        if (modules.includes(lower)) return group;
    }
    return 'Other';
}

function moduleGroupTone(group) {
    switch (group) {
        case 'Cases': return 'blue';
        case 'Referrals': return 'amber';
        case 'Users': return 'emerald';
        case 'Agencies': return 'cyan';
        default: return 'slate';
    }
}

export default function AdminDashboard({ dashboard = {} }) {
    const { auth } = usePage().props;
    const firstName = auth?.user?.name?.split(' ')[0] ?? 'Administrator';
    const stats = dashboard.stats ?? dashboard;

    const queueItems = buildQueueItems(dashboard, stats);
    const recentCases = safeArray(dashboard.recentCases).slice(0, 6);
    const recentLogs = safeArray(dashboard.recentLogs).slice(0, 6).map((log) => ({
        ...log,
        actionType: log.action ?? log.actionType,
        title: log.message ?? 'System activity',
        desc: log.detail ?? '',
        time: log.timestamp ? formatDisplayDateTime(log.timestamp) : log.time,
    }));
    const topAgencies = safeArray(dashboard.topAgencies).slice(0, 6);
    const agencyScorecard = safeArray(dashboard.agencyScorecard).slice(0, 5);
    const scorecardAgencies = agencyScorecard.length > 0
        ? agencyScorecard
        : topAgencies.map((a) => ({
              id: a.id,
              name: a.name ?? a.agencyName,
              totalReferrals: a.totalReferrals ?? 0,
              activeReferrals: a.activeReferrals ?? a.count ?? 0,
              overdueReferrals: 0,
              overdueRate: 0,
              avgDaysToComplete: null,
          }));
    const usersByRole = safeArray(dashboard.usersByRole);
    const categories = safeArray(dashboard.casesByCategory).slice(0, 6);
    const priorityReferrals = safeArray(dashboard.priorityReferrals).slice(0, 5).map((item) => ({
        ...item,
        case_number: item.case_number ?? item.caseNo ?? null,
        client_name: item.client_name ?? item.clientName ?? 'Unnamed client',
        agency_name: item.agency_name ?? item.agencyName ?? null,
        age_days: item.age_days ?? item.ageDays ?? null,
    }));
    const referralAgingBands = safeArray(dashboard.referralAgingBands);
    const caseTrends = safeArray(dashboard.caseTrends);

    // ── Client-side feed filter ──
    const [feedFilter, setFeedFilter] = useState('all');
    const attentionCount = recentCases.filter(needsAttention).length;
    const overdueCount = recentCases.filter((c) => c.is_overdue).length;

    // ── Audit log module filter ──
    const [auditFilter, setAuditFilter] = useState('all');
    const auditModuleCounts = recentLogs.reduce((acc, log) => {
        const group = categorizeModule(log.module);
        acc[group] = (acc[group] ?? 0) + 1;
        return acc;
    }, {});
    const auditModuleGroups = Object.keys(auditModuleCounts).sort();
    const filteredLogs = auditFilter === 'all'
        ? recentLogs
        : recentLogs.filter((log) => categorizeModule(log.module) === auditFilter);

    const filteredCases = recentCases.filter((item) => {
        if (feedFilter === 'overdue') return item.is_overdue;
        if (feedFilter === 'attention') return needsAttention(item);
        return true;
    });

    // ── KPI trend derivation from caseTrends ──
    const weeklyCaseDelta = (() => {
        if (caseTrends.length < 7) return null;
        const recent7 = caseTrends.slice(-7).reduce((sum, d) => sum + Number(d.count ?? d.total ?? 0), 0);
        const prev7 = caseTrends.slice(-14, -7).reduce((sum, d) => sum + Number(d.count ?? d.total ?? 0), 0);
        const delta = recent7 - prev7;
        return delta !== 0 ? delta : null;
    })();

    const overdueAgingHint = (() => {
        if (referralAgingBands.length === 0) return null;
        const severe = referralAgingBands.reduce((sum, b) => {
            const label = String(b.band ?? b.label ?? '');
            return label.includes('11') ? sum + Number(b.count ?? 0) : sum;
        }, 0);
        return severe > 0 ? severe : null;
    })();

    return (
        <div className="mx-auto max-w-7xl pb-8">
            <GettingStartedChecklist />

            <PageHeader
                eyebrow="Admin overview"
                title={`Welcome back, ${firstName}`}
                subtitle="Monitor queues, track referrals, and manage the system from one place."
            >
                <QuickActions
                    actions={[
                        { href: '/cases', label: 'Cases', icon: 'folder', count: stats.totalCases, primary: true },
                        { href: '/referrals', label: 'Referrals', icon: 'send', count: stats.totalReferrals },
                        { href: '/overdue-referrals', label: 'Overdue', icon: 'warning', count: stats.overdueReferrals },
                    ]}
                />
            </PageHeader>

            {/* ── KPI strip with trend-aware descriptions ── */}
            <StatRow
                dataTour="dashboard-stats"
                stats={[
                    {
                        title: 'Total cases',
                        value: stats.totalCases ?? 0,
                        icon: 'folder',
                        description: weeklyCaseDelta != null
                            ? `${weeklyCaseDelta > 0 ? '+' : ''}${weeklyCaseDelta} cases this week`
                            : 'All non-draft case files in the system.',
                        trend: weeklyCaseDelta != null ? `${weeklyCaseDelta > 0 ? '+' : ''}${weeklyCaseDelta} this week` : undefined,
                    },
                    {
                        title: 'Total referrals',
                        value: stats.totalReferrals ?? 0,
                        icon: 'send',
                        iconBg: 'bg-amber-50',
                        iconColor: 'text-amber-700',
                        description: 'Referrals sent to partner agencies.',
                    },
                    {
                        title: 'Active agencies',
                        value: stats.activeAgencies ?? stats.totalAgencies ?? 0,
                        icon: 'account_balance',
                        iconBg: 'bg-emerald-50',
                        iconColor: 'text-emerald-700',
                        description: `${formatCount(stats.inactiveAgencies ?? 0)} inactive agency records.`,
                    },
                    {
                        title: 'Overdue referrals',
                        value: stats.overdueReferrals ?? 0,
                        icon: 'warning',
                        iconBg: 'bg-rose-50',
                        iconColor: 'text-rose-700',
                        description: overdueAgingHint != null
                            ? `${overdueAgingHint} active referrals older than 10 days`
                            : 'Active referrals older than five days.',
                        trend: (stats.overdueReferrals ?? 0) > 0 ? 'Needs action' : undefined,
                    },
                ]}
            />

            <TriageStrip items={queueItems} dataTour="dashboard-work-queues" />

            {/* ── Referral aging bands ── */}
            {referralAgingBands.length > 0 ? (
                <SectionCard
                    title="Referral aging"
                    dataTour="dashboard-referral-aging"
                    bodyClassName="px-5 pt-4 pb-5"
                >
                    <BarList
                        items={referralAgingBands.map((band) => ({
                            key: band.band ?? band.label ?? band.bandLabel,
                            label: `${band.band ?? band.label ?? band.bandLabel} days`,
                            count: band.count ?? band.total ?? 0,
                            tone: agingBandTone(band.band ?? band.label ?? band.bandLabel),
                        }))}
                    />
                    <p className="mt-3 text-[11px] text-slate-400">Active referrals by age.</p>
                </SectionCard>
            ) : null}

            <div className="grid gap-6 xl:grid-cols-12">
                <div className="space-y-6 xl:col-span-8">
                    {/* ── Attention-aware recent case activity ── */}
                    <SectionCard
                        title="Recent case activity"
                        dataTour="dashboard-recent-cases"
                        action={<ViewAllLink href="/cases">View all cases</ViewAllLink>}
                        bodyClassName="p-0"
                    >
                        <div className="flex items-center gap-2 border-b border-slate-100 px-5 pb-3 pt-4">
                            <FilterChip
                                label="All"
                                count={recentCases.length}
                                active={feedFilter === 'all'}
                                onClick={() => setFeedFilter('all')}
                            />
                            <FilterChip
                                label="Needs attention"
                                count={attentionCount}
                                active={feedFilter === 'attention'}
                                onClick={() => setFeedFilter('attention')}
                            />
                            <FilterChip
                                label="Overdue"
                                count={overdueCount}
                                active={feedFilter === 'overdue'}
                                onClick={() => setFeedFilter('overdue')}
                            />
                        </div>
                        <EntityList empty={<EmptyState message="No recent cases yet." href="/cases" actionLabel="Open cases" />}>
                            {filteredCases.map((item) => (
                                <CaseActivityRow
                                    key={item.id}
                                    href={`/cases/${item.id}`}
                                    caseNumber={item.case_number ?? item.caseNo}
                                    clientName={item.client_name ?? item.clientName}
                                    category={item.category}
                                    caseOwner={item.case_owner ?? item.caseOwner}
                                    referralCount={item.referral_count ?? 0}
                                    worstReferralStatus={item.worst_referral_status}
                                    isOverdue={Boolean(item.is_overdue)}
                                    maxReferralAgeDays={item.max_referral_age_days}
                                    updatedAt={item.updated_at ? formatDisplayDate(item.updated_at) : null}
                                    status={item.status}
                                />
                            ))}
                        </EntityList>
                    </SectionCard>

                    {/* ── Priority referrals ── */}
                    <SectionCard
                        title="Priority referrals"
                        dataTour="dashboard-priority-referrals"
                        action={<ViewAllLink href="/referrals">View referrals</ViewAllLink>}
                    >
                        <EntityList
                            empty={
                                <EmptyState
                                    message="No priority referrals right now."
                                    href="/referrals"
                                    actionLabel="View referrals"
                                />
                            }
                        >
                            {priorityReferrals.map((item, idx) => (
                                <PriorityReferralRow
                                    key={item.id ?? item.case_number ?? idx}
                                    href={item.case_id ? `/cases/${item.case_id}` : '/referrals'}
                                    caseNumber={item.case_number}
                                    clientName={item.client_name}
                                    agencyName={item.agency_name}
                                    status={item.status}
                                    ageDays={item.age_days}
                                />
                            ))}
                        </EntityList>
                    </SectionCard>

                    {/* ── Recent administrative changes ── */}
                    <SectionCard
                        title="Recent administrative changes"
                        dataTour="dashboard-recent-activity"
                        action={<ViewAllLink href="/audit-logs">View audit logs</ViewAllLink>}
                        bodyClassName="p-0"
                    >
                        <div className="flex flex-wrap items-center gap-2 border-b border-slate-100 px-5 pb-3 pt-4">
                            <FilterChip
                                label="All"
                                count={recentLogs.length}
                                active={auditFilter === 'all'}
                                onClick={() => setAuditFilter('all')}
                            />
                            {auditModuleGroups.map((group) => (
                                <FilterChip
                                    key={group}
                                    label={group}
                                    count={auditModuleCounts[group] ?? 0}
                                    active={auditFilter === group}
                                    onClick={() => setAuditFilter(group)}
                                />
                            ))}
                        </div>
                        <ActivityFeed
                            items={filteredLogs}
                            empty={<EmptyState message="No matching activity." />}
                        />
                    </SectionCard>
                </div>

                <aside className="space-y-6 xl:col-span-4">
                    <SectionCard title="Admin tools" dataTour="dashboard-admin-tools" bodyClassName="p-3">
                        <div className="grid gap-1">
                            {ADMIN_TOOLS.map((tool) => (
                                <Link
                                    key={tool.href}
                                    href={tool.href}
                                    className="flex items-center gap-3 rounded-lg px-3 py-2.5 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                >
                                    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                        <MaterialSymbol name={tool.icon} className="text-[18px]" />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block text-sm font-bold text-slate-900">{tool.label}</span>
                                        <span className="block truncate text-xs text-slate-500">{tool.description}</span>
                                    </span>
                                    <MaterialSymbol name="chevron_right" className="text-[16px] text-slate-300" />
                                </Link>
                            ))}
                        </div>
                    </SectionCard>

                    <SectionCard
                        title="Agency response"
                        dataTour="dashboard-agency-scorecard"
                        action={<ViewAllLink href="/admin/agencies">View agencies</ViewAllLink>}
                    >
                        <AgencyScorecard
                            agencies={scorecardAgencies}
                            empty={
                                <EmptyState
                                    message="No agency activity yet."
                                    href="/admin/agencies"
                                    actionLabel="View agencies"
                                />
                            }
                        />
                        {agencyScorecard.length > 0 ? (
                            <p className="px-5 pb-3 pt-1 text-[10px] font-medium text-slate-400">
                                Sorted by overdue rate.
                            </p>
                        ) : null}
                    </SectionCard>

                    <SectionCard title="Referral status">
                        {safeArray(dashboard.referralStatusDistribution).length > 0 ? (
                            <StatusDonut items={dashboard.referralStatusDistribution} />
                        ) : (
                            <p className="text-sm text-slate-500">Status distribution appears once referrals exist.</p>
                        )}
                    </SectionCard>

                    <CollapsibleSectionCard
                        title="System snapshot"
                        dataTour="dashboard-system-snapshot"
                        defaultOpen={false}
                    >
                        <div className="space-y-5">
                            {/* Users by role */}
                            <div>
                                <h3 className="mb-2 text-[10px] font-bold uppercase tracking-widest text-slate-400">Users by role</h3>
                                {usersByRole.length > 0 ? (
                                    <div className="space-y-1.5">
                                        {usersByRole.map((role) => (
                                            <div key={role.role ?? role.label} className="flex items-center justify-between gap-3 text-xs">
                                                <span className="font-semibold text-slate-700">{role.label ?? role.role}</span>
                                                <span className="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold text-primary">{formatCount(role.count)}</span>
                                            </div>
                                        ))}
                                        <p className="pt-1 text-[10px] text-slate-400">
                                            {formatCount(stats.verifiedUsers)} of {formatCount(stats.totalUsers)} verified.
                                        </p>
                                    </div>
                                ) : (
                                    <p className="text-xs text-slate-500">No role data yet.</p>
                                )}
                            </div>
                            {/* Case mix */}
                            <div>
                                <h3 className="mb-2 text-[10px] font-bold uppercase tracking-widest text-slate-400">Case mix</h3>
                                {categories.length > 0 ? (
                                    <BarList
                                        items={categories.map((category) => ({
                                            key: category.name,
                                            label: category.name,
                                            count: category.count,
                                            hex: category.color,
                                        }))}
                                    />
                                ) : (
                                    <p className="text-xs text-slate-500">Category mix appears once cases are filed.</p>
                                )}
                            </div>
                        </div>
                    </CollapsibleSectionCard>
                </aside>
            </div>
        </div>
    );
}
