import { useEffect } from 'react';

const toneStyles = {
  success: 'bg-emerald-50 border-emerald-400 text-emerald-800',
  info: 'bg-blue-50 border-blue-400 text-blue-800',
  warning: 'bg-amber-50 border-amber-400 text-amber-800',
  error: 'bg-red-50 border-red-400 text-red-800',
};

const toneIcons = {
  success: 'check_circle',
  info: 'info',
  warning: 'warning',
  error: 'error',
};

export default function AppToast({ message, onClose, tone = 'info', exiting = false, duration }) {
  useEffect(() => {
    if (!message || exiting || !duration) return;
    const timer = setTimeout(onClose, duration);
    return () => clearTimeout(timer);
  }, [message, onClose, duration, exiting]);

  if (!message) return null;

  return (
    <div
      className={`flex items-center gap-3 border-l-4 px-5 py-4 shadow-lg rounded-[3px] ${toneStyles[tone]} ${exiting ? 'animate-slide-out' : 'animate-slide-in'}`}
    >
      <span className="material-symbols-outlined text-[20px] shrink-0">{toneIcons[tone]}</span>
      <p className="text-[13px] font-bold leading-snug flex-1 min-w-0">{message}</p>
      <button
        onClick={onClose}
        className="flex items-center justify-center opacity-60 hover:opacity-100 shrink-0 ml-2"
      >
        <span className="material-symbols-outlined text-[18px]">close</span>
      </button>
    </div>
  );
}
