import { humanizeStatus } from '@/lib/statusLabels';
import {
  AlertTriangle,
  Archive,
  CheckCircle2,
  ClipboardList,
  Clock,
  FolderCheck,
  FolderOpen,
  Loader2,
  XCircle,
} from 'lucide-react';

const colors = {
  OPEN: 'border-blue-200 bg-blue-50 text-blue-700',
  CLOSED: 'border-slate-200 bg-slate-100 text-slate-600',
  ARCHIVED: 'border-gray-200 bg-gray-100 text-gray-600',
  PENDING: 'border-amber-200 bg-amber-50 text-amber-700',
  PROCESSING: 'border-blue-200 bg-blue-50 text-blue-700',
  FOR_COMPLIANCE: 'border-orange-200 bg-orange-50 text-orange-700',
  COMPLETED: 'border-emerald-200 bg-emerald-50 text-emerald-700',
  REJECTED: 'border-rose-200 bg-rose-50 text-rose-700',
  OVERDUE: 'border-red-200 bg-red-50 text-red-700',
  ACTIVE: 'border-emerald-200 bg-emerald-50 text-emerald-700',
  INACTIVE: 'border-slate-200 bg-slate-100 text-slate-600',
  BEING_PREPARED: 'border-blue-200 bg-blue-50 text-blue-700',
  IN_PROGRESS: 'border-indigo-200 bg-indigo-50 text-indigo-700',
  RESOLVED: 'border-emerald-200 bg-emerald-50 text-emerald-700',
  DEFAULT: 'border-slate-200 bg-white text-slate-600',
};

const icons = {
  OPEN: FolderOpen,
  CLOSED: FolderCheck,
  ARCHIVED: Archive,
  PENDING: Clock,
  PROCESSING: Loader2,
  FOR_COMPLIANCE: ClipboardList,
  COMPLETED: CheckCircle2,
  REJECTED: XCircle,
  OVERDUE: AlertTriangle,
  ACTIVE: CheckCircle2,
  INACTIVE: XCircle,
};

const sizes = {
  sm: 'text-[10px] px-2 py-[3px]',
  md: 'text-[11px] px-2.5 py-1',
};

const pillSizes = {
  sm: 'text-xs px-2.5 py-1',
  md: 'text-xs px-3 py-1.5',
};

const variantClasses = {
  sharp: 'rounded-[2px] font-extrabold uppercase tracking-wide',
  pill: 'rounded-full font-semibold',
  soft: 'rounded font-semibold',
};

/**
 * Soft register treatment: a low-saturation tint with a saturated leading dot.
 * Text keeps full contrast against the tint, so the label — not the colour —
 * carries the meaning (WCAG 1.4.1).
 */
const softColors = {
  OPEN: 'bg-primary-fixed/70 text-primary',
  CLOSED: 'bg-slate-100 text-slate-700',
  ARCHIVED: 'bg-gray-100 text-gray-700',
  PENDING: 'bg-amber-50 text-amber-800',
  PROCESSING: 'bg-primary-fixed/70 text-primary',
  FOR_COMPLIANCE: 'bg-orange-50 text-orange-800',
  COMPLETED: 'bg-emerald-50 text-emerald-800',
  REJECTED: 'bg-rose-50 text-rose-800',
  OVERDUE: 'bg-red-50 text-red-800',
  ACTIVE: 'bg-emerald-50 text-emerald-800',
  INACTIVE: 'bg-slate-100 text-slate-700',
  BEING_PREPARED: 'bg-primary-fixed/70 text-primary',
  IN_PROGRESS: 'bg-indigo-50 text-indigo-800',
  RESOLVED: 'bg-emerald-50 text-emerald-800',
  PENDING_REVIEW: 'bg-amber-50 text-amber-800',
  FOR_REFERRAL: 'bg-sky-50 text-sky-800',
  DEFAULT: 'bg-slate-100 text-slate-700',
};

const softDotColors = {
  OPEN: 'bg-primary',
  CLOSED: 'bg-slate-400',
  ARCHIVED: 'bg-gray-400',
  PENDING: 'bg-amber-500',
  PROCESSING: 'bg-primary',
  FOR_COMPLIANCE: 'bg-orange-500',
  COMPLETED: 'bg-emerald-500',
  REJECTED: 'bg-rose-500',
  OVERDUE: 'bg-red-500',
  ACTIVE: 'bg-emerald-500',
  INACTIVE: 'bg-slate-400',
  BEING_PREPARED: 'bg-primary',
  IN_PROGRESS: 'bg-indigo-500',
  RESOLVED: 'bg-emerald-500',
  PENDING_REVIEW: 'bg-amber-500',
  FOR_REFERRAL: 'bg-sky-500',
  DEFAULT: 'bg-slate-400',
};

const softSizes = {
  sm: 'text-[11.5px] px-2 py-1 gap-1.5',
  md: 'text-[12.5px] px-2.5 py-1.5 gap-1.5',
};

function normalizeStatus(status) {
  return String(status ?? '')
    .trim()
    .replace(/\s+/g, '_')
    .toUpperCase();
}

function titleCase(value) {
  return String(value)
    .toLowerCase()
    .replace(/_/g, ' ')
    .replace(/(^|\s)\S/g, (char) => char.toUpperCase());
}

function StatusIcon({ icon: Icon, size, variant }) {
  if (!Icon) return null;

  const iconSize =
    variant === 'pill'
      ? size === 'md'
        ? 'h-[14px] w-[14px]'
        : 'h-[13px] w-[13px]'
      : size === 'md'
        ? 'h-[11px] w-[11px]'
        : 'h-[10px] w-[10px]';

  return <Icon className={`${iconSize} shrink-0`} />;
}

export default function StatusBadge({
  status,
  size = 'sm',
  showIcon = true,
  variant = 'sharp',
  label: labelOverride,
  icon: iconOverride,
}) {
  const normalizedStatus = normalizeStatus(status);

  if (variant === 'soft') {
    const label =
      labelOverride ?? titleCase(humanizeStatus(status));
    const classes =
      'inline-flex items-center ' +
      `${softSizes[size] ?? softSizes.sm} ${softColors[normalizedStatus] ?? softColors.DEFAULT}`;

    return (
      <span className={classes}>
        <span
          aria-hidden="true"
          className={`h-1.5 w-1.5 shrink-0 rounded-full ${softDotColors[normalizedStatus] ?? softDotColors.DEFAULT}`}
        />
        {label}
      </span>
    );
  }

  const Icon = iconOverride ?? icons[normalizedStatus];
  const label =
    labelOverride ??
    (variant === 'pill' ? titleCase(humanizeStatus(status)) : humanizeStatus(status));

  const sizeClasses = variant === 'pill' ? pillSizes[size] ?? pillSizes.sm : sizes[size] ?? sizes.sm;
  const classes =
    'inline-flex items-center gap-1 border ' +
    `${sizeClasses} ${variantClasses[variant] ?? variantClasses.sharp} ${colors[normalizedStatus] ?? colors.DEFAULT}`;

  return (
    <span className={classes}>
      {showIcon ? <StatusIcon icon={Icon} size={size} variant={variant} /> : null}
      {label}
    </span>
  );
}
