const MINUTE_MS = 60 * 1000;
const HOUR_MS = 60 * MINUTE_MS;
const DAY_MS = 24 * HOUR_MS;
const WEEK_MS = 7 * DAY_MS;

function parseDate(iso: string): Date | null {
    if (!iso.trim()) {
        return null;
    }

    const date = new Date(iso);

    return Number.isNaN(date.getTime()) ? null : date;
}

function formatMonthDayYear(date: Date): string {
    return new Intl.DateTimeFormat('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    }).format(date);
}

function formatMonthDay(date: Date): string {
    return new Intl.DateTimeFormat('en-US', {
        month: 'long',
        day: 'numeric',
    }).format(date);
}

function formatMonthYear(date: Date): string {
    return new Intl.DateTimeFormat('en-US', {
        month: 'long',
        year: 'numeric',
    }).format(date);
}

function startOfLocalDay(date: Date): Date {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
}

function startOfLocalWeek(date: Date): Date {
    const dayStart = startOfLocalDay(date);
    const diff = dayStart.getDay();

    return new Date(dayStart.getFullYear(), dayStart.getMonth(), dayStart.getDate() - diff);
}

function isSameCalendarDay(left: Date, right: Date): boolean {
    return (
        left.getFullYear() === right.getFullYear() &&
        left.getMonth() === right.getMonth() &&
        left.getDate() === right.getDate()
    );
}

function isYesterday(date: Date, now: Date): boolean {
    const yesterday = startOfLocalDay(now);
    yesterday.setDate(yesterday.getDate() - 1);

    return isSameCalendarDay(date, yesterday);
}

function isSameWeek(date: Date, now: Date): boolean {
    return startOfLocalWeek(date).getTime() === startOfLocalWeek(now).getTime();
}

function isLastWeek(date: Date, now: Date): boolean {
    const thisWeekStart = startOfLocalWeek(now);
    const lastWeekStart = new Date(thisWeekStart.getFullYear(), thisWeekStart.getMonth(), thisWeekStart.getDate() - 7);
    const lastWeekEnd = new Date(thisWeekStart.getFullYear(), thisWeekStart.getMonth(), thisWeekStart.getDate() - 1);

    const time = startOfLocalDay(date).getTime();
    return time >= lastWeekStart.getTime() && time <= lastWeekEnd.getTime();
}

function isSameMonth(date: Date, now: Date): boolean {
    return date.getFullYear() === now.getFullYear() && date.getMonth() === now.getMonth();
}

export function formatRelativeTime(iso: string): string {
    const date = parseDate(iso);

    if (!date) {
        return '—';
    }

    const now = new Date();
    const diffMs = now.getTime() - date.getTime();

    if (diffMs < 0 && Math.abs(diffMs) > MINUTE_MS) {
        return formatMonthDayYear(date);
    }

    const absDiffMs = Math.abs(diffMs);

    if (absDiffMs < MINUTE_MS) {
        return 'Just now';
    }

    if (absDiffMs < HOUR_MS) {
        const minutes = Math.floor(absDiffMs / MINUTE_MS);
        return `${minutes} minute${minutes === 1 ? '' : 's'} ago`;
    }

    if (absDiffMs < DAY_MS) {
        const hours = Math.floor(absDiffMs / HOUR_MS);
        return `${hours} hour${hours === 1 ? '' : 's'} ago`;
    }

    if (isYesterday(date, now)) {
        return 'Yesterday';
    }

    if (absDiffMs < WEEK_MS) {
        const days = Math.floor(absDiffMs / DAY_MS);
        return `${days} day${days === 1 ? '' : 's'} ago`;
    }

    if (absDiffMs < 30 * DAY_MS) {
        const weeks = Math.floor(absDiffMs / WEEK_MS);
        return `${weeks} week${weeks === 1 ? '' : 's'} ago`;
    }

    return formatMonthDayYear(date);
}

export function formatTimeAgo(iso: string): string {
    const date = parseDate(iso);

    if (!date) {
        return '—';
    }

    const now = new Date();
    const diffMs = now.getTime() - date.getTime();

    if (diffMs < 0 && Math.abs(diffMs) > MINUTE_MS) {
        return formatMonthDay(date);
    }

    const absDiffMs = Math.abs(diffMs);

    if (absDiffMs < MINUTE_MS) {
        return 'Just now';
    }

    if (absDiffMs < HOUR_MS) {
        return `${Math.floor(absDiffMs / MINUTE_MS)}m ago`;
    }

    if (absDiffMs < DAY_MS) {
        return `${Math.floor(absDiffMs / HOUR_MS)}h ago`;
    }

    if (absDiffMs < WEEK_MS) {
        return `${Math.floor(absDiffMs / DAY_MS)}d ago`;
    }

    return formatMonthDay(date);
}

export function formatDateGroup(iso: string): string {
    const date = parseDate(iso);

    if (!date) {
        return '—';
    }

    const now = new Date();

    if (isSameCalendarDay(date, now)) {
        return 'Today';
    }

    if (isYesterday(date, now)) {
        return 'Yesterday';
    }

    if (isSameWeek(date, now)) {
        return 'This Week';
    }

    if (isLastWeek(date, now)) {
        return 'Last Week';
    }

    if (isSameMonth(date, now)) {
        return 'This Month';
    }

    return formatMonthYear(date);
}
