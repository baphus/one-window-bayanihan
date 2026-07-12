import { useEffect, useRef } from 'react';

export default function UnsavedChangesModal({ show, onConfirm, onCancel }) {
  const dialogRef = useRef(null);
  const cancelBtnRef = useRef(null);

  // Auto-focus "Continue Editing" when modal opens
  useEffect(() => {
    if (show) {
      cancelBtnRef.current?.focus();
    }
  }, [show]);

  // Escape key dismisses modal
  useEffect(() => {
    if (!show) return;
    const handler = (e) => {
      if (e.key === 'Escape') {
        e.stopPropagation();
        onCancel();
      }
    };
    document.addEventListener('keydown', handler, true);
    return () => document.removeEventListener('keydown', handler, true);
  }, [show, onCancel]);

  // Focus trap — keep Tab within the dialog
  useEffect(() => {
    if (!show) return;
    const dialog = dialogRef.current;
    if (!dialog) return;

    const handler = (e) => {
      if (e.key !== 'Tab') return;
      const focusable = dialog.querySelectorAll(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])',
      );
      if (focusable.length === 0) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];

      if (e.shiftKey) {
        if (document.activeElement === first) {
          e.preventDefault();
          last.focus();
        }
      } else {
        if (document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      }
    };
    document.addEventListener('keydown', handler);
    return () => document.removeEventListener('keydown', handler);
  }, [show]);

  if (!show) return null;

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="unsaved-changes-title"
      aria-describedby="unsaved-changes-desc"
      ref={dialogRef}
    >
      <div className="w-full max-w-md rounded-[3px] border-t-4 border-amber-500 bg-white shadow-xl">
        <div className="px-6 py-5">
          <div className="flex items-start gap-3">
            <span
              className="material-symbols-outlined mt-0.5 text-[22px] text-amber-500"
              style={{ fontVariationSettings: "'FILL' 1, 'wght' 400" }}
              aria-hidden="true"
            >
              warning
            </span>
            <div>
              <h2 id="unsaved-changes-title" className="text-[16px] font-extrabold text-slate-900">
                Unsaved Changes
              </h2>
              <p id="unsaved-changes-desc" className="mt-2 text-[13px] text-slate-600 leading-relaxed">
                You have unsaved changes. Are you sure you want to leave? Your changes will be lost.
              </p>
            </div>
          </div>
        </div>
        <div className="flex justify-end gap-3 border-t border-slate-200 px-6 py-4">
          <button
            ref={cancelBtnRef}
            onClick={onCancel}
            type="button"
            className="h-9 rounded-[3px] border border-slate-300 px-4 text-[12px] font-bold text-slate-700 hover:bg-slate-50 transition-colors"
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
  );
}
