import ReportLazySection from '@/Components/Reports/ReportLazySection';
import ChartSkeleton from '@/Components/Reports/ChartSkeleton';
import TrendChart from '@/Components/Reports/TrendChart';

export default function LazyTrendChart({ lazyKey, title, ...props }) {
  return (
    <ReportLazySection lazyKey={lazyKey} skeleton={<ChartSkeleton />}>
      {(data) => <TrendChart title={title} data={data} {...props} />}
    </ReportLazySection>
  );
}
