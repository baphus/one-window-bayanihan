import { useEffect, useState } from 'react';

const toneConfig = {
  default: { button: 'bg-blue-900 hover:bg-blue-800', icon: 'help', iconColor: 'text-blue-600', iconBg: 'bg-blue-50' },
  danger: { button: 'bg-red-600 hover:bg-red-700', icon: 'warning', iconColor: 'text-red-600', iconBg: 'bg-red-50' },
};

export default function ConfirmDialog({
  open,
  title = 'Confirm',
  message = 'Are you sure?',
  confirmLabel = 'Confirm',
  cancelLabel = 'Cancel',
  tone = 'default',
  confirmVariant,
  onConfirm,
  onCancel,
  onClose,
  disabled = false,
  children,
}) {
  const [guarded, setGuarded] = useState(false);
  // Reset the one-shot guard whenever the dialog is closed/reopened.
  useEffect(() => {
    if (!open) setGuarded(false);
  }, [open]);

  if (!open) return null;
  const t = toneConfig[confirmVariant || tone];
  const handleCancel = onCancel || onClose;

  const handleConfirm = () => {
    if (guarded) return;
    setGuarded(true);
    onConfirm();
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4" onClick={handleCancel}>
      <div className="w-full max-w-md rounded-lg owb-modal-animate bg-white shadow-xl" onClick={(e) => e.stopPropagation()}>
        <div className="flex flex-col items-center px-6 py-5 text-center">
          <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full ${t.iconBg}`}>
            <span className={`material-symbols-outlined text-[22px] ${t.iconColor}`}>{t.icon}</span>
          </div>
          <h2 className="mt-3 text-[16px] font-extrabold text-slate-900">{title}</h2>
          {children ? (
            <div className="mt-3 w-full text-left">{children}</div>
          ) : (
            <p className="mt-1.5 text-[13px] text-slate-600 leading-relaxed">{message}</p>
          )}
        </div>
        <div className="flex justify-end gap-3 border-t border-slate-200 px-6 py-4">
          <button onClick={handleCancel} className="h-9 rounded-[3px] border border-slate-300 px-4 text-[12px] font-bold text-slate-700 hover:bg-slate-50 transition-colors">
            {cancelLabel}
          </button>
          <button onClick={handleConfirm} disabled={disabled || guarded} className={`h-9 rounded-[3px] px-4 text-[12px] font-bold text-white transition-colors disabled:opacity-50 disabled:cursor-not-allowed ${t.button}`}>
            {confirmLabel}
          </button>
        </div>
      </div>
    </div>
  );
}
