import { describe, expect, it } from 'vitest';
import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';

/**
 * These report sections and components were superseded by the
 * Index -> CasesTab / ReferralsTab tree. They were kept around as dead files
 * long enough that their `vi.mock` entries in suppressCount.test.jsx outlived
 * them, so a file list is asserted instead of trusting a one-off cleanup.
 */
const DELETED = [
  'resources/js/Components/Reports/OverviewBanner.jsx',
  'resources/js/Components/Reports/ClientSnapshotCard.jsx',
  'resources/js/Components/Reports/AgencyWorkloadChart.jsx',
  'resources/js/Components/Reports/AvgCompletionCard.jsx',
  'resources/js/Components/Reports/DateScopeSelect.jsx',
  'resources/js/Components/Reports/ReportPresetSelector.jsx',
  'resources/js/Components/Reports/ReportTabBar.jsx',
  'resources/js/Components/Reports/ReferralFunnel.jsx',
  'resources/js/Components/Reports/StatusToggleChips.jsx',
  'resources/js/Pages/Reports/sections/AgencyScorecardSection.jsx',
  'resources/js/Pages/Reports/sections/CategorySection.jsx',
  'resources/js/Pages/Reports/sections/CycleTimeSection.jsx',
  'resources/js/Pages/Reports/sections/EmploymentSection.jsx',
  'resources/js/Pages/Reports/sections/GeographicSection.jsx',
  'resources/js/Pages/Reports/sections/LazyChartArticle.jsx',
  'resources/js/Pages/Reports/sections/LazyDemographics.jsx',
  'resources/js/Pages/Reports/sections/ReferralFunnelSection.jsx',
  'resources/js/Pages/Reports/sections/StatusDistributionSection.jsx',
  'resources/js/Components/Helpdesk/ArticleCard.jsx',
  'resources/js/Components/landing/AboutSection.jsx',
  'resources/js/Components/ExistingClientModal.jsx',
  'resources/js/Components/ResponsiveNavLink.jsx',
  'resources/js/Pages/Admin/OverdueReferrals/OverdueCard.jsx',
  'resources/js/Hooks/useAutoSave.js',
  'resources/js/Hooks/__tests__/useAutoSaveDraft.test.jsx',
  'resources/js/types/index.ts',
];

// Anchor on the project root (vitest runs from it) rather than __dirname, which
// is transformed by the test runner and does not reliably point at this file.
const repoRoot = process.cwd();
const jsRoot = resolve(repoRoot, 'resources/js');

describe('dead report and frontend artifacts', () => {
  it.each(DELETED)('%s stays deleted', (relativePath) => {
    expect(existsSync(resolve(repoRoot, relativePath))).toBe(false);
  });

  it('suppressCount.test.jsx does not mock any deleted report module', () => {
    const test = readFileSync(resolve(repoRoot, 'resources/js/Pages/Reports/__tests__/suppressCount.test.jsx'), 'utf8');
    const mocked = [...test.matchAll(/vi\.mock\('(@\/[^']+)'/g)].map((match) => match[1]);

    const staleReportMocks = mocked.filter((id) => id.startsWith('@/Components/Reports/') || id.startsWith('@/Pages/Reports/sections/'));
    const stale = staleReportMocks.filter((id) => !existsSync(resolve(jsRoot, id.replace('@/', '')))
      && !existsSync(resolve(jsRoot, `${id.replace('@/', '')}.jsx`)));

    expect(stale).toEqual([]);
  });
});
