import { useEffect, useMemo, useState } from "react";

export function UnifiedTable({
  data = [],
  columns = [],
  keyExtractor,

  title,
  description,

  searchPlaceholder = "Search records...",
  searchValue = "",
  onSearchChange,

  onAdvancedFilters,
  isAdvancedFiltersOpen = false,
  advancedFiltersContent,
  onColumnsControl,
  isColumnsControlOpen = false,
  columnsControlContent,
  onNewRecord,
  newRecordLabel = "+ New Record",
  extraActions,

  viewMode = "list",
  onViewModeChange,

  activeFilters = [],
  onRemoveFilter,
  onClearFilters,
  quickFilters,
  activeFilterCount = 0,
  onSearchClear,
  emptyStateMessage,

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
  variant = "default",

  selectable = false,
  selectedKeys = [],
  onSelectionChange,

  isLoading = false,
  onRowContextMenu,
}) {
  const ENABLE_ROW_CONTEXT_MENU = true;
  const isColumnSortable = (col) => {
    if (!col || typeof col !== 'object') return false;
    return col.sortable ?? (col.key?.toLowerCase() !== "actions" && col.title?.toUpperCase() !== "ACTIONS");
  }

  const inferDirectionForColumn = (col) => {
    const signature = `${col.key} ${col.title}`.toLowerCase();
    if (/(date|time|age|timestamp|created|updated)/.test(signature)) {
      return "desc";
    }

    return "asc";
  };

  const inferredDefault = useMemo(() => {
    const sortableColumns = columns.filter((col) => isColumnSortable(col));
    if (sortableColumns.length === 0) {
      return { key: undefined, direction: "asc" };
    }

    if (defaultSortKey) {
      const matched = sortableColumns.find((col) => col.key === defaultSortKey);
      return {
        key: matched?.key,
        direction: defaultSortDirection ?? (matched ? inferDirectionForColumn(matched) : "asc"),
      };
    }

    const preferred =
      sortableColumns.find((col) => /(created|updated).*?(at|date)|timestamp|age/i.test(col.key)) ||
      sortableColumns.find((col) => /(date|time|age|created|updated)/i.test(col.title));

    if (preferred) {
      return { key: preferred.key, direction: defaultSortDirection ?? inferDirectionForColumn(preferred) };
    }

    return {
      key: sortableColumns[0].key,
      direction: defaultSortDirection ?? "asc",
    };
  }, [columns, defaultSortDirection, defaultSortKey]);

  const initialSortKey = sortKey ?? inferredDefault?.key;
  const initialSortDir = sortDirection ?? inferredDefault?.direction;
  const [internalSortKey, setInternalSortKey] = useState(initialSortKey);
  const [internalSortDirection, setInternalSortDirection] = useState(initialSortDir);

  useEffect(() => {
    if (sortKey !== undefined) {
      return;
    }

    const hasCurrentSort =
      !!internalSortKey && columns.some((col) => col.key === internalSortKey && isColumnSortable(col));

    if (!hasCurrentSort) {
      setInternalSortKey(inferredDefault.key);
      setInternalSortDirection(inferredDefault.direction);
    }
  }, [columns, inferredDefault.key, inferredDefault.direction, internalSortKey, sortKey]);

  const effectiveSortKey = sortKey ?? internalSortKey ?? inferredDefault.key;
  const effectiveSortDirection =
    sortKey !== undefined
      ? sortDirection ?? inferredDefault.direction
      : internalSortDirection;

  const resolveComparableValue = (value) => {
    if (value === null || value === undefined) {
      return "";
    }

    if (value instanceof Date) {
      return value.getTime();
    }

    if (typeof value === "number") {
      return Number.isFinite(value) ? value : 0;
    }

    if (typeof value === "boolean") {
      return value ? 1 : 0;
    }

    const text = String(value);
    const maybeDate = Date.parse(text);
    if (!Number.isNaN(maybeDate)) {
      return maybeDate;
    }

    return text;
  };

  const sortedData = useMemo(() => {
    if (onSortChange) return data; // server-side sort is active
    if (!effectiveSortKey) {
      return data;
    }

    const targetColumn = columns.find((col) => col.key === effectiveSortKey);
    if (!targetColumn || !isColumnSortable(targetColumn)) {
      return data;
    }

    const directionMultiplier = effectiveSortDirection === "asc" ? 1 : -1;

    return [...data].sort((a, b) => {
      const rawA = targetColumn.sortAccessor ? targetColumn.sortAccessor(a) : a[targetColumn.key];
      const rawB = targetColumn.sortAccessor ? targetColumn.sortAccessor(b) : b[targetColumn.key];
      const valueA = resolveComparableValue(rawA);
      const valueB = resolveComparableValue(rawB);

      if (typeof valueA === "number" && typeof valueB === "number") {
        return (valueA - valueB) * directionMultiplier;
      }

      return String(valueA).localeCompare(String(valueB), undefined, { numeric: true, sensitivity: "base" }) * directionMultiplier;
    });
  }, [columns, data, effectiveSortDirection, effectiveSortKey, onSortChange]);

  const handleSortToggle = (columnKey) => {
    const nextDirection =
      effectiveSortKey === columnKey && effectiveSortDirection === "asc" ? "desc" : "asc";

    if (onSortChange) {
      onSortChange(columnKey, nextDirection);
      return;
    }

    setInternalSortKey(columnKey);
    setInternalSortDirection(nextDirection);
  };

  const hasSortableColumns = columns.some((col) => isColumnSortable(col));
  const canResetSort =
    !!inferredDefault.key &&
    (effectiveSortKey !== inferredDefault.key || effectiveSortDirection !== inferredDefault.direction);

  const handleResetSort = () => {
    if (!inferredDefault.key) {
      return;
    }

    if (onSortChange) {
      onSortChange(inferredDefault.key, inferredDefault.direction);
      return;
    }

    setInternalSortKey(inferredDefault.key);
    setInternalSortDirection(inferredDefault.direction);
  };

  const allSelected = selectable && sortedData.length > 0 && selectedKeys.length === sortedData.length;

  const handleToggleAll = () => {
    if (!onSelectionChange) return
    if (allSelected) {
      onSelectionChange([])
    } else {
      onSelectionChange(sortedData.map(row => keyExtractor(row)))
    }
  }

  const handleToggleRow = (key) => {
    if (!onSelectionChange) return
    if (selectedKeys.includes(key)) {
      onSelectionChange(selectedKeys.filter(k => k !== key))
    } else {
      onSelectionChange([...selectedKeys, key])
    }
  }

  const renderPagination = () => {
    const pages = [];
    const maxVisible = 5;
    let start = Math.max(1, currentPage - Math.floor(maxVisible / 2));
    let end = Math.min(totalPages, start + maxVisible - 1);
    if (end - start + 1 < maxVisible) start = Math.max(1, end - maxVisible + 1);
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
  };

  return (
    <div className="space-y-8 w-full">
      {(title || description) && (
        <div className="space-y-2">
          {title && <h1 className="text-2xl font-bold tracking-tight text-slate-900">{title}</h1>}
          {description && <p className="text-slate-500 max-w-2xl">{description}</p>}
        </div>
      )}

      <div className={variant === "embedded" ? "bg-white overflow-hidden w-full" : "bg-white border border-slate-300 overflow-hidden w-full rounded-md shadow-sm"} aria-busy={isLoading}>
        
        {!hideControlBar && (
        <div className="p-4 bg-slate-50 flex flex-col lg:flex-row items-center justify-between gap-4 border-b border-slate-300 min-h-[72px]">
          <div className="relative flex-1 w-full max-w-md h-[40px]">
            <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[20px]">
              search
            </span>
            <input
              type="text"
              placeholder={searchPlaceholder}
              value={searchValue}
              onChange={(e) => onSearchChange?.(e.target.value)}
              className="w-full h-full pl-10 pr-10 bg-white border border-slate-300 rounded-[2px] text-[14px] text-slate-600 placeholder-slate-400 outline-none focus:ring-1 focus:ring-blue-900 transition"
            />
            {searchValue && onSearchClear && (
              <button
                type="button"
                onClick={onSearchClear}
                className="absolute right-3 top-1/2 -translate-y-1/2 flex items-center justify-center text-slate-400 hover:text-slate-600 transition-colors"
              >
                <span className="material-symbols-outlined text-[18px]">close</span>
              </button>
            )}
          </div>
          
          <div className="flex w-full flex-wrap items-center gap-3 pb-2 lg:w-auto lg:flex-nowrap lg:pb-0">
            {onAdvancedFilters && (
              <div className="relative shrink-0">
                <button 
                  onClick={onAdvancedFilters}
                  className="h-[40px] px-4 border border-slate-300 text-[14px] font-bold text-slate-600 rounded-[2px] bg-white flex items-center justify-center gap-2 hover:bg-slate-50 transition-colors whitespace-nowrap relative"
                >
                  <span className="material-symbols-outlined text-[18px]">tune</span> Filters{activeFilterCount > 0 && (
                    <span className="ml-1.5 bg-blue-900 text-white text-[10px] font-bold min-w-[18px] h-[18px] px-1 rounded-full inline-flex items-center justify-center leading-none">
                      {activeFilterCount}
                    </span>
                  )}
                </button>

                {isAdvancedFiltersOpen && advancedFiltersContent ? (
                  <div className="absolute right-0 top-[calc(100%+8px)] z-50 w-72 max-h-[calc(100vh-180px)] overflow-y-auto rounded-[3px] border border-slate-300 bg-white p-5 shadow-lg">
                    {advancedFiltersContent}
                  </div>
                ) : null}
              </div>
            )}
            
            {onColumnsControl && (
              <div className="relative shrink-0">
                <button 
                  onClick={onColumnsControl}
                  className="h-[40px] px-4 border border-slate-300 text-[14px] font-bold text-slate-600 rounded-[2px] bg-white flex items-center justify-center gap-2 hover:bg-slate-50 transition-colors whitespace-nowrap"
                >
                  <span className="material-symbols-outlined text-[18px]">view_column</span> Columns
                </button>

                {isColumnsControlOpen && columnsControlContent ? (
                  <div className="absolute right-0 top-[calc(100%+8px)] z-50 w-72 max-h-[calc(100vh-180px)] overflow-y-auto rounded-[3px] border border-slate-300 bg-white p-5 shadow-lg">
                    {columnsControlContent}
                  </div>
                ) : null}
              </div>
            )}

            {showSortReset && hasSortableColumns && canResetSort && (
              <button
                type="button"
                onClick={handleResetSort}
                className="h-[40px] px-4 border border-slate-300 text-[14px] font-bold text-slate-600 rounded-[2px] bg-white flex items-center justify-center gap-2 hover:bg-slate-50 transition-colors whitespace-nowrap"
              >
                <span className="material-symbols-outlined text-[18px]">restart_alt</span> Reset Sort
              </button>
            )}
            
            {onViewModeChange && (
              <div className="flex items-center bg-slate-100 rounded-[2px] p-1 border border-slate-300 h-[40px] shrink-0">
                <button 
                  onClick={() => onViewModeChange('list')}
                  className={`h-full w-8 flex items-center justify-center rounded-[2px] ${viewMode === 'list' ? 'bg-white shadow-sm border border-slate-300 text-blue-900' : 'text-slate-400 hover:text-slate-600'}`}
                >
                  <span className="material-symbols-outlined text-[18px]">list</span>
                </button>
                <button 
                  onClick={() => onViewModeChange('grid')}
                  className={`h-full w-8 flex items-center justify-center rounded-[2px] ${viewMode === 'grid' ? 'bg-white shadow-sm border border-slate-300 text-blue-900' : 'text-slate-400 hover:text-slate-600'}`}
                >
                  <span className="material-symbols-outlined text-[18px]">grid_view</span>
                </button>
              </div>
            )}
            
            {extraActions}
            {onNewRecord && (
              <button 
                onClick={onNewRecord}
                className="h-[40px] px-5 bg-blue-900 text-white text-[14px] font-bold rounded-[3px] flex items-center gap-2 hover:bg-blue-800 transition-colors ml-2 shadow-sm whitespace-nowrap shrink-0"
              >
                <span className="font-semibold text-[16px]">+</span> {newRecordLabel.replace('+ ', '')}
              </button>
            )}
          </div>
        </div>
        )}

        {activeFilters && activeFilters.length > 0 && (
          <div className="px-5 py-[14px] flex items-center flex-wrap gap-4 border-b border-slate-300 bg-white text-[12px]">
            <span className="font-bold uppercase tracking-widest text-slate-400">Active Filters:</span>
            {activeFilters.map((filter, index) => (
              <div 
                key={`${filter.key}-${index}`} 
                className="flex items-center gap-1.5 bg-[#f0f7fc] text-blue-900 px-3 py-1 rounded-[2px] font-bold border border-[#d2e5f3]"
              >
                {filter.label}: {filter.value}
                <button 
                  onClick={() => onRemoveFilter?.(filter)}
                  className="flex items-center justify-center hover:opacity-75 transition-opacity"
                >
                  <span className="material-symbols-outlined text-[15px]">close</span>
                </button>
              </div>
            ))}
            {onClearFilters && (
              <button 
                onClick={onClearFilters}
                className="font-bold text-blue-900 hover:underline text-[13px]"
              >
                Clear All
              </button>
            )}
          </div>
        )}

        {quickFilters && (
          <div className="px-5 py-3 border-b border-slate-300 bg-white flex items-center gap-2 flex-wrap">
            {quickFilters}
          </div>
        )}

        {viewMode === 'list' ? (
          <div className="overflow-x-auto relative">
            <table className={`w-full text-left border-collapse ${isLoading ? 'opacity-30 pointer-events-none' : ''}`}>
              <thead>
                <tr className="bg-slate-50 border-b border-slate-300">
                  {selectable && (
                    <th className="px-5 py-4 w-10">
                      <input
                        type="checkbox"
                        checked={allSelected}
                        onChange={handleToggleAll}
                        className="rounded border-slate-300"
                      />
                    </th>
                  )}
                  {columns.map((col) => (
                    <th 
                      key={col.key} 
                      className={`px-5 py-4 text-[12px] font-extrabold uppercase tracking-widest text-slate-500 whitespace-nowrap ${col.className || ''}`}
                    >
                      {isColumnSortable(col) ? (
                        <button
                          type="button"
                          onClick={() => handleSortToggle(col.key)}
                          className="inline-flex items-center gap-1 hover:text-blue-900 transition-colors"
                        >
                          <span>{col.title}</span>
                          <span className="material-symbols-outlined text-[15px] leading-none">
                            {effectiveSortKey === col.key ? (effectiveSortDirection === "asc" ? "arrow_upward" : "arrow_downward") : "unfold_more"}
                          </span>
                        </button>
                      ) : (
                        col.title
                      )}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-300 bg-white">
                {sortedData.map((row) => (
                  <tr
                    key={keyExtractor(row)}
                    className={`hover:bg-slate-100 transition-colors group ${ENABLE_ROW_CONTEXT_MENU && onRowContextMenu ? 'cursor-context-menu' : ''}`}
                    onContextMenu={ENABLE_ROW_CONTEXT_MENU && onRowContextMenu ? (e) => onRowContextMenu(e, row) : undefined}
                  >
                    {selectable && (
                      <td className="px-5 py-4">
                        <input
                          type="checkbox"
                          checked={selectedKeys.includes(keyExtractor(row))}
                          onChange={() => handleToggleRow(keyExtractor(row))}
                          className="rounded border-slate-300"
                        />
                      </td>
                    )}
                    {columns.map((col) => (
                      <td key={`${keyExtractor(row)}-${col.key}`} className={`px-5 py-4 ${col.className || ''}`}>
                        {col.render ? col.render(row) : row[col.key]}
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
            {sortedData.length === 0 && !isLoading && (
               <div className="flex flex-col items-center justify-center p-12 text-center">
                 {searchValue || activeFilters.length > 0 ? (
                   <>
                     <span className="material-symbols-outlined mb-3 text-4xl text-slate-300">search_off</span>
                     <p className="text-[14px] font-bold text-slate-700">No results found</p>
                     <p className="mt-1 max-w-sm text-xs text-slate-500">No records match your current search or filters. Try adjusting your criteria.</p>
                   </>
                 ) : (
                   <>
                     <span className="material-symbols-outlined mb-3 text-4xl text-slate-300">inbox</span>
                     <p className="text-[14px] font-bold text-slate-700">{emptyStateMessage || 'No records yet'}</p>
                     <p className="mt-1 max-w-sm text-xs text-slate-500">There are no records to display here yet. Data will appear once it's available.</p>
                   </>
                 )}
               </div>
            )}
            {isLoading && (
              <div className="absolute inset-0 bg-white/80 z-10 flex flex-col gap-3 p-5">
                {[0, 1, 2, 3, 4].map((row) => (
                  <div key={row} className="flex items-center gap-5 animate-pulse py-[18px] border-b border-slate-100 last:border-b-0">
                    {selectable && (
                      <div className="h-4 w-4 bg-slate-200 rounded shrink-0" />
                    )}
                    {columns.map((col, ci) => {
                      const widths = ['110px', '150px', '90px', '130px', '70px', '170px', '100px'];
                      const w = col.key === 'actions' || col.title.toUpperCase() === 'ACTIONS' ? '50px' : widths[ci % widths.length];
                      return (
                        <div key={col.key} className="h-4 bg-slate-200 rounded shrink-0" style={{ width: w }} />
                      );
                    })}
                  </div>
                ))}
              </div>
            )}
          </div>
        ) : (
          <div className="p-5 bg-slate-50 border-t border-slate-300">
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
              {sortedData.map((row) => (
                <div key={keyExtractor(row)} className="border border-slate-300 rounded-[4px] p-5 bg-white shadow-sm flex flex-col gap-4 hover:shadow-md transition">
                  {columns.map((col) => (
                    <div 
                      key={col.key} 
                      className={
                        col.title === 'ACTIONS' 
                          ? "mt-auto pt-4 border-t border-slate-100 flex items-center justify-end" 
                          : "flex flex-col gap-1.5"
                      }
                    >
                      {col.title !== 'ACTIONS' && (
                        <span className="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest">{col.title}</span>
                      )}
                      <div>
                        {col.render ? col.render(row) : row[col.key]}
                      </div>
                    </div>
                  ))}
                </div>
              ))}
            </div>
            {sortedData.length === 0 && (
               <div className="flex flex-col items-center justify-center p-12 text-center">
                 {searchValue || activeFilters.length > 0 ? (
                   <>
                     <span className="material-symbols-outlined mb-3 text-4xl text-slate-300">search_off</span>
                     <p className="text-[14px] font-bold text-slate-700">No results found</p>
                     <p className="mt-1 max-w-sm text-xs text-slate-500">No records match your current search or filters. Try adjusting your criteria.</p>
                   </>
                 ) : (
                   <>
                     <span className="material-symbols-outlined mb-3 text-4xl text-slate-300">inbox</span>
                     <p className="text-[14px] font-bold text-slate-700">{emptyStateMessage || 'No records yet'}</p>
                     <p className="mt-1 max-w-sm text-xs text-slate-500">There are no records to display here yet. Data will appear once it's available.</p>
                   </>
                 )}
               </div>
            )}
          </div>
        )}

        {!hidePagination && (
        <div className="px-6 py-4 bg-slate-50 flex flex-col md:flex-row items-center justify-between gap-4 border-t border-slate-300">
          <div className="text-[13px] text-slate-500 text-left">
            Showing <span className="font-bold text-slate-700">{startIndex}–{endIndex}</span> of <span className="font-bold text-slate-700">{totalRecords.toLocaleString()}</span> records
          </div>
          
          <div className="flex items-center gap-6">
            <div className="flex items-center gap-3">
              <label className="text-[11px] font-bold uppercase tracking-widest text-slate-400">Rows per page:</label>
              <select 
                value={rowsPerPage} 
                onChange={(e) => onRowsPerPageChange?.(Number(e.target.value))}
                className="bg-white border border-slate-300 text-[13px] font-bold text-slate-700 rounded-[2px] pl-3 pr-7 py-1.5 outline-none focus:ring-1 focus:ring-blue-900"
              >
                {rowsPerPageOptions.map(opt => (
                  <option key={opt} value={opt}>{opt}</option>
                ))}
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
