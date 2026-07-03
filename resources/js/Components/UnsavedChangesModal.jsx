export default function UnsavedChangesModal({
  show,
  onConfirm,
  onCancel,
}) {
  if (!show) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4">
      <div className="w-full max-w-md rounded-[3px] border-t-4 border-amber-500 bg-white shadow-xl">
        <div className="px-6 py-5">
          <div className="flex items-start gap-3">
            <span className="material-symbols-outlined mt-0.5 text-[22px] text-amber-500" style={{ fontVariationSettings: "'FILL' 1, 'wght' 400" }}>
              warning
            </span>
            <div>
              <h2 className="text-[16px] font-extrabold text-slate-900">Unsaved Changes</h2>
              <p className="mt-2 text-[13px] text-slate-600 leading-relaxed">
                You have unsaved changes. Are you sure you want to leave? Your changes will be lost.
              </p>
            </div>
          </div>
        </div>
        <div className="flex justify-end gap-3 border-t border-slate-200 px-6 py-4">
          <button
            onClick={onCancel}
            className="h-9 rounded-[3px] border border-slate-300 px-4 text-[12px] font-bold text-slate-700 hover:bg-slate-50 transition-colors"
          >
            Continue Editing
          </button>
          <button
            onClick={onConfirm}
            className="h-9 rounded-[3px] bg-red-600 px-4 text-[12px] font-bold text-white hover:bg-red-700 transition-colors"
          >
            Discard Changes
          </button>
        </div>
      </div>
    </div>
  );
}
