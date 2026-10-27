/**
 * ProfileCard / ProfileSection / ProfileField
 *
 * The shared chrome for a profile page across every role. One card holds a
 * stack of titled sections, so the whole record reads top to bottom without
 * hopping between separate cards — the same pattern the citizen portal uses
 * for My Cases and the OFW profile.
 *
 * Extracted so the OFW profile and the staff profile (case manager, agency
 * focal, system admin) cannot drift apart in spacing, headings or labels.
 */

/** The card shell that wraps a profile's sections. */
export function ProfileCard({ children, className = '' }) {
    return (
        <div className={`overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm ${className}`}>
            {children}
        </div>
    );
}

/** A titled section inside a ProfileCard. */
export function ProfileSection({ title, description, action, children, className = '' }) {
    return (
        <section className={`border-b border-slate-100 px-5 py-5 last:border-b-0 sm:px-6 ${className}`}>
            <div className="mb-4 flex flex-wrap items-start justify-between gap-2">
                <div className="min-w-0">
                    <h2 className="font-headline text-[15px] font-extrabold tracking-tight text-slate-900">
                        {title}
                    </h2>
                    {description ? <p className="mt-0.5 text-[12px] text-slate-500">{description}</p> : null}
                </div>
                {action ?? null}
            </div>
            {children}
        </section>
    );
}

/** A labelled field wrapper: uppercase micro-label, control, error, hint. */
export function ProfileField({ label, htmlFor, error, hint, children }) {
    return (
        <div>
            <label htmlFor={htmlFor} className="block text-[11px] font-bold uppercase tracking-[0.08em] text-slate-600">
                {label}
            </label>
            {children}
            {hint && !error ? <p className="mt-1 text-[11px] text-slate-400">{hint}</p> : null}
            {error ? <p className="mt-1 text-[12px] font-medium text-rose-600">{error}</p> : null}
        </div>
    );
}

/** Shared control styling so every profile input matches. */
export const PROFILE_INPUT_CLASS =
    'mt-1.5 block h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] text-slate-900 transition-colors placeholder:text-slate-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500';

export const PROFILE_TEXTAREA_CLASS =
    'mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-[13px] text-slate-900 transition-colors placeholder:text-slate-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500';

/** A text input wired to the profile chrome. */
export function ProfileTextInput({ id, label, value, onChange, error, hint, type = 'text', placeholder, maxLength, autoComplete, disabled, readOnly }) {
    return (
        <ProfileField label={label} htmlFor={id} error={error} hint={hint}>
            <input
                id={id}
                type={type}
                value={value ?? ''}
                onChange={onChange ? (e) => onChange(e.target.value) : undefined}
                placeholder={placeholder}
                maxLength={maxLength}
                autoComplete={autoComplete}
                disabled={disabled}
                readOnly={readOnly}
                className={PROFILE_INPUT_CLASS}
            />
        </ProfileField>
    );
}

/** A read-only labelled value, for detail the user cannot edit. */
export function ProfileStaticField({ label, value }) {
    return (
        <div>
            <p className="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-600">{label}</p>
            <p className="mt-1.5 truncate text-[13px] text-slate-700">{value || '—'}</p>
        </div>
    );
}

/**
 * The single Save / Discard bar at the foot of an editable card.
 * `dirty` gates both controls; nothing else on the page should submit.
 */
export function ProfileSaveBar({ dirty, processing, onDiscard, discardLabel = 'Discard changes', saveLabel = 'Save changes', note }) {
    const disabled = !dirty || processing;

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 bg-slate-50 px-5 py-3 sm:px-6">
            <p className="text-[12px] text-slate-500">
                {note ?? (dirty ? 'You have unsaved changes.' : 'All changes are saved.')}
            </p>
            <div className="flex items-center gap-2">
                <button
                    type="button"
                    onClick={onDiscard}
                    disabled={disabled}
                    className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-[13px] font-bold text-slate-700 transition-colors hover:border-slate-300 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {discardLabel}
                </button>
                <button
                    type="submit"
                    disabled={disabled}
                    className="inline-flex items-center gap-1.5 rounded-lg bg-primary px-5 py-2.5 text-[13px] font-bold text-white shadow-sm transition-colors hover:bg-primary-container disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {processing && (
                        <span className="material-symbols-outlined animate-spin text-[17px]" aria-hidden="true">
                            progress_activity
                        </span>
                    )}
                    {processing ? 'Saving…' : saveLabel}
                </button>
            </div>
        </div>
    );
}
