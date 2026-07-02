const toneStyles = {
  default: { border: 'border-blue-900', button: 'bg-blue-900 hover:bg-blue-800' },
  danger: { border: 'border-red-600', button: 'bg-red-600 hover:bg-red-700' },
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

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4">
      <div className={`w-full max-w-md rounded-[3px] border-t-4 ${toneStyles[tone].border} bg-white shadow-xl`}>
        <div className="px-6 py-5">
          <h2 className="text-[16px] font-extrabold text-slate-900">{title}</h2>
          <p className="mt-2 text-[13px] text-slate-600 leading-relaxed">{message}</p>
        </div>
        <div className="flex justify-end gap-3 border-t border-slate-200 px-6 py-4">
          <button onClick={onCancel} className="h-9 rounded-[3px] border border-slate-300 px-4 text-[12px] font-bold text-slate-700 hover:bg-slate-50 transition-colors">
            {cancelLabel}
          </button>
          <button onClick={onConfirm} className={`h-9 rounded-[3px] px-4 text-[12px] font-bold text-white transition-colors ${toneStyles[tone].button}`}>
            {confirmLabel}
          </button>
        </div>
      </div>
    </div>
  );
}
