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
  'PeerReferralCreated': 'info',
  'ReferralUpdated': 'info',
  'ReferralCompleted': 'success',
  'StatusChanged': 'info',
  'UserCreated': 'info',
  'UserUpdated': 'info',
  'AlertGenerated': 'warning',
  'SystemAlert': 'critical',
  'DownloadReady': 'success',
  'DownloadFailed': 'warning',
  // Progress / work-history / request fallbacks (old rows may use any of these)
  'MilestoneAdded': 'success',
  'MilestoneUpdated': 'info',
  'Milestone': 'info',
  'WorkHistory': 'info',
  'WorkHistoryAdded': 'info',
  'WorkHistoryUpdated': 'info',
  'ClientRequestDelivery': 'success',
  'ClientRequest': 'info',
  'ReferralClientRequest': 'info',
  'DocumentRequest': 'info',
  'DocumentReady': 'success',
  'CommentAdded': 'info',
  'ReferralComment': 'info',
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
 *
 * Backend contract (assumed, not verified): every notification `data` should
 * contain { title, message, case_number, actor_name, url, type }, but old rows
 * may miss any key — the fallbacks below cover that. Title/message are never
 * empty and never render the raw class basename.
 *
 * @param {object} notification - The raw notification object
 * @param {object} [severityMap=NOTIFICATION_TYPE_SEVERITY] - Custom severity mapping
 */
export function normalizeNotification(notification, severityMap = NOTIFICATION_TYPE_SEVERITY) {
  const safe = notification && typeof notification === 'object' ? notification : {};
  const shortName = extractNotificationTypeName(safe.type);
  const severity = severityMap[shortName] || 'info';
  const data = safe.data && typeof safe.data === 'object' ? safe.data : {};

  const caseRef = extractCaseReference(data);
  const actor = extractActorName(data);
  const detail = extractDetailTitle(data);
  const actionUrl = extractActionUrl(data);

  let title = toPlainLanguage(pickString(data.title, data.subject, data.notification_title));
  if (!title && detail) {
    title = `${fallbackBaseTitle(shortName)}: ${toPlainLanguage(detail)}`;
  }
  if (!title) {
    title = fallbackBaseTitle(shortName);
  }
  // Never render a raw class basename or an empty string.
  if (!title || title === shortName) {
    title = fallbackBaseTitle(shortName);
  }

  let message = toPlainLanguage(pickString(data.message, data.body, data.description, data.text));
  if (message) {
    // Attach the case reference when the stored message omits it so old rows
    // stay identifiable without mutating otherwise-good copy.
    if (caseRef && !message.includes(caseRef)) {
      message = `${message} (Case ${caseRef})`;
    }
  } else {
    message = buildFallbackMessage(shortName, { caseRef, actor, detail });
  }

  return {
    id: `notification-${safe.id ?? 'unknown'}`,
    _rawId: safe.id,
    _source: 'notification',
    severity,
    title,
    message,
    created_at: safe.created_at ?? data.created_at ?? null,
    is_read: safe.read_at !== null || safe.read === true,
    action_url: actionUrl,
    // Enriched keys for list/panel rendering; may be null when old rows miss them.
    case_number: caseRef,
    actor_name: actor,
    type: shortName,
  };
}

/**
 * Return the first non-blank string from the candidates, trimmed.
 */
function pickString(...candidates) {
  for (const value of candidates) {
    if (typeof value === 'string') {
      const trimmed = value.trim();
      if (trimmed !== '') return trimmed;
    } else if (typeof value === 'number' && Number.isFinite(value)) {
      return String(value);
    }
  }
  return null;
}

/**
 * Translate internal jargon (e.g. "client_request_delivery") into plain
 * OFW-facing language. Known technical phrases are replaced first, then any
 * remaining snake/kebab case is humanized. Returns '' for blank input.
 */
export function toPlainLanguage(value) {
  if (value === null || value === undefined) return '';
  const text = String(value).trim();
  if (text === '') return '';

  let out = text;
  const replacements = [
    [/client_request_delivery/gi, 'document delivery'],
    [/client_request_status/gi, 'request update'],
    [/client_request/gi, 'request'],
    [/work_history/gi, 'work history'],
    [/case_file/gi, 'case'],
    [/peer_referral/gi, 'peer referral'],
  ];
  for (const [pattern, replacement] of replacements) {
    out = out.replace(pattern, replacement);
  }
  // Humanize leftover snake/kebab jargon ("SOME_KEY" → "SOME KEY" → "Some key"
  // is left to callers; here just normalize separators when the whole string
  // looks like a key).
  if (/^[A-Za-z0-9_\-]+$/.test(out) && /[_-]/.test(out)) {
    out = out.replace(/[_-]+/g, ' ').replace(/\s+/g, ' ').trim();
  }
  return out;
}

/**
 * Normalize a notification short name / type string into a lookup key:
 * "MilestoneAdded" → "milestoneadded", "client_request_delivery" → "clientrequestdelivery".
 */
function typeKeyOf(shortName) {
  if (!shortName) return '';
  return String(shortName).toLowerCase().replace(/[^a-z0-9]/g, '');
}

const FALLBACK_BASE_TITLES = {
  casecreated: 'New case opened',
  caseupdated: 'Case updated',
  caseassigned: 'Case assigned to you',
  caseclosed: 'Case closed',
  casereopened: 'Case reopened',
  referralcreated: 'New referral',
  peerreferralcreated: 'New peer referral',
  referralupdated: 'Referral updated',
  referralcompleted: 'Referral completed',
  statuschanged: 'Status updated',
  usercreated: 'New account',
  userupdated: 'Account updated',
  alertgenerated: 'Attention needed',
  systemalert: 'System notice',
  downloadready: 'Download ready',
  downloadfailed: 'Download failed',
  milestoneadded: 'Progress update',
  milestoneupdated: 'Progress update',
  milestone: 'Progress update',
  workhistory: 'Work history update',
  workhistoryadded: 'Work history update',
  workhistoryupdated: 'Work history update',
  clientrequestdelivery: 'Document delivered',
  clientrequest: 'New request from your case worker',
  referralclientrequest: 'New request from your case worker',
  documentrequest: 'Document requested',
  documentready: 'Document ready',
  commentadded: 'New comment',
  referralcomment: 'New comment on your referral',
};

/**
 * Human base title for a notification type. Fuzzy-matches families
 * (milestone*, workhistory*, clientrequest*, download*, referral*, case*)
 * so legacy rows with variant class names still get plain language.
 * Never returns a raw class name or an empty string.
 */
export function fallbackBaseTitle(shortName) {
  const key = typeKeyOf(shortName);
  if (key && FALLBACK_BASE_TITLES[key]) return FALLBACK_BASE_TITLES[key];
  if (key.includes('milestone')) return 'Progress update';
  if (key.includes('workhistory')) return 'Work history update';
  if (key.includes('clientrequestdelivery')) return 'Document delivered';
  if (key.includes('clientrequest')) return 'New request from your case worker';
  if (key.includes('documentready') || key.includes('downloadready')) return 'Document ready';
  if (key.includes('documentrequest') || key.includes('downloadfailed')) {
    return key.includes('fail') ? 'Download failed' : 'Document requested';
  }
  if (key.includes('referralcompleted')) return 'Referral completed';
  if (key.includes('referral')) return 'Referral update';
  if (key.includes('caseclosed')) return 'Case closed';
  if (key.includes('casereopened')) return 'Case reopened';
  if (key.includes('caseassigned')) return 'Case assigned to you';
  if (key.includes('casecreated')) return 'New case opened';
  if (key.includes('case')) return 'Case update';
  if (key.includes('comment')) return 'New comment';
  if (key.includes('alert') || key.includes('system')) return 'System notice';
  return 'Update';
}

/** Case reference across legacy + contract key spellings. */
function extractCaseReference(data) {
  return pickString(
    data.case_number,
    data.caseNumber,
    data.reference_number,
    data.referenceNumber,
    data.tracker_number,
    data.trackerNumber,
    data.case_no,
    data.caseNo
  );
}

/** Actor display name across legacy + contract key spellings. */
function extractActorName(data) {
  return pickString(
    data.actor_name,
    data.actorName,
    data.actor,
    data.created_by_name,
    data.createdByName,
    data.user_name,
    data.userName,
    data.staff_name,
    data.staffName
  );
}

/** Milestone / request / document detail title across legacy key spellings. */
function extractDetailTitle(data) {
  return toPlainLanguage(pickString(
    data.milestone_title,
    data.milestoneTitle,
    data.milestone,
    data.request_title,
    data.requestTitle,
    data.document_title,
    data.documentTitle,
    data.item_title,
    data.itemTitle,
    data.referral_title,
    data.referralTitle
  ));
}

/** Deep-link URL across legacy + contract key spellings. */
function extractActionUrl(data) {
  const url = pickString(
    data.url,
    data.action_url,
    data.actionUrl,
    data.link,
    data.path,
    data.deep_link,
    data.deepLink
  );
  return url || null;
}

/**
 * Build a non-empty human message from type + case ref + actor + detail.
 * Every branch includes as much context as is available and always ends
 * with a usable sentence — never blank, never the raw class name.
 */
export function buildFallbackMessage(shortName, { caseRef, actor, detail } = {}) {
  const key = typeKeyOf(shortName);
  const caseSuffix = caseRef ? ` for case ${caseRef}` : '';
  const quotedDetail = detail ? `: "${detail}"` : '';
  const who = actor || 'Your case worker';

  if (key.includes('milestone')) {
    return actor
      ? `${actor} added a progress update${quotedDetail}${caseSuffix}.`
      : `A progress update was added${quotedDetail}${caseSuffix}.`;
  }
  if (key.includes('workhistory')) {
    return actor
      ? `${actor} updated the work history${quotedDetail}${caseSuffix}.`
      : `The work history was updated${quotedDetail}${caseSuffix}.`;
  }
  if (key.includes('clientrequestdelivery')) {
    return `${who} delivered a document${quotedDetail}${caseSuffix}. Open this notification to view it.`;
  }
  if (key.includes('clientrequest') || key.includes('documentrequest')) {
    return `${who} sent a request${quotedDetail}${caseSuffix}. Open this notification to respond.`;
  }
  if (key.includes('documentready') || key.includes('downloadready')) {
    return `Your document is ready${caseSuffix}. Open this notification to download it.`;
  }
  if (key.includes('downloadfailed') || key.includes('fail')) {
    return `A download could not be prepared${caseSuffix}. Please try again later.`;
  }
  if (key.includes('referralcompleted')) {
    return `Your referral was completed${caseSuffix}${actor ? ` by ${actor}` : ''}.`;
  }
  if (key.includes('referral')) {
    return `There is an update on your referral${caseSuffix}${actor ? ` from ${actor}` : ''}${detail ? `: "${detail}"` : ''}.`;
  }
  if (key.includes('caseclosed')) {
    return `Case ${caseRef || 'update'} was closed${actor ? ` by ${actor}` : ''}.`;
  }
  if (key.includes('casereopened')) {
    return `Case ${caseRef || 'update'} was reopened${actor ? ` by ${actor}` : ''}.`;
  }
  if (key.includes('caseassigned')) {
    return `Case ${caseRef || 'update'} was assigned${actor ? ` to ${actor}` : ''}.`;
  }
  if (key.includes('casecreated')) {
    return `Case ${caseRef || 'update'} was opened${actor ? ` by ${actor}` : ''}.`;
  }
  if (key.includes('case')) {
    return `There is an update on case ${caseRef || 'your case'}${actor ? ` from ${actor}` : ''}${detail ? `: "${detail}"` : ''}.`;
  }
  if (key.includes('comment')) {
    return `${who} added a comment${caseSuffix}${detail ? `: "${detail}"` : ''}.`;
  }
  // Fully generic: still names the case/actor/detail when known.
  const parts = [];
  if (actor) parts.push(`from ${actor}`);
  if (caseRef) parts.push(`for case ${caseRef}`);
  if (detail) parts.push(`: "${detail}"`);
  if (parts.length > 0) return `You have a new update ${parts.join(' ')}.`.replace(' :', ':');
  return 'You have a new update. Open this notification to view details.';
}
