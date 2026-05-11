import { useEffect, useMemo, useState } from 'react';

export function UnifiedTable({
  data,
  columns,
  keyExtractor,
  title,
  description,
  searchPlaceholder = 'Search records...',
  searchValue = '',
  onSearchChange,
  onAdvancedFilters,
  isAdvancedFiltersOpen = false,
  advancedFiltersContent,
  onColumnsControl,
  onNewRecord,
  newRecordLabel = '+ New Record',
  viewMode = 'list',
  onViewModeChange,
  activeFilters = [],
  onRemoveFilter,
  onClearFilters,
  currentPage = 1,
  totalPages = 1,
  totalRecords = 0,
  startIndex = 1,
  endIndex = 10,
  rowsPerPage = 10,
  rowsPerPageOptions = [10, 25, 50],
  onPageChange,
  onRowsPerPageChange,
  sortKey,
  sortDirection,
  onSortChange,
  defaultSortKey,
  defaultSortDirection,
  showSortReset = true,
  hideControlBar = false,
  hidePagination = false,
  variant = 'default',
}) {
  const isColumnSortable = (col) =>
    col.sortable ?? (col.key.toLowerCase() !== 'actions' && col.title.toUpperCase() !== 'ACTIONS');

  const inferDirectionForColumn = (col) => {
    const sig = `${col.key} ${col.title}`.toLowerCase();
    return /(date|time|age|timestamp|created|updated)/.test(sig) ? 'desc' : 'asc';
  };

  const inferredDefault = useMemo(() => {
    const sortable = columns.filter((c) => isColumnSortable(c));
    if (!sortable.length) return { key: undefined, direction: 'asc' };
    if (defaultSortKey) {
      const m = sortable.find((c) => c.key === defaultSortKey);
      return { key: m?.key, direction: defaultSortDirection ?? (m ? inferDirectionForColumn(m) : 'asc') };
    }
    const preferred = sortable.find((c) => /(created|updated).*?(at|date)|timestamp|age/i.test(c.key))
      || sortable.find((c) => /(date|time|age|created|updated)/i.test(c.title));
    if (preferred) return { key: preferred.key, direction: defaultSortDirection ?? inferDirectionForColumn(preferred) };
    return { key: sortable[0].key, direction: defaultSortDirection ?? 'asc' };
  }, [columns, defaultSortDirection, defaultSortKey]);

  const [intKey, setIntKey] = useState(sortKey ?? inferredDefault.key);
  const [intDir, setIntDir] = useState(sortDirection ?? inferredDefault.direction);

  useEffect(() => {
    if (sortKey !== undefined) return;
    const has = intKey && columns.some((c) => c.key === intKey && isColumnSortable(c));
    if (!has) { setIntKey(inferredDefault.key); setIntDir(inferredDefault.direction); }
  }, [columns, inferredDefault, intKey, sortKey]);

  const effKey = sortKey ?? intKey ?? inferredDefault.key;
  const effDir = sortKey !== undefined ? (sortDirection ?? inferredDefault.direction) : intDir;

  const resolveVal = (value) => {
    if (value === null || value === undefined) return '';
    if (value instanceof Date) return value.getTime();
    if (typeof value === 'number') return Number.isFinite(value) ? value : 0;
    if (typeof value === 'boolean') return value ? 1 : 0;
    const t = String(value);
    const d = Date.parse(t);
    return Number.isNaN(d) ? t : d;
  };

  const sortedData = useMemo(() => {
    if (!effKey) return data;
    const col = columns.find((c) => c.key === effKey);
    if (!col || !isColumnSortable(col)) return data;
    const mult = effDir === 'asc' ? 1 : -1;
    return [...data].sort((a, b) => {
      const ra = col.sortAccessor ? col.sortAccessor(a) : a[col.key];
      const rb = col.sortAccessor ? col.sortAccessor(b) : b[col.key];
      const va = resolveVal(ra);
      const vb = resolveVal(rb);
      if (typeof va === 'number' && typeof vb === 'number') return (va - vb) * mult;
      return String(va).localeCompare(String(vb), undefined, { numeric: true, sensitivity: 'base' }) * mult;
    });
  }, [columns, data, effDir, effKey]);

  const handleSort = (key) => {
    const next = effKey === key && effDir === 'asc' ? 'desc' : 'asc';
    if (onSortChange) { onSortChange(key, next); return; }
    setIntKey(key); setIntDir(next);
  };

  const hasSortable = columns.some((c) => isColumnSortable(c));
  const canReset = inferredDefault.key && (effKey !== inferredDefault.key || effDir !== inferredDefault.direction);

  const handleReset = () => {
    if (!inferredDefault.key) return;
    if (onSortChange) { onSortChange(inferredDefault.key, inferredDefault.direction); return; }
    setIntKey(inferredDefault.key); setIntDir(inferredDefault.direction);
  };

  const renderPagination = () => {
    const pages = [];
    const maxVisible = 5;
    let start = Math.max(1, currentPage - Math.floor(maxVisible / 2));
    let end = Math.min(totalPages, start + maxVisible - 1);
    if (end - start + 1 < maxVisible) start = Math.max(1, end - maxVisible + 1);
    for (let i = start; i <= end; i++) pages.push(i);

    return (
      <div className="flex items-center gap-1.5 text-[#cbd5e1]">
        <button onClick={() => onPageChange?.(1)} className="w-7 h-7 flex items-center justify-center rounded-sm text-slate-300 hover:text-slate-700 transition"><span className="material-symbols-outlined text-[20px] font-bold">first_page</span></button>
        <button onClick={() => onPageChange?.(Math.max(1, currentPage - 1))} className="w-7 h-7 flex items-center justify-center rounded-sm text-slate-300 hover:text-slate-700 transition"><span className="material-symbols-outlined text-[20px] font-bold">chevron_left</span></button>
        <div className="flex items-center gap-1 px-3">
          {start > 1 && <><button onClick={() => onPageChange?.(1)} className="w-[30px] h-[30px] flex items-center justify-center rounded-[2px] hover:bg-[#f1f5f9] text-slate-700 text-[13px] font-bold transition">1</button><span className="w-[30px] h-[30px] flex items-center justify-center text-slate-400 text-[13px] font-bold">...</span></>}
          {pages.map((p) => (
            <button key={p} onClick={() => onPageChange?.(p)} className={`w-[30px] h-[30px] flex items-center justify-center rounded-[2px] text-[13px] font-bold shadow-sm transition ${p === currentPage ? 'bg-[#0b5384] text-white' : 'hover:bg-[#f1f5f9] text-slate-700'}`}>{p}</button>
          ))}
          {end < totalPages && <><span className="w-[30px] h-[30px] flex items-center justify-center text-slate-400 text-[13px] font-bold">...</span><button onClick={() => onPageChange?.(totalPages)} className="w-[30px] h-[30px] flex items-center justify-center rounded-[2px] hover:bg-[#f1f5f9] text-slate-700 text-[13px] font-bold transition">{totalPages}</button></>}
        </div>
        <button onClick={() => onPageChange?.(Math.min(totalPages, currentPage + 1))} className="w-7 h-7 flex items-center justify-center rounded-sm text-slate-700 hover:text-slate-900 transition"><span className="material-symbols-outlined text-[20px] font-bold">chevron_right</span></button>
        <button onClick={() => onPageChange?.(totalPages)} className="w-7 h-7 flex items-center justify-center rounded-sm text-slate-700 hover:text-slate-900 transition"><span className="material-symbols-outlined text-[20px] font-bold">last_page</span></button>
      </div>
    );
  };

  return (
    <div className="space-y-8 w-full">
      {(title || description) && (
        <div className="space-y-2">
          {title && <h1 className="text-2xl font-bold tracking-tight text-slate-900">{title}</h1>}
          {description && <p className="text-slate-500 max-w-2xl">{description}</p>}
        </div>
      )}

      <div className={variant === 'embedded' ? 'bg-white overflow-hidden w-full' : 'bg-white border border-[#cbd5e1] overflow-hidden w-full rounded-md shadow-sm'}>
        {!hideControlBar && (
          <div className="p-4 bg-[#f8fafc] flex flex-col lg:flex-row items-center justify-between gap-4 border-b border-[#cbd5e1] min-h-[72px]">
            <div className="relative flex-1 w-full max-w-md h-[40px]">
              <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[20px]">search</span>
              <input type="text" placeholder={searchPlaceholder} value={searchValue} onChange={(e) => onSearchChange?.(e.target.value)} className="w-full h-full pl-10 pr-4 bg-white border border-[#cbd5e1] rounded-[2px] text-[14px] text-slate-600 placeholder-slate-400 outline-none focus:ring-1 focus:ring-[#0b5384] transition" />
            </div>
            <div className="flex w-full flex-wrap items-center gap-3 pb-2 lg:w-auto lg:flex-nowrap lg:pb-0">
              {onAdvancedFilters && (
                <div className="relative shrink-0">
                  <button onClick={onAdvancedFilters} className="h-[40px] px-4 border border-[#cbd5e1] text-[14px] font-bold text-slate-600 rounded-[2px] bg-white flex items-center justify-center gap-2 hover:bg-slate-50 transition-colors whitespace-nowrap relative">
                    <span className="material-symbols-outlined text-[18px]">tune</span> Filters
                  </button>
                  {isAdvancedFiltersOpen && advancedFiltersContent && (
                    <div className="absolute right-0 top-[calc(100%+8px)] z-50 w-72 rounded-[3px] border border-[#cbd5e1] bg-white p-5 shadow-lg">{advancedFiltersContent}</div>
                  )}
                </div>
              )}
              {onColumnsControl && (
                <button onClick={onColumnsControl} className="h-[40px] px-4 border border-[#cbd5e1] text-[14px] font-bold text-slate-600 rounded-[2px] bg-white flex items-center justify-center gap-2 hover:bg-slate-50 transition-colors whitespace-nowrap">
                  <span className="material-symbols-outlined text-[18px]">view_column</span> Columns
                </button>
              )}
              {showSortReset && hasSortable && (
                <button type="button" onClick={handleReset} disabled={!canReset} className="h-[40px] px-4 border border-[#cbd5e1] text-[14px] font-bold text-slate-600 rounded-[2px] bg-white flex items-center justify-center gap-2 hover:bg-slate-50 transition-colors whitespace-nowrap disabled:cursor-not-allowed disabled:opacity-50">
                  <span className="material-symbols-outlined text-[18px]">restart_alt</span> Reset Sort
                </button>
              )}
              {onViewModeChange && (
                <div className="flex items-center bg-[#f1f5f9] rounded-[2px] p-1 border border-[#cbd5e1] h-[40px] shrink-0">
                  <button onClick={() => onViewModeChange('list')} className={`h-full w-8 flex items-center justify-center rounded-[2px] ${viewMode === 'list' ? 'bg-white shadow-sm border border-[#cbd5e1] text-[#0b5384]' : 'text-slate-400 hover:text-slate-600'}`}><span className="material-symbols-outlined text-[18px]">list</span></button>
                  <button onClick={() => onViewModeChange('grid')} className={`h-full w-8 flex items-center justify-center rounded-[2px] ${viewMode === 'grid' ? 'bg-white shadow-sm border border-[#cbd5e1] text-[#0b5384]' : 'text-slate-400 hover:text-slate-600'}`}><span className="material-symbols-outlined text-[18px]">grid_view</span></button>
                </div>
              )}
              {onNewRecord && (
                <button onClick={onNewRecord} className="h-[40px] px-5 bg-[#0b5384] text-white text-[14px] font-bold rounded-[3px] flex items-center gap-2 hover:bg-[#09416a] transition-colors ml-2 shadow-sm whitespace-nowrap shrink-0">
                  <span className="font-semibold text-[16px]">+</span> {newRecordLabel.replace('+ ', '')}
                </button>
              )}
            </div>
          </div>
        )}

        {activeFilters.length > 0 && (
          <div className="px-5 py-[14px] flex items-center flex-wrap gap-4 border-b border-[#cbd5e1] bg-white text-[12px]">
            <span className="font-bold uppercase tracking-widest text-[#94a3b8]">Active Filters:</span>
            {activeFilters.map((f, i) => (
              <div key={`${f.key}-${i}`} className="flex items-center gap-1.5 bg-[#f0f7fc] text-[#0b5384] px-3 py-1 rounded-[2px] font-bold border border-[#d2e5f3]">
                {f.label}: {f.value}
                <button onClick={() => onRemoveFilter?.(f)} className="flex items-center justify-center hover:opacity-75"><span className="material-symbols-outlined text-[15px]">close</span></button>
              </div>
            ))}
            {onClearFilters && <button onClick={onClearFilters} className="font-bold text-[#0b5384] hover:underline text-[13px]">Clear All</button>}
          </div>
        )}

        {viewMode === 'list' ? (
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="bg-[#f8fafc] border-b border-[#cbd5e1]">
                  {columns.map((col) => (
                    <th key={col.key} className={`px-5 py-4 text-[12px] font-extrabold uppercase tracking-widest text-[#64748b] whitespace-nowrap ${col.className || ''}`}>
                      {isColumnSortable(col) ? (
                        <button type="button" onClick={() => handleSort(col.key)} className="inline-flex items-center gap-1 hover:text-[#0b5384] transition-colors">
                          <span>{col.title}</span>
                          <span className="material-symbols-outlined text-[15px] leading-none">{effKey === col.key ? (effDir === 'asc' ? 'arrow_upward' : 'arrow_downward') : 'unfold_more'}</span>
                        </button>
                      ) : col.title}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-[#cbd5e1] bg-white">
                {sortedData.map((row) => (
                  <tr key={keyExtractor(row)} className="hover:bg-slate-50 transition-colors group">
                    {columns.map((col) => (
                      <td key={`${keyExtractor(row)}-${col.key}`} className={`px-5 py-4 ${col.className || ''}`}>
                        {col.render ? col.render(row) : row[col.key]}
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
            {sortedData.length === 0 && (
              <div className="flex flex-col items-center justify-center p-12 text-center">
                <span className="material-symbols-outlined mb-3 text-4xl text-slate-300">inbox</span>
                <p className="text-[14px] font-bold text-slate-700">No records found</p>
                <p className="mt-1 max-w-sm text-xs text-slate-500">We couldn't find any records matching your current criteria. Try adjusting your filters or search term.</p>
              </div>
            )}
          </div>
        ) : (
          <div className="p-5 bg-slate-50 border-t border-[#cbd5e1]">
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
              {sortedData.map((row) => (
                <div key={keyExtractor(row)} className="border border-[#cbd5e1] rounded-[4px] p-5 bg-white shadow-sm flex flex-col gap-4 hover:shadow-md transition">
                  {columns.map((col) => (
                    <div key={col.key} className={col.title === 'ACTIONS' ? 'mt-auto pt-4 border-t border-slate-100 flex items-center justify-end' : 'flex flex-col gap-1.5'}>
                      {col.title !== 'ACTIONS' && <span className="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest">{col.title}</span>}
                      <div>{col.render ? col.render(row) : row[col.key]}</div>
                    </div>
                  ))}
                </div>
              ))}
            </div>
            {sortedData.length === 0 && (
              <div className="flex flex-col items-center justify-center p-12 text-center">
                <span className="material-symbols-outlined mb-3 text-4xl text-slate-300">inbox</span>
                <p className="text-[14px] font-bold text-slate-700">No records found</p>
                <p className="mt-1 max-w-sm text-xs text-slate-500">We couldn't find any records matching your current criteria. Try adjusting your filters or search term.</p>
              </div>
            )}
          </div>
        )}

        {!hidePagination && (
          <div className="px-6 py-4 bg-[#f8fafc] flex flex-col md:flex-row items-center justify-between gap-4 border-t border-[#cbd5e1]">
            <div className="text-[13px] text-slate-500 text-left">
              Showing <span className="font-bold text-slate-700">{startIndex}–{endIndex}</span> of <span className="font-bold text-slate-700">{totalRecords.toLocaleString()}</span> records
            </div>
            <div className="flex items-center gap-6">
              <div className="flex items-center gap-3">
                <label className="text-[11px] font-bold uppercase tracking-widest text-[#94a3b8]">Rows per page:</label>
                <select value={rowsPerPage} onChange={(e) => onRowsPerPageChange?.(Number(e.target.value))} className="bg-white border border-[#cbd5e1] text-[13px] font-bold text-slate-700 rounded-[2px] px-3 py-1.5 outline-none focus:ring-1 focus:ring-[#0b5384]">
                  {rowsPerPageOptions.map((o) => <option key={o} value={o}>{o}</option>)}
                </select>
              </div>
              {renderPagination()}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
