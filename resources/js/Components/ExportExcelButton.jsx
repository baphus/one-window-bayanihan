/**
 * ExportExcelButton — shared "Export Excel" trigger for list pages.
 *
 * Renders the standard emerald download button; extra props (e.g. data-tour)
 * are spread onto the button element. A custom `label` can be passed.
 */
export default function ExportExcelButton({ onClick, label = 'Export Excel', ...rest }) {
    return (
        <button
            type="button"
            onClick={onClick}
            {...rest}
            className="inline-flex h-10 items-center gap-2 whitespace-nowrap rounded-md border border-success bg-success px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-success/90 focus:outline-none focus:ring-2 focus:ring-success focus:ring-offset-2"
        >
            <span className="material-symbols-outlined text-[18px]">download</span>
            {label}
        </button>
    );
}
