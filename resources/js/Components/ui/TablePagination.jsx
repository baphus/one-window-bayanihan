/**
 * Page window control for the shared register table.
 *
 * Keeps the existing five-page window plus first/last jumps, and adds the
 * keyboard/screen-reader contract the register design relies on: an accessible
 * name for every chevron, `aria-current="page"` on the active number, and
 * disabled bounds controls instead of silently no-op buttons.
 *
 * @param {object} props
 * @param {number} props.currentPage
 * @param {number} props.totalPages
 * @param {function} props.onPageChange
 */
export default function TablePagination({ currentPage = 1, totalPages = 1, onPageChange }) {
    if (!totalPages || totalPages < 1) return null;

    const isFirst = currentPage <= 1;
    const isLast = currentPage >= totalPages;

    const maxVisible = 5;
    let start = Math.max(1, currentPage - Math.floor(maxVisible / 2));
    let end = Math.min(totalPages, start + maxVisible - 1);
    if (end - start + 1 < maxVisible) start = Math.max(1, end - maxVisible + 1);
    const pages = [];
    for (let i = start; i <= end; i++) pages.push(i);

    const navButtonClass =
        'flex h-8 w-8 items-center justify-center rounded text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-1 disabled:pointer-events-none disabled:text-slate-300';
    const numberClass =
        'flex h-8 min-w-8 items-center justify-center rounded px-1.5 text-[13px] font-medium tabular-nums transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-1';

    return (
        <nav aria-label="Pagination" className="flex items-center gap-1">
            <button
                type="button"
                aria-label="First page"
                disabled={isFirst}
                onClick={() => onPageChange?.(1)}
                className={navButtonClass}
            >
                <span aria-hidden="true" className="material-symbols-outlined text-[18px]">first_page</span>
            </button>
            <button
                type="button"
                aria-label="Previous page"
                disabled={isFirst}
                onClick={() => onPageChange?.(Math.max(1, currentPage - 1))}
                className={navButtonClass}
            >
                <span aria-hidden="true" className="material-symbols-outlined text-[18px]">chevron_left</span>
            </button>

            <div className="flex items-center gap-1 px-1">
                {start > 1 && (
                    <>
                        <button type="button" aria-label="Page 1" onClick={() => onPageChange?.(1)} className={`${numberClass} text-slate-600 hover:bg-slate-100`}>
                            1
                        </button>
                        <span aria-hidden="true" className="flex h-8 w-6 items-center justify-center text-[13px] text-slate-400">…</span>
                    </>
                )}
                {pages.map((p) => {
                    const isActive = p === currentPage;
                    return (
                        <button
                            key={p}
                            type="button"
                            aria-label={`Page ${p}`}
                            aria-current={isActive ? 'page' : undefined}
                            onClick={() => onPageChange?.(p)}
                            className={`${numberClass} ${
                                isActive
                                    ? 'bg-primary-fixed font-semibold text-primary'
                                    : 'text-slate-600 hover:bg-slate-100'
                            }`}
                        >
                            {p}
                        </button>
                    );
                })}
                {end < totalPages && (
                    <>
                        <span aria-hidden="true" className="flex h-8 w-6 items-center justify-center text-[13px] text-slate-400">…</span>
                        <button type="button" aria-label={`Page ${totalPages}`} onClick={() => onPageChange?.(totalPages)} className={`${numberClass} text-slate-600 hover:bg-slate-100`}>
                            {totalPages}
                        </button>
                    </>
                )}
            </div>

            <button
                type="button"
                aria-label="Next page"
                disabled={isLast}
                onClick={() => onPageChange?.(Math.min(totalPages, currentPage + 1))}
                className={navButtonClass}
            >
                <span aria-hidden="true" className="material-symbols-outlined text-[18px]">chevron_right</span>
            </button>
            <button
                type="button"
                aria-label="Last page"
                disabled={isLast}
                onClick={() => onPageChange?.(totalPages)}
                className={navButtonClass}
            >
                <span aria-hidden="true" className="material-symbols-outlined text-[18px]">last_page</span>
            </button>
        </nav>
    );
}
