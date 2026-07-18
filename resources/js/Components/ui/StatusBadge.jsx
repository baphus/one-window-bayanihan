import clsx from 'clsx';
import { formatStatusLabel } from '@/lib/utils';
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

function normalizeStatus(status) {
  return String(status ?? '')
    .trim()
    .replace(/\s+/g, '_')
    .toUpperCase();
}

function StatusIcon({ icon: Icon, size }) {
  if (!Icon) return null;

  const iconSize = size === 'md' ? 'h-[11px] w-[11px]' : 'h-[10px] w-[10px]';

  return <Icon className={clsx(iconSize, 'shrink-0')} />;
}

export default function StatusBadge({ status, size = 'sm', showIcon = true }) {
  const normalizedStatus = normalizeStatus(status);
  const Icon = icons[normalizedStatus];
  const label = formatStatusLabel(status);

  const classes = clsx(
    'inline-flex items-center gap-1 rounded-[2px] border font-extrabold uppercase tracking-wide',
    sizes[size] ?? sizes.sm,
    colors[normalizedStatus] ?? colors.DEFAULT
  );

  return (
    <span className={classes}>
      {showIcon ? <StatusIcon icon={Icon} size={size} /> : null}
      {label}
    </span>
  );
}
