import { useMemo } from 'react';
import { COLORS } from '@/Components/Reports/pageHeadingStyles';

function toPieFormat(distribution) {
  if (!distribution?.labels) return [];
  const total = distribution.data.reduce((s, v) => s + v, 0) || 1;
  const colors = distribution.colors || COLORS.chartPalette;
  return distribution.labels.map((label, i) => ({
    label,
    count: distribution.data[i] || 0,
    hex: colors[i % colors.length],
    color: '',
    percent: Math.round(((distribution.data[i] || 0) / total) * 100),
  }));
}

function toBarFormat(data, defaultColor, label = 'Count') {
  if (!data?.labels) return null;
  return {
    labels: data.labels,
    datasets: [
      {
        label,
        data: data.data,
        backgroundColor: data.colors?.[0] || defaultColor || COLORS.primary,
        borderRadius: 3,
        barThickness: 18,
      },
    ],
  };
}

/**
 * Transforms raw admin report data into chart.js-compatible formats.
 * Consolidates 10 useMemo blocks into one call.
 */
export default function useAdminChartData({
  referralStatusDistribution,
  clientTypeDistribution,
  referralTypeDistribution,
  cycleTimeDistribution,
  referralAging,
  geographicDistribution,
  agencyWorkload,
  caseTrends,
  categoryDistribution,
  caseIssueDistribution,
}) {
  return useMemo(
    () => ({
      referralStatusPie: toPieFormat(referralStatusDistribution),
      clientTypePie: toPieFormat(clientTypeDistribution),
      referralTypePie: toPieFormat(referralTypeDistribution),
      adminCycleTimeData: toBarFormat(cycleTimeDistribution, [COLORS.success, '#84cc16', COLORS.warning, COLORS.danger]),
      adminAgingData: toBarFormat(referralAging, [COLORS.success, '#84cc16', COLORS.warning, COLORS.danger]),
      adminGeoData: toBarFormat(geographicDistribution, COLORS.primary),
      workloadChartData: toBarFormat(agencyWorkload, '#1e3a8a'),
      caseTrendsChartData: !caseTrends?.labels
        ? null
        : {
            labels: caseTrends.labels,
            datasets: [
              {
                label: 'Cases',
                data: caseTrends.data,
                borderColor: COLORS.accent,
                backgroundColor: `${COLORS.accent}1A`,
                fill: true,
                tension: 0.3,
              },
            ],
          },
      adminCategoryData: toCategoryBarFormat(categoryDistribution),
      adminCaseIssueData: toCategoryBarFormat(caseIssueDistribution),
    }),
    [
      referralStatusDistribution,
      clientTypeDistribution,
      referralTypeDistribution,
      cycleTimeDistribution,
      referralAging,
      geographicDistribution,
      agencyWorkload,
      caseTrends,
      categoryDistribution,
      caseIssueDistribution,
    ],
  );
}

function toCategoryBarFormat(items) {
  if (!items?.length) return null;
  return {
    labels: items.map((c) => c.name),
    datasets: [
      {
        label: 'Cases',
        data: items.map((c) => c.count),
        backgroundColor: items.map((c) => c.color),
        borderRadius: 3,
        barThickness: 18,
      },
    ],
  };
}
