import MetricCard from '@/Components/Reports/MetricCard';
import TrendIndicator from '@/Components/Reports/TrendIndicator';
import { GitFork, Target, CalendarDays, Clock, CheckCircle2, FolderOpen, FolderKanban, Building2 } from 'lucide-react';

export default function KpiCardsSection({ kpis, overview, avgReferralCompletion }) {
  if (!kpis && !overview) return null;

  // Admin overview KPIs
  if (overview) {
    return (
      <section className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <MetricCard label="Total Cases" value={`${overview.totalCases || 0}`} accent="border-l-[#1e3a8a]"
          icon={<FolderOpen className="h-3.5 w-3.5 text-[#1e3a8a]" />} />
        <MetricCard label="Open Cases" value={`${overview.openCases || 0}`} accent="border-l-[#9a5b1a]" valueTone="text-[#9a5b1a]"
          icon={<FolderKanban className="h-3.5 w-3.5 text-[#9a5b1a]" />} />
        <MetricCard label="Total Referrals" value={`${overview.totalReferrals || 0}`} accent="border-l-[#0b7a75]"
          icon={<GitFork className="h-3.5 w-3.5 text-[#0b7a75]" />} />
        <MetricCard label="Active Agencies" value={`${overview.activeAgencies || 0}`} accent="border-l-[#0b5a8c]"
          icon={<Building2 className="h-3.5 w-3.5 text-[#0b5a8c]" />} />
      </section>
    );
  }

  // Agency has 5 cards, Case Manager has 4 — conditionally render the extra cards
  const hasAgencyExtras = kpis.completedReferrals !== undefined;

  return (
    <section className={`grid grid-cols-1 gap-3 sm:grid-cols-2 ${hasAgencyExtras ? 'xl:grid-cols-5' : 'xl:grid-cols-4'}`}>
      <MetricCard label="Total Referrals" value={`${kpis.totalReferrals}`} accent="border-l-[#0b5a8c]"
        icon={<GitFork className="h-3.5 w-3.5 text-[#0b5a8c]" />}
        trailing={<TrendIndicator change={kpis.kpiChanges?.totalReferrals} />} />
      {hasAgencyExtras && (
        <MetricCard label="Completed" value={`${kpis.completedReferrals}`} accent="border-l-[#0b7a75]"
          icon={<CheckCircle2 className="h-3.5 w-3.5 text-[#0b7a75]" />}
          trailing={<TrendIndicator change={kpis.kpiChanges?.completedReferrals} />} />
      )}
      <MetricCard label="Pending" value={`${kpis.pendingReferrals}`} accent="border-l-[#9a5b1a]" valueTone="text-[#9a5b1a]"
        icon={<Clock className="h-3.5 w-3.5 text-[#9a5b1a]" />}
        trailing={<TrendIndicator change={kpis.kpiChanges?.pendingReferrals} />} />
      {avgReferralCompletion !== undefined ? (
        <MetricCard label="Avg Completion" value={`${avgReferralCompletion.toFixed(1)}d`} accent="border-l-[#0b5a8c]" description="From referral sent to completion"
          icon={<CalendarDays className="h-3.5 w-3.5 text-[#0b5a8c]" />}
          trailing={<TrendIndicator change={kpis.kpiChanges?.avgCompletionDays} inverse />} />
      ) : (
        <MetricCard label="Avg Completion Days" value={`${kpis.avgCompletionDays}d`} accent="border-l-[#1e3a8a]" description="From referral creation to completion"
          icon={<CalendarDays className="h-3.5 w-3.5 text-[#1e3a8a]" />}
          trailing={<TrendIndicator change={kpis.kpiChanges?.avgCompletionDays} inverse />} />
      )}
      <MetricCard label="Completion Rate" value={`${kpis.completionRate}%`} accent="border-l-[#0b7a75]"
        icon={<Target className="h-3.5 w-3.5 text-[#0b7a75]" />}
        trailing={<TrendIndicator change={kpis.kpiChanges?.completionRate} />} />
    </section>
  );
}
