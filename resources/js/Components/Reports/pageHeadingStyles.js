// Central design tokens for the Reports dashboard.
// This file is the single lever for the Reports visual system — changing the
// palettes/tokens here cascades to every card, chart and section.
//
// Palettes below are VALIDATED colorblind-safe (dataviz six-checks, per mode).
// Categorical palettes are for *identity* series only and must never carry
// status meaning. Status colors are reserved and always ship with an
// icon + label (never color alone). Status identity/label/order is sourced
// from the `case_statuses` reference table at runtime; the map below is the
// accessible color fallback keyed by status slug.

// Validated categorical palettes (all six dataviz checks pass per mode).
const CATEGORICAL_LIGHT = ['#2f6fb0', '#d9663b', '#3f915f', '#9b51b0', '#c73e78'];
const CATEGORICAL_DARK = ['#4f8ac2', '#cf7748', '#3f9765', '#a862ba', '#cc5a82'];

// Reserved status colors (accessible steps), keyed by status slug.
// These replace the raw seeded hexes where those failed contrast, while
// preserving the same semantic mapping used in `case_statuses`.
const STATUS_COLORS = {
  // Case statuses
  OPEN: '#3f915f',
  CLOSED: '#64748b',
  DRAFT: '#94a3b8',
  ARCHIVED: '#78716c',
  // Referral statuses
  PENDING: '#c9812b',
  PROCESSING: '#2f6fb0',
  FOR_COMPLIANCE: '#d9663b',
  COMPLETED: '#3f915f',
  REJECTED: '#c0392b',
};

// Semantic status tones (good / warning / serious / critical).
const STATUS_TONE = {
  good: '#3f915f',
  warning: '#c9812b',
  serious: '#d9663b',
  critical: '#c0392b',
  neutral: '#64748b',
};

export const COLORS = {
  primary: '#0b5a8c',
  primaryDark: '#4f8ac2',
  secondary: '#3f915f',
  accent: '#9b51b0',
  warning: STATUS_TONE.warning,
  danger: STATUS_TONE.critical,
  success: STATUS_TONE.good,
  border: '#cbd5e1',
  borderDark: '#334155',

  // Categorical (identity) — default to light; use paletteFor(mode) to switch.
  chartPalette: CATEGORICAL_LIGHT,
  chartPaletteLight: CATEGORICAL_LIGHT,
  chartPaletteDark: CATEGORICAL_DARK,

  statusColors: STATUS_COLORS,
  statusTone: STATUS_TONE,
};

// Return the categorical palette for the given mode ('light' | 'dark').
export function paletteFor(mode = 'light') {
  return mode === 'dark' ? CATEGORICAL_DARK : CATEGORICAL_LIGHT;
}

// Resolve a color for a status slug, falling back to a neutral tone.
// Prefer a reference-table `color` when provided (from case_statuses),
// otherwise use the accessible fallback map.
export function statusColor(slug, fallbackFromDb) {
  if (STATUS_COLORS[slug]) return STATUS_COLORS[slug];
  return fallbackFromDb || STATUS_TONE.neutral;
}

// Assign categorical colors in fixed order; a 6th+ category folds to "Other".
export function categoricalColors(count, mode = 'light') {
  const palette = paletteFor(mode);
  return Array.from({ length: count }, (_, i) =>
    i < palette.length ? palette[i] : '#94a3b8',
  );
}

// Shared, dark-mode-aware class tokens.
export const pageHeadingStyles = {
  pageTitle:
    'text-3xl md:text-[34px] font-black leading-tight tracking-tight text-slate-900 dark:text-slate-100',
  pageSubtitle: 'mt-1 text-[14px] leading-6 text-slate-600 dark:text-slate-400',
  sectionTitle:
    'text-[11px] font-extrabold uppercase tracking-[0.14em] text-[#0b5a8c] dark:text-[#7fb3e0]',
  metricLabel:
    'text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400',
};

// Reusable card shell (light + dark). Compose with extra classes as needed.
export const cardShell =
  'rounded-[3px] border border-[#cbd5e1] bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900';
