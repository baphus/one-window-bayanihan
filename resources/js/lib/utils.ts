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

export function getCaseAgeInDays(timestamp: string): number {
    const parsed = new Date(timestamp);
    if (Number.isNaN(parsed.getTime())) return 0;

    return Math.floor(Math.max(0, Date.now() - parsed.getTime()) / (24 * 60 * 60 * 1000));
}
