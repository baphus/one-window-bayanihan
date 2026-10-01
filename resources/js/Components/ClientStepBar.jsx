/**
 * ClientStepBar — the shared per-office progress stepper for client-facing
 * surfaces (OFW portal + public tracking portal).
 *
 * One linear run of labelled steps per referral, driven by the
 * `{ label, state }` steps the backend builds per referral
 * (`TrackingService::buildAgencySteps()`). Visual behaviour matches the
 * long-standing portal stepper: step labels collapse on narrow screens and
 * only the current step is named there.
 *
 * Accessibility: step labels are visually hidden below the `sm` breakpoint,
 * so every step also carries its label in a visually-hidden span and on the
 * list item's accessible name — screen-reader users always hear the full
 * sequence, not just dots.
 */

/** Pure progress math, exported for tests. Never throws on bad input. */
export function getStepProgress(steps = []) {
    const list = Array.isArray(steps) ? steps : [];
    const activeIndex = list.findIndex((s) => s?.state === 'active');
    const activeLabel = list[activeIndex]?.label
        ?? (list.length && list.every((s) => s?.state === 'complete') ? list[list.length - 1].label : null);
    const completedCount = list.filter((s) => s?.state === 'complete').length;
    const progressPercent = list.length <= 1
        ? 0
        : activeIndex !== -1
            ? (activeIndex / (list.length - 1)) * 100
            : (completedCount / list.length) * 100;

    return { activeIndex, activeLabel, completedCount, progressPercent };
}

function stateText(state) {
    if (state === 'complete') return 'completed';
    if (state === 'active') return 'current step';
    return 'upcoming';
}

export default function ClientStepBar({ steps = [] }) {
    const { activeIndex, activeLabel, progressPercent } = getStepProgress(steps);

    return (
        <div className="relative px-1">
            <div className="absolute left-0 right-0 top-[9px] h-px bg-slate-200" />
            <div
                className="absolute left-0 top-[9px] h-px bg-primary transition-all duration-500 ease-out"
                style={{ width: `${progressPercent}%` }}
            />
            <ol
                aria-label="Progress"
                className="relative z-10 grid"
                style={{ gridTemplateColumns: `repeat(${steps.length}, minmax(0, 1fr))` }}
            >
                {steps.map((step) => {
                    const isComplete = step.state === 'complete';
                    const isActive = step.state === 'active';

                    return (
                        <li
                            key={step.label}
                            aria-label={`${step.label} — ${stateText(step.state)}`}
                            className="flex flex-col items-center"
                        >
                            <span
                                className={`flex h-[18px] w-[18px] items-center justify-center rounded-full ring-4 ring-white ${
                                    isComplete ? 'bg-primary text-white' :
                                    isActive ? 'border-2 border-primary bg-white' :
                                    'bg-slate-200'
                                }`}
                            >
                                {isComplete && <span aria-hidden="true" className="material-symbols-outlined text-[11px] font-bold">check</span>}
                                {isActive && <span className="h-1.5 w-1.5 rounded-full bg-primary motion-safe:animate-pulse" />}
                            </span>
                            <span
                                aria-hidden="true"
                                className={`mt-1.5 hidden max-w-[90px] px-1 text-center text-[10px] font-semibold leading-tight tracking-tight sm:line-clamp-2 ${
                                    isActive ? 'text-primary' : isComplete ? 'text-slate-800' : 'text-slate-400'
                                }`}
                                title={step.label}
                            >
                                {step.label}
                            </span>
                            {/* Screen-reader label: the visual label above is hidden on narrow screens. */}
                            <span className="sr-only">{step.label}: {stateText(step.state)}</span>
                        </li>
                    );
                })}
            </ol>
            {activeLabel && (
                <p className="mt-2 text-center text-[11px] font-semibold text-primary sm:hidden">
                    Current step: {activeLabel}
                </p>
            )}
            <span className="sr-only">
                {activeIndex !== -1
                    ? `Currently at step ${activeIndex + 1} of ${steps.length}: ${activeLabel}`
                    : (activeLabel ?? 'No current step')}
            </span>
        </div>
    );
}
