import clsx from 'clsx';
import { formatStatusLabel } from '@/lib/utils';
import {
  AlertTriangle,
  Archive,
  CheckCircle2,
  ClipboardList,
  Clock,
  FileEdit,
  FolderCheck,
  FolderOpen,
  Loader2,
  XCircle,
} from 'lucide-react';

const colors = {
  OPEN: 'border-blue-200 bg-blue-50 text-blue-700',
  CLOSED: 'border-slate-200 bg-slate-100 text-slate-600',
  DRAFT: 'border-amber-200 bg-amber-50 text-amber-700',
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
  DRAFT: FileEdit,
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

  return <Icon className={clsx(iconSize, 'shrink-0')} />;
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
  const Icon = iconOverride ?? icons[normalizedStatus];
  const label =
    labelOverride ??
    (variant === 'pill' ? titleCase(formatStatusLabel(status)) : formatStatusLabel(status));

  const sizeClasses = variant === 'pill' ? pillSizes[size] ?? pillSizes.sm : sizes[size] ?? sizes.sm;
  const classes = clsx(
    'inline-flex items-center gap-1 border',
    sizeClasses,
    variantClasses[variant] ?? variantClasses.sharp,
    colors[normalizedStatus] ?? colors.DEFAULT
  );

  return (
    <span className={classes}>
      {showIcon ? <StatusIcon icon={Icon} size={size} variant={variant} /> : null}
      {label}
    </span>
  );
}
