/**
 * Citizen status bars and alerts.
 *
 * The citizen portal states one thing at a time: whether the citizen needs to
 * do something, whether nothing is needed, or whether the case could not be
 * loaded. Each tone has its own icon, background, border and text colour.
 */

import AgencyLogo from '@/Components/AgencyLogo';

const TONES = {
    positive: {
        wrap: 'border border-emerald-200 bg-emerald-50',
        icon: 'check_circle',
        iconColor: 'text-emerald-600',
        title: 'text-emerald-800',
        body: 'text-emerald-700',
    },
    action: {
        wrap: 'border border-amber-200 bg-amber-50',
        icon: 'error',
        iconColor: 'text-amber-600',
        title: 'text-amber-800',
        body: 'text-amber-700',
    },
    error: {
        wrap: 'border border-rose-200 bg-rose-50',
        icon: 'error',
        iconColor: 'text-rose-600',
        title: 'text-rose-800',
        body: 'text-rose-700',
    },
    info: {
        wrap: 'border border-info/25 bg-info-container/40',
        icon: 'info',
        iconColor: 'text-info',
        title: 'text-slate-900',
        body: 'text-slate-600',
    },
};

/**
 * A full-width status bar: an icon, a bold headline, and optional detail.
 *
 * @param {object} props
 * @param {'positive'|'action'|'error'|'info'} [props.tone]
 * @param {string} props.title
 * @param {string} [props.detail]
 * @param {import('react').ReactNode} [props.children] Extra actions under the text.
 */
export function StatusBar({ tone = 'positive', title, detail, children }) {
    const palette = TONES[tone] ?? TONES.positive;

    return (
        <div className={`flex items-start gap-3 rounded-lg px-4 py-3 ${palette.wrap}`}>
            <span
                aria-hidden="true"
                className={`material-symbols-outlined mt-px shrink-0 text-[18px] leading-none ${palette.iconColor}`}
            >
                {palette.icon}
            </span>
            <div className="min-w-0 flex-1">
                <p className={`text-[13px] font-bold ${palette.title}`}>{title}</p>
                {detail ? <p className={`mt-0.5 text-[12px] leading-relaxed ${palette.body}`}>{detail}</p> : null}
                {children}
            </div>
        </div>
    );
}

/**
 * The agency card used in "Agencies Handling Your Case": the office mark,
 * its name, the referral status, a one-line status note, the last update
 * date, and a "View Progress" action.
 *
 * @param {object} props
 * @param {string} props.name
 * @param {string} [props.description]
 * @param {string} [props.statusLabel]
 * @param {string} [props.note]
 * @param {string} [props.updatedAt]
 * @param {string} [props.href] Link target for the progress action.
 * @param {object} [props.statusClass] Tailwind classes for the status badge.
 */
export function AgencyCard({ name, logoUrl, description, statusLabel, note, updatedAt, href, statusClass = 'border-slate-200 bg-slate-100 text-slate-600' }) {
    return (
        <section className="flex flex-col rounded-lg border border-slate-200 bg-white p-4">
            <div className="flex items-start gap-3">
                <AgencyLogo name={name} logoUrl={logoUrl} />
                <div className="min-w-0">
                    <h3 className="break-words [overflow-wrap:anywhere] font-headline text-[14px] font-extrabold tracking-tight text-slate-900">{name}</h3>
                    {description ? (
                        <p className="mt-0.5 text-[12px] leading-snug text-slate-500">{description}</p>
                    ) : null}
                </div>
            </div>

            {statusLabel ? (
                <div className="mt-4 flex items-center justify-between gap-3">
                    <p className="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">
                        Referral status
                    </p>
                    <span className={`inline-flex items-center gap-1.5 rounded-md border px-2 py-1 text-[11px] font-semibold ${statusClass}`}>
                        <span className="h-1.5 w-1.5 rounded-full bg-current" />
                        {statusLabel}
                    </span>
                </div>
            ) : null}

            {note ? <p className="mt-3 text-[13px] leading-relaxed text-slate-600">{note}</p> : null}

            {updatedAt ? (
                <p className="mt-3 text-[11px] text-slate-400">Updated {updatedAt}</p>
            ) : null}

            {href ? (
                <div className="mt-4">
                    <a
                        href={href}
                        className="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 py-2 text-[12px] font-bold text-primary transition-colors hover:border-slate-300 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                    >
                        <span className="material-symbols-outlined text-[15px] leading-none" aria-hidden="true">arrow_forward</span>
                        View Progress
                    </a>
                </div>
            ) : null}
        </section>
    );
}

/**
 * A document row: file icon, file name, its metadata, a status badge and a
 * view action.
 */
export function DocumentRow({ name, meta, statusLabel, statusClass = 'border-emerald-200 bg-emerald-50 text-emerald-700', actionLabel = 'View', onAction }) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
            <div className="flex min-w-0 items-start gap-3">
                <span
                    aria-hidden="true"
                    className="material-symbols-outlined mt-px shrink-0 text-[20px] leading-none text-primary"
                >
                    description
                </span>
                <div className="min-w-0">
                    <p className="truncate text-[13px] font-bold text-slate-900">{name}</p>
                    {meta ? <p className="mt-0.5 text-[11px] text-slate-500">{meta}</p> : null}
                </div>
            </div>

            <div className="flex items-center gap-3">
                {statusLabel ? (
                    <span className={`inline-flex items-center gap-1.5 rounded-md border px-2 py-1 text-[11px] font-semibold ${statusClass}`}>
                        <span className="h-1.5 w-1.5 rounded-full bg-current" />
                        {statusLabel}
                    </span>
                ) : null}
                {onAction ? (
                    <button
                        type="button"
                        onClick={onAction}
                        className="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 py-2 text-[12px] font-bold text-primary transition-colors hover:border-slate-300 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                    >
                        <span className="material-symbols-outlined text-[15px] leading-none" aria-hidden="true">download</span>
                        {actionLabel}
                    </button>
                ) : null}
            </div>
        </div>
    );
}
