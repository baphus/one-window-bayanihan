import ReportLazySection from '@/Components/Reports/ReportLazySection';
import ChartSkeleton from '@/Components/Reports/ChartSkeleton';
import TrendChart from '@/Components/Reports/TrendChart';

export default function ReferralTrendsSection() {
  return (
    <ReportLazySection lazyKey="referralTrends" skeleton={<ChartSkeleton />} emptyMessage="No referral activity in this period.">
      {(data) => <TrendChart title="Referrals Over Time" data={data} />}
    </ReportLazySection>
  );
}
