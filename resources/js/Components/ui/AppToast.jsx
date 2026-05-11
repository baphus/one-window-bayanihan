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

export default function AppToast({ message, onClose, tone = 'info', duration = 3200 }) {
  useEffect(() => {
    if (!message) return;
    const timer = setTimeout(onClose, duration);
    return () => clearTimeout(timer);
  }, [message, onClose, duration]);

  if (!message) return null;

  return (
    <div className={`fixed top-6 right-6 z-[100] flex items-center gap-3 border-l-4 px-5 py-4 shadow-lg ${toneStyles[tone]} animate-slide-in`}>
      <span className="material-symbols-outlined text-[20px]">{toneIcons[tone]}</span>
      <p className="text-[13px] font-bold leading-snug">{message}</p>
      <button onClick={onClose} className="ml-4 flex items-center justify-center opacity-60 hover:opacity-100">
        <span className="material-symbols-outlined text-[18px]">close</span>
      </button>
    </div>
  );
}
