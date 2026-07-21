import { useEffect, useRef } from 'react';

export default function UnsavedChangesModal({ show, onConfirm, onCancel, onSaveDraft }) {
  const cancelBtnRef = useRef(null);

  useEffect(() => {
    if (show) cancelBtnRef.current?.focus();
  }, [show]);

  useEffect(() => {
    if (!show) return;
    const handler = (e) => {
      if (e.key === 'Escape') onCancel();
    };
    document.addEventListener('keydown', handler);
    return () => document.removeEventListener('keydown', handler);
  }, [show, onCancel]);

  if (!show) return null;

  return (
    <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="unsaved-changes-title" aria-describedby="unsaved-changes-desc">
      <div className="fixed inset-0 bg-black/50 backdrop-blur-sm" onClick={onCancel} />
      <div className="relative z-10 w-full max-w-lg rounded-lg border border-slate-200 bg-white shadow-2xl owb-modal-animate">
        <div className="px-6 py-5">
          <div className="flex items-start gap-3">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100">
              <span
                className="material-symbols-outlined text-[22px] text-amber-600"
                style={{ fontVariationSettings: "'FILL' 1, 'wght' 500" }}
                aria-hidden="true"
              >
                warning
              </span>
            </div>

            <div>
              <h2
                id="unsaved-changes-title"
                className="text-base font-semibold text-slate-900"
              >
                Unsaved Changes
              </h2>

              <p
                id="unsaved-changes-desc"
                className="mt-2 text-sm leading-6 text-slate-600"
              >
                You have unsaved changes. If you leave now, your changes will be permanently lost.
              </p>
            </div>
          </div>
        </div>

        <div className={`flex items-center ${onSaveDraft ? 'justify-between' : 'justify-end'} border-t border-slate-200 px-6 py-4`}>
          {onSaveDraft && (
            <button
              onClick={onSaveDraft}
              type="button"
              className="inline-flex items-center gap-2 h-9 rounded-[3px] border border-amber-300 bg-amber-50 px-4 text-[12px] font-bold text-amber-700 transition hover:bg-amber-100"
            >
              <span className="material-symbols-outlined text-[16px]">save</span>
              Save as Draft
            </button>
          )}

          <div className="flex items-center gap-3">
            <button
              ref={cancelBtnRef}
              onClick={onCancel}
              type="button"
              className="h-9 px-2 text-[12px] font-bold text-slate-500 hover:text-slate-700 transition-colors"
            >
              Continue Editing
            </button>

            <button
              onClick={onConfirm}
              type="button"
              className="h-9 rounded-[3px] bg-red-600 px-4 text-[12px] font-bold text-white hover:bg-red-700 transition-colors"
            >
              Discard Changes
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
