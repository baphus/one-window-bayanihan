export default function DraftControls({ status, onClear }) {
    return (
        <div className="flex items-center justify-between gap-4">
            <p className="text-sm text-gray-600" role="status" aria-live="polite">{status}</p>
            {status && onClear ? (
                <button type="button" onClick={onClear} className="shrink-0 text-sm font-medium underline underline-offset-4">
                    Clear saved draft
                </button>
            ) : null}
        </div>
    );
}
