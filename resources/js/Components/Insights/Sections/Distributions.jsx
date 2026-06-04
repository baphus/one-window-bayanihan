import DistributionChart from '@/Components/Insights/DistributionChart';

export default function Distributions({
  statusDistribution,
  categoryDistribution,
  serviceDistribution,
  geographicDistribution,
  clientTypeSplit,
}) {
  return (
    <div className="space-y-4">
      {/* Top row: 3 doughnut/pie charts */}
      <section className="grid grid-cols-1 gap-4 xl:grid-cols-3">
        <DistributionChart
          title="Case Status"
          data={statusDistribution}
          type="doughnut"
          emptyMessage="No status distribution data available."
        />
        <DistributionChart
          title="Case Category"
          data={categoryDistribution}
          type="doughnut"
          emptyMessage="No category distribution data available."
        />
        <DistributionChart
          title="Service Type"
          data={serviceDistribution}
          type="doughnut"
          emptyMessage="No service distribution data available."
        />
      </section>

      {/* Bottom row: 2-column grid */}
      <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <DistributionChart
          title="Geographic Distribution"
          data={geographicDistribution}
          type="horizontalBar"
          height={260}
          emptyMessage="No geographic distribution data available."
        />
        <DistributionChart
          title="Client Type Split"
          data={clientTypeSplit}
          type="doughnut"
          emptyMessage="No client type data available."
        />
      </section>
    </div>
  );
}
