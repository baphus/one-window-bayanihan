import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';

/**
 * All 8 tab IDs for the Insights page.
 */
const ALL_TABS = [
  'executive',
  'trends',
  'distribution',
  'operational',
  'scorecards',
  'satisfaction',
  'predictive',
  'alerts',
];

/**
 * Per-role tab visibility. Roles not listed (or mapped to null) see all tabs.
 * @type {Record<string, string[]|null>}
 */
const ALLOWED_TABS_BY_ROLE = {
  CASE_MANAGER: [
    'executive',
    'trends',
    'distribution',
    'operational',
    'scorecards',
    'satisfaction',
    'alerts',
  ],
  AGENCY: [
    'executive',
    'distribution',
    'operational',
    'scorecards',
    'satisfaction',
    'predictive',
    'alerts',
  ],
};

/**
 * Per-role view-level restrictions (sections gated within tabs).
 * A view that is NOT listed for a role defaults to allowed (true).
 * @type {Record<string, string[]>}
 */
const RESTRICTED_VIEWS = {
  ADMIN: [],
  CASE_MANAGER: [
    'geographic',
    'overloaded_agencies',
    'bottleneck_detection',
    'agency_scorecard',
    'satisfaction_other',
    'predictive',
  ],
  AGENCY: [
    'case_trend',
    'status_distribution',
    'category_distribution',
    'client_type_split',
    'aging_cases',
    'overloaded_agencies',
    'bottleneck_detection',
    'cm_scorecard',
  ],
};

/**
 * Role-based data filter scope.
 */
const FILTER_SCOPE_BY_ROLE = {
  ADMIN: null,
  CASE_MANAGER: null,
  AGENCY: 'agency',
};

/**
 * Access-control hook for the Insights page.
 *
 * Reads the authenticated user's role from Inertia shared props and returns
 * access-control helpers for tab and section visibility gating.
 *
 * @returns {{ can: (view: string) => boolean, allowedTabs: string[], filterScope: string|null }}
 */
export default function useInsightsAccess() {
  const user = usePage().props.auth?.user;
  const role = user?.role;

  return useMemo(() => {
    const effectiveRole = role || 'ADMIN';
    const restricted = RESTRICTED_VIEWS[effectiveRole] ?? [];

    return {
      /**
       * Check whether the current user is allowed to view a specific section.
       *
       * @param {string} view - The view/section identifier (e.g., 'geographic', 'predictive').
       * @returns {boolean}
       */
      can: (view) => !restricted.includes(view),

      /**
       * The list of tab IDs the current user is allowed to see.
       * Used to filter the tab navigation bar.
       */
      allowedTabs: ALLOWED_TABS_BY_ROLE[effectiveRole] ?? ALL_TABS,

      /**
       * Data filter scope for API queries.
       * - `null` or `undefined`: no scope filter
       * - `'agency'`: results should be scoped to the user's agency
       */
      filterScope: FILTER_SCOPE_BY_ROLE[effectiveRole] ?? null,
    };
  }, [role]);
}
