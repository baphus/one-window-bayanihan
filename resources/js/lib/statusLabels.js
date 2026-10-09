/**
 * Human-readable labels for backend enum keys.
 *
 * Every map below is explicit: display copy must never be derived with a
 * naive underscore-replace (which would render e.g. "OFW" as "Ofw" or
 * "FOR_COMPLIANCE" as "FOR COMPLIANCE"). Backend keys stay exactly as
 * stored — only screen text goes through these maps.
 *
 * Unknown values pass through unchanged so new backend values degrade to
 * their raw text instead of blanking out.
 */
const CASE_STATUS_LABELS = {
    OPEN: 'Open',
    FOR_REFERRAL: 'For referral',
    CLOSED: 'Closed',
    ARCHIVED: 'Archived',
    DRAFT: 'Draft',
};

const REFERRAL_STATUS_LABELS = {
    PENDING: 'Pending',
    PROCESSING: 'Processing',
    FOR_COMPLIANCE: 'For Compliance',
    COMPLETED: 'Completed',
    REJECTED: 'Rejected',
};

const REJECTION_REASON_LABELS = {
    INCOMPLETE_REQUIREMENTS: 'Incomplete requirements',
    OUTSIDE_MANDATE: 'Outside mandate',
    DUPLICATE_REFERRAL: 'Duplicate referral',
    CLIENT_WITHDREW: 'Client withdrew',
    NO_SERVICE_CAPACITY: 'No service capacity',
    OTHER: 'Other',
};

const ACTOR_LABELS = {
    agency: 'Agency',
    case_manager: 'Case manager',
    system: 'System',
};

const SOURCE_LABELS = {
    internal: 'Internal',
    self_filed: 'Portal',
};

const CLIENT_TYPE_LABELS = {
    OFW: 'OFW',
    NEXT_OF_KIN: 'Next of kin',
    'Next of Kin': 'Next of kin',
};

const SEX_LABELS = {
    MALE: 'Male',
    FEMALE: 'Female',
    UNKNOWN: 'Unknown',
    Male: 'Male',
    Female: 'Female',
    Unknown: 'Unknown',
};

const VULNERABILITY_LABELS = {
    PWD: 'PWD',
    'Senior Citizen': 'Senior Citizen',
    'Solo Parent': 'Solo Parent',
    'Indigenous Person': 'Indigenous Person',
};

export const STATUS_LABELS = {
    ...CASE_STATUS_LABELS,
    ...REFERRAL_STATUS_LABELS,
    ...REJECTION_REASON_LABELS,
    ...ACTOR_LABELS,
    ...SOURCE_LABELS,
    ...CLIENT_TYPE_LABELS,
    ...SEX_LABELS,
    ...VULNERABILITY_LABELS,
};

/**
 * Map a backend enum key to its senior-friendly screen label.
 * Unknown, null, and blank values never throw: unknown keys pass through
 * as-is, nullish/blank values become ''.
 */
export function humanizeStatus(value) {
    if (value === null || value === undefined) {
        return '';
    }
    const raw = String(value).trim();
    if (raw === '') {
        return '';
    }
    return STATUS_LABELS[raw] ?? STATUS_LABELS[raw.toUpperCase()] ?? raw;
}
