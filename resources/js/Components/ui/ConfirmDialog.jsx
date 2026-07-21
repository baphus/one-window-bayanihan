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
  onConfirm,
  onCancel,
}) {
  if (!open) return null;
  const t = toneConfig[tone];

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4">
      <div className="w-full max-w-md rounded-lg owb-modal-animate bg-white shadow-xl">
        <div className="flex flex-col items-center px-6 py-5 text-center">
          <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full ${t.iconBg}`}>
            <span className={`material-symbols-outlined text-[22px] ${t.iconColor}`}>{t.icon}</span>
          </div>
          <h2 className="mt-3 text-[16px] font-extrabold text-slate-900">{title}</h2>
          <p className="mt-1.5 text-[13px] text-slate-600 leading-relaxed">{message}</p>
        </div>
        <div className="flex justify-end gap-3 border-t border-slate-200 px-6 py-4">
          <button onClick={onCancel} className="h-9 rounded-[3px] border border-slate-300 px-4 text-[12px] font-bold text-slate-700 hover:bg-slate-50 transition-colors">
            {cancelLabel}
          </button>
          <button onClick={onConfirm} className={`h-9 rounded-[3px] px-4 text-[12px] font-bold text-white transition-colors ${t.button}`}>
            {confirmLabel}
          </button>
        </div>
      </div>
    </div>
  );
}
