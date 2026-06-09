import { AlertTriangle, ShieldAlert, Info, CheckCircle2 } from 'lucide-react';

export const SEVERITY_CONFIG = {
  warning: { icon: AlertTriangle, dot: 'bg-amber-500', label: 'Warning' },
  critical: { icon: ShieldAlert, dot: 'bg-rose-500', label: 'Critical' },
  info: { icon: Info, dot: 'bg-blue-500', label: 'Info' },
  success: { icon: CheckCircle2, dot: 'bg-emerald-500', label: 'Success' },
};

// Map Laravel notification class short names to severity levels
export const NOTIFICATION_TYPE_SEVERITY = {
  'CaseCreated': 'info',
  'CaseUpdated': 'info',
  'CaseAssigned': 'info',
  'CaseClosed': 'success',
  'CaseReopened': 'warning',
  'ReferralCreated': 'info',
  'ReferralUpdated': 'info',
  'ReferralCompleted': 'success',
  'StatusChanged': 'info',
  'UserCreated': 'info',
  'UserUpdated': 'info',
  'AlertGenerated': 'warning',
  'SystemAlert': 'critical',
};

export function getSeverityConfig(severity) {
  return SEVERITY_CONFIG[severity?.toLowerCase()] || SEVERITY_CONFIG.info;
}

/**
 * Extract the short class name from a Laravel notification type string.
 * e.g. "App\Notifications\Cases\CaseCreatedNotification" → "CaseCreated"
 */
export function extractNotificationTypeName(type) {
  if (!type) return null;
  const parts = type.split('\\');
  const className = parts[parts.length - 1] || '';
  return className.replace(/Notification$/, '') || null;
}

/**
 * Normalize a Laravel database notification into the alert display format.
 * @param {object} notification - The raw notification object
 * @param {object} [severityMap=NOTIFICATION_TYPE_SEVERITY] - Custom severity mapping
 */
export function normalizeNotification(notification, severityMap = NOTIFICATION_TYPE_SEVERITY) {
  const shortName = extractNotificationTypeName(notification.type);
  const severity = severityMap[shortName] || 'info';
  const data = notification.data || {};

  return {
    id: `notification-${notification.id}`,
    _rawId: notification.id,
    _source: 'notification',
    severity,
    title: data.title || data.subject || (shortName ? shortName.replace(/([A-Z])/g, ' $1').trim() : 'Notification'),
    message: data.message || data.body || '',
    created_at: notification.created_at,
    is_read: notification.read_at !== null || notification.read === true,
  };
}

export function timeAgo(dateStr) {
  if (!dateStr) return '';
  const now = Date.now();
  const date = new Date(dateStr).getTime();
  const diffMs = now - date;
  if (diffMs < 0) return 'just now';
  const diffMins = Math.floor(diffMs / 60000);
  if (diffMins < 1) return 'just now';
  if (diffMins < 60) return `${diffMins}m ago`;
  const diffHours = Math.floor(diffMins / 60);
  if (diffHours < 24) return `${diffHours}h ago`;
  const diffDays = Math.floor(diffHours / 24);
  if (diffDays < 30) return `${diffDays}d ago`;
  const diffMonths = Math.floor(diffDays / 30);
  if (diffMonths < 12) return `${diffMonths}mo ago`;
  return `${Math.floor(diffMonths / 12)}y ago`;
}

export function formatDisplayDate(dateStr) {
  if (!dateStr) return '';
  const now = Date.now();
  const date = new Date(dateStr).getTime();

  // Guard against invalid dates
  if (isNaN(date)) return '';

  const diffMs = now - date;

  // For dates newer than 24 hours, use timeAgo format
  const diffHours = diffMs / (1000 * 60 * 60);
  if (diffHours < 24) {
    return timeAgo(dateStr);
  }

  // For dates older than 24 hours: "June 9, 2026 at 3:45 PM"
  const d = new Date(dateStr);
  const datePart = d.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
  const timePart = d.toLocaleTimeString('en-US', {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  });
  return `${datePart} at ${timePart}`;
}
