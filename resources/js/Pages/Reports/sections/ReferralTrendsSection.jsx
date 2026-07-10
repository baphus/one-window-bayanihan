import ReportLazySection from '@/Components/Reports/ReportLazySection';
import ChartSkeleton from '@/Components/Reports/ChartSkeleton';
import TrendChart from '@/Components/Reports/TrendChart';

export default function ReferralTrendsSection({ role }) {
  if (role && role !== 'AGENCY') return null;

  return (
    <ReportLazySection lazyKey="referralTrends" skeleton={<ChartSkeleton />} emptyMessage="No referral trend data available.">
      {(data) => <TrendChart title="Referral Trends" data={data} />}
    </ReportLazySection>
  );
}
