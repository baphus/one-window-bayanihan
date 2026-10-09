export default function TablePagination({ currentPage = 1, totalPages = 1, onPageChange }) {
    if (!totalPages || totalPages < 1) return null;

    const maxVisible = 5;
    let start = Math.max(1, currentPage - Math.floor(maxVisible / 2));
    let end = Math.min(totalPages, start + maxVisible - 1);
    if (end - start + 1 < maxVisible) start = Math.max(1, end - maxVisible + 1);
    const pages = [];
    for (let i = start; i <= end; i++) pages.push(i);

    return (
        <div className="flex items-center gap-1.5 text-slate-300">
            <button
                onClick={() => onPageChange?.(1)}
                className="w-7 h-7 flex items-center justify-center rounded-sm text-slate-300 hover:text-slate-700 transition"
            >
                <span className="material-symbols-outlined text-[20px] font-bold">first_page</span>
            </button>
            <button
                onClick={() => onPageChange?.(Math.max(1, currentPage - 1))}
                className="w-7 h-7 flex items-center justify-center rounded-sm text-slate-300 hover:text-slate-700 transition"
            >
                <span className="material-symbols-outlined text-[20px] font-bold">chevron_left</span>
            </button>
            <div className="flex items-center gap-1 px-3">
                {start > 1 && (
                    <>
                        <button
                            onClick={() => onPageChange?.(1)}
                            className="w-[30px] h-[30px] flex items-center justify-center rounded-[2px] hover:bg-slate-100 text-slate-700 text-[13px] font-bold transition"
                        >
                            1
                        </button>
                        <span className="w-[30px] h-[30px] flex items-center justify-center text-slate-400 text-[13px] font-bold">...</span>
                    </>
                )}
                {pages.map((p) => (
                    <button
                        key={p}
                        onClick={() => onPageChange?.(p)}
                        className={`w-[30px] h-[30px] flex items-center justify-center rounded-[2px] text-[13px] font-bold shadow-sm transition ${p === currentPage ? "bg-blue-900 text-white" : "hover:bg-slate-100 text-slate-700"}`}
                    >
                        {p}
                    </button>
                ))}
                {end < totalPages && (
                    <>
                        <span className="w-[30px] h-[30px] flex items-center justify-center text-slate-400 text-[13px] font-bold">...</span>
                        <button
                            onClick={() => onPageChange?.(totalPages)}
                            className="w-[30px] h-[30px] flex items-center justify-center rounded-[2px] hover:bg-slate-100 text-slate-700 text-[13px] font-bold transition"
                        >
                            {totalPages}
                        </button>
                    </>
                )}
            </div>
            <button
                onClick={() => onPageChange?.(Math.min(totalPages, currentPage + 1))}
                className="w-7 h-7 flex items-center justify-center rounded-sm text-slate-700 hover:text-slate-900 transition"
            >
                <span className="material-symbols-outlined text-[20px] font-bold">chevron_right</span>
            </button>
            <button
                onClick={() => onPageChange?.(totalPages)}
                className="w-7 h-7 flex items-center justify-center rounded-sm text-slate-700 hover:text-slate-900 transition"
            >
                <span className="material-symbols-outlined text-[20px] font-bold">last_page</span>
            </button>
        </div>
    );
}
