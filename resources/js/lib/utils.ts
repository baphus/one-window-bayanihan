export function formatDisplayDateTime(iso: string): string {
    return new Intl.DateTimeFormat('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
    }).format(new Date(iso));
}

export function formatDisplayTime(iso: string): string {
    return new Intl.DateTimeFormat('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
    }).format(new Date(iso));
}

export function formatDisplayDate(iso: string): string {
    if (!iso) return '';
    // Parse date-only strings as UTC to avoid timezone shift
    const dateStr = String(iso).match(/^\d{4}-\d{2}-\d{2}/)?.[0];
    const date = dateStr ? new Date(dateStr + 'T00:00:00Z') : new Date(iso);
    return new Intl.DateTimeFormat('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(date);
}

/**
 * Normalise a list page's category filter into a string id array.
 *
 * The server may echo back `category_ids` (array), a single `category_id`, or
 * both (a legacy link that carried one id alongside a new selection), so the
 * ids of both keys are merged and de-duplicated. Case, Client and Referral
 * lists all build their export query and filter chips from this.
 */
export function getCategoryFilterIds(filters: Record<string, unknown> | null | undefined): string[] {
    const raw = filters?.category_ids ?? filters?.category_id;
    const ids = Array.isArray(raw) ? [...raw] : (raw ? [raw] : []);
    const legacy = filters?.category_id;
    if (legacy && !ids.includes(legacy)) ids.push(legacy);
    return [...new Set(ids.filter((id) => id !== null && id !== undefined && id !== '').map(String))];
}

export function getCaseAgeInDays(timestamp: string): number {
    const parsed = new Date(timestamp);
    if (Number.isNaN(parsed.getTime())) return 0;

    return Math.floor(Math.max(0, Date.now() - parsed.getTime()) / (24 * 60 * 60 * 1000));
}
