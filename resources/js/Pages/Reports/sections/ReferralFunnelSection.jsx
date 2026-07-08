import { useState } from 'react';
import ReportLazySection from '@/Components/Reports/ReportLazySection';
import ChartSkeleton from '@/Components/Reports/ChartSkeleton';
import ReferralFunnel from '@/Components/Reports/ReferralFunnel';
import StatusToggleChips from '@/Components/Reports/StatusToggleChips';
import { cardShell, pageHeadingStyles } from '@/Components/Reports/pageHeadingStyles';

// Referral pipeline funnel with per-status show/hide chips. Status list, labels
// and colors come from the case_statuses reference rows. Rejected is hidden by
// default (de-emphasized terminal).
export default function ReferralFunnelSection({ referralStatuses = [] }) {
  const [hidden, setHidden] = useState(['REJECTED']);

  const toggle = (slug) =>
    setHidden((h) => (h.includes(slug) ? h.filter((s) => s !== slug) : [...h, slug]));

  return (
    <ReportLazySection lazyKey="referralStatusDistribution" skeleton={<ChartSkeleton />} emptyMessage="No referral pipeline data available.">
      {(data) => (
        <article className={`${cardShell} p-4`}>
          <div className="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h3 className={pageHeadingStyles.sectionTitle}>Referral Pipeline</h3>
            <StatusToggleChips statuses={referralStatuses} hidden={hidden} onToggle={toggle} />
          </div>
          <ReferralFunnel distribution={data} hidden={hidden} />
        </article>
      )}
    </ReportLazySection>
  );
}
