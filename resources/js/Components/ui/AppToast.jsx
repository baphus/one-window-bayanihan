import { useEffect, useRef, useState } from 'react';

const tones = {
  success: { label: 'Success', icon: 'M5 12l4 4L19 6', surface: 'bg-success-container border-l-success', iconColor: 'bg-success' },
  error: { label: 'Error', icon: 'M6 6l12 12M18 6L6 18', surface: 'bg-error-container border-l-error', iconColor: 'bg-error' },
  warning: { label: 'Warning', icon: 'M12 5v9m0 4h.01', surface: 'bg-warning-container border-l-warning', iconColor: 'bg-warning' },
  info: { label: 'Informational', icon: 'M12 10v8m0-12h.01', surface: 'bg-info-container border-l-info', iconColor: 'bg-info' },
};

export default function AppToast({ message, onClose, tone = 'info', exiting = false, duration }) {
  const [hovered, setHovered] = useState(false);
  const [focused, setFocused] = useState(false);
  const remaining = useRef(duration);
  const closeRef = useRef(onClose);
  closeRef.current = onClose;

  useEffect(() => {
    if (exiting || hovered || focused || remaining.current == null) return;
    const started = Date.now();
    const timer = setTimeout(() => closeRef.current(), remaining.current);
    return () => {
      clearTimeout(timer);
      remaining.current = Math.max(0, remaining.current - (Date.now() - started));
    };
  }, [exiting, hovered, focused]);

  if (!message) return null;
  const style = tones[tone] || tones.info;

  return (
    <div
      role={tone === 'error' ? 'alert' : 'status'}
      onMouseEnter={() => setHovered(true)}
      onMouseLeave={() => setHovered(false)}
      onFocus={() => setFocused(true)}
      onBlur={(event) => { if (!event.currentTarget.contains(event.relatedTarget)) setFocused(false); }}
      className={`origin-top-right flex items-start gap-3 rounded-sm border-l-4 py-3 pl-3 pr-1 shadow-lg ${style.surface} motion-reduce:animate-none ${exiting ? 'animate-pop-out' : 'animate-pop-in'}`}
    >
      <span aria-hidden="true" className={`mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-white ${style.iconColor}`}>
        <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d={style.icon} /></svg>
      </span>
      <div className="min-w-0 flex-1 text-sm leading-5 text-on-surface">
        <p className="font-bold">{style.label}</p>
        <p className="break-words font-normal">{message}</p>
      </div>
      <button
        type="button"
        aria-label="Dismiss notification"
        onClick={onClose}
        className="-mt-2 flex h-10 w-10 shrink-0 items-center justify-center rounded-sm text-on-surface hover:bg-black/5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
      >
        <svg aria-hidden="true" className="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><path d="M6 6l12 12M18 6L6 18" /></svg>
      </button>
    </div>
  );
}
