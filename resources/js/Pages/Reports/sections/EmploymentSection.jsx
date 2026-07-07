import { useLazyProp } from '@/Hooks/useLazyProp';
import ChartSkeleton from '@/Components/Reports/ChartSkeleton';
import EmploymentAnalytics from '@/Components/Reports/EmploymentAnalytics';
import SectionAccordion from '@/Components/Reports/SectionAccordion';

export default function EmploymentSection() {
  const [employmentDistribution, employmentLoading, employmentError] = useLazyProp('employmentDistribution');
  const [employmentPositionBreakdown, positionLoading, positionError] = useLazyProp('employmentPositionBreakdown');

  const isLoading = employmentLoading || positionLoading;

  return (
    <SectionAccordion title="Employment Analytics" defaultOpen>
      {isLoading ? (
        <ChartSkeleton />
      ) : (
        <EmploymentAnalytics
          employmentDistribution={employmentDistribution}
          employmentPositionBreakdown={employmentPositionBreakdown}
        />
      )}
    </SectionAccordion>
  );
}
