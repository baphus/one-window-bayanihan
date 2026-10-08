// Report card + heading tokens.
// Status hexes mirror ui/StatusBadge's hues; primary mirrors the `primary`
// token in tailwind.config.js. Chart identity colors reuse the same hues so
// status and categorical palettes never drift apart.

const PRIMARY = '#005288';

const STATUS_HEX = {
  OPEN: '#1d4ed8',
  CLOSED: '#64748b',
  DRAFT: '#b45309',
  ARCHIVED: '#6b7280',
  PENDING: '#b45309',
  PROCESSING: '#1d4ed8',
  FOR_COMPLIANCE: '#c2410c',
  COMPLETED: '#047857',
  REJECTED: '#be123c',
};

const NEUTRAL = '#64748b';

export const COLORS = {
  primary: PRIMARY,
  border: '#cbd5e1',
  success: STATUS_HEX.COMPLETED,
  warning: STATUS_HEX.PENDING,
  danger: STATUS_HEX.REJECTED,
  chartPalette: [PRIMARY, '#1d4ed8', '#047857', '#b45309', '#c2410c', '#be123c'],
  statusColors: STATUS_HEX,
  statusTone: {
    good: STATUS_HEX.COMPLETED,
    warning: STATUS_HEX.PENDING,
    serious: STATUS_HEX.FOR_COMPLIANCE,
    critical: STATUS_HEX.REJECTED,
    neutral: NEUTRAL,
  },
};

// Resolve a color for a status slug, falling back to the DB color then neutral.
export function statusColor(slug, fallbackFromDb) {
  return STATUS_HEX[slug] || fallbackFromDb || NEUTRAL;
}

// Shared, dark-mode-aware class tokens.
export const pageHeadingStyles = {
  pageTitle:
    'text-2xl md:text-3xl font-extrabold font-headline tracking-tight text-slate-900 dark:text-slate-100',
  pageSubtitle: 'text-sm text-slate-400 font-body mt-0.5 dark:text-slate-400',
  sectionTitle:
    'text-[11px] font-extrabold uppercase tracking-[0.14em] text-primary dark:text-primary-fixed-dim',
  metricLabel:
    'text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400',
};

// Reusable card shell (light + dark). Compose with extra classes as needed.
export const cardShell =
  'rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900';
