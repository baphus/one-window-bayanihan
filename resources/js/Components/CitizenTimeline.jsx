/**
 * CitizenTimeline — the case record timeline shown to citizens.
 *
 * A vertical rail with a marker per recorded event, reading the way the
 * citizen portal's case record does: date, event title, the office that
 * recorded it, and a short description. The newest recorded event carries
 * the "Latest update" marker; events still expected are drawn as pending.
 *
 * The staff-facing UnifiedTimeline uses a different anatomy (icon dots,
 * relative-time labels), so this stays a separate component rather than a
 * variant of that one.
 */

const MARKER = {
    complete: {
        dot: 'bg-emerald-50 border-emerald-200 text-emerald-600',
        icon: 'check',
        date: 'text-emerald-700',
    },
    current: {
        dot: 'bg-surface-container-low border-slate-300 text-primary',
        icon: 'progress_activity',
        date: 'text-primary',
    },
    pending: {
        dot: 'bg-white border-slate-200 text-slate-400',
        icon: 'schedule',
        date: 'text-slate-400',
    },
};

function formatEventDate(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    return date.toLocaleDateString('en-PH', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

export default function CitizenTimeline({ events = [], statusBar = null, emptyMessage = 'No updates are recorded for this case yet.' }) {
    if (events.length === 0) {
        return (
            <p className="px-5 py-6 text-sm text-slate-500">{emptyMessage}</p>
        );
    }

    return (
        <div className="px-5 py-5">
            <ol className="relative">
                {/* Rail */}
                <span
                    aria-hidden="true"
                    className="absolute bottom-4 left-[13px] top-4 w-px bg-slate-200"
                />

                {events.map((event, index) => {
                    const isLast = index === events.length - 1;
                    const state = event.state ?? (isLast ? 'current' : 'complete');
                    const marker = MARKER[state] ?? MARKER.complete;
                    const isLatest = event.latest === true || (isLast && state === 'current');

                    return (
                        <li key={event.id ?? index} className="relative flex gap-3 pb-5 last:pb-0">
                            <span
                                aria-hidden="true"
                                className={`z-10 grid h-[27px] w-[27px] shrink-0 place-items-center rounded-full border ${marker.dot}`}
                            >
                                <span className="material-symbols-outlined text-[14px] leading-none">
                                    {marker.icon}
                                </span>
                            </span>

                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                                    <p className={`text-[12px] font-bold ${marker.date}`}>
                                        {formatEventDate(event.date ?? event.timestamp ?? event.created_at ?? event.createdAt)}
                                    </p>
                                    {isLatest ? (
                                        <span className="inline-flex items-center gap-1 text-[10px] font-bold text-slate-500">
                                            <span className="h-1.5 w-1.5 rounded-full bg-primary" />
                                            Latest update
                                        </span>
                                    ) : null}
                                </div>

                                <p className="mt-0.5 font-headline text-[14px] font-bold tracking-tight text-slate-900">
                                    {event.title}
                                </p>

                                {event.agency ? (
                                    <p className="mt-0.5 text-[12px] font-semibold text-slate-500">{event.agency}</p>
                                ) : null}

                                {event.description ? (
                                    <p className="mt-1 text-[13px] leading-relaxed text-slate-600">{event.description}</p>
                                ) : null}
                            </div>
                        </li>
                    );
                })}
            </ol>

            {statusBar ? <div className="mt-4">{statusBar}</div> : null}
        </div>
    );
}
