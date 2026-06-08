import { useQuery } from '@tanstack/react-query';
import { keepPreviousData } from '@tanstack/react-query';
import axios from 'axios';
import DistributionChart from '@/Components/Insights/DistributionChart';
import SectionSkeleton from '../SectionSkeleton';

function useDistributionQuery(endpoint, filters) {
  return useQuery({
    queryKey: ['insights', 'distribution', endpoint, filters],
    queryFn: async () => {
      const { data } = await axios.get(`/api/insights/${endpoint}`, {
        params: filters,
      });
      return data;
    },
    staleTime: 5 * 60 * 1000,
    placeholderData: keepPreviousData,
  });
}

export default function Distributions({ from, to }) {
  const filters = { from, to };

  const statusQ = useDistributionQuery('status-distribution', filters);
  const categoryQ = useDistributionQuery('category-distribution', filters);
  const serviceQ = useDistributionQuery('service-distribution', filters);
  const geoQ = useDistributionQuery('geographic-distribution', filters);
  const clientTypeQ = useDistributionQuery('client-type-split', filters);

  const isLoading =
    statusQ.isLoading ||
    categoryQ.isLoading ||
    serviceQ.isLoading ||
    geoQ.isLoading ||
    clientTypeQ.isLoading;

  if (isLoading) return <SectionSkeleton type="chart" count={2} />;

  const errMsg = (q) => q.error?.response?.data?.message || q.error?.message || null;

  return (
    <div className="space-y-4">
      {/* Top row: 3 doughnut/pie charts */}
      <section className="grid grid-cols-1 gap-4 xl:grid-cols-3">
        <DistributionChart
          title="Case Status"
          data={statusQ.data}
          type="doughnut"
          emptyMessage="No status distribution data available."
          error={errMsg(statusQ)}
          onRetry={() => statusQ.refetch()}
        />
        <DistributionChart
          title="Case Category"
          data={categoryQ.data}
          type="doughnut"
          emptyMessage="No category distribution data available."
          error={errMsg(categoryQ)}
          onRetry={() => categoryQ.refetch()}
        />
        <DistributionChart
          title="Service Type"
          data={serviceQ.data}
          type="doughnut"
          emptyMessage="No service distribution data available."
          error={errMsg(serviceQ)}
          onRetry={() => serviceQ.refetch()}
        />
      </section>

      {/* Bottom row: 2-column grid */}
      <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <DistributionChart
          title="Geographic Distribution"
          data={geoQ.data}
          type="horizontalBar"
          height={260}
          emptyMessage="No geographic distribution data available."
          error={errMsg(geoQ)}
          onRetry={() => geoQ.refetch()}
        />
        <DistributionChart
          title="Client Type Split"
          data={clientTypeQ.data}
          type="doughnut"
          emptyMessage="No client type data available."
          error={errMsg(clientTypeQ)}
          onRetry={() => clientTypeQ.refetch()}
        />
      </section>
    </div>
  );
}
