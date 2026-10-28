import { useEffect, useId, useMemo, useRef, useState } from "react";
import TablePagination from "./TablePagination";

/**
 * Shared list table for every register-style page.
 *
 * Presentation follows the case-register reference: a borderless surface, a
 * quiet neutral header row, comfortable two-line rows separated by hairlines,
 * and restrained 40px controls. Behavior is unchanged — search, advanced
 * filters, column control, server sorting, pagination, selection, loading,
 * empty states, grid mode, and the row context menu all keep their contracts.
 *
 * Density: the default (`variant="default"`) is the full-page register with
 * 76px rows. `variant="embedded"` is the nested/card context (case detail,
 * client detail) and stays compact so it does not overwhelm its container.
 *
 * The public prop API is intentionally unchanged so all 13 consumer pages
 * inherit the appearance without edits.
 */
export function UnifiedTable({
  data = [],
  columns = [],
  keyExtractor,

  title,
  description,

  searchPlaceholder = "Search records...",
  searchValue = "",
  onSearchChange,
  hideSearch = false,

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
  const isEmbedded = variant === "embedded";
  // Unique per table instance: several tables can mount on one page (case and
  // client detail render two), so a fixed id would collide.
  const rowsPerPageId = `unified-table-rows-${useId().replace(/[^a-zA-Z0-9-]/g, '')}`;

  // Popovers close on Escape and on an outside click. The page owns the open
  // state, so closing is reported through the same toggles it already passed;
  // this only asks it to close.
  const filterPopoverRef = useRef(null);
  const columnsPopoverRef = useRef(null);

  useEffect(() => {
    if (!isAdvancedFiltersOpen && !isColumnsControlOpen) return undefined;

    const onKeyDown = (event) => {
      if (event.key !== 'Escape') return;
      if (isAdvancedFiltersOpen) onAdvancedFilters?.();
      else if (isColumnsControlOpen) onColumnsControl?.();
    };

    const onPointerDown = (event) => {
      const inFilters = filterPopoverRef.current?.contains(event.target);
      const inColumns = columnsPopoverRef.current?.contains(event.target);
      if (inFilters || inColumns) return;
      if (isAdvancedFiltersOpen) onAdvancedFilters?.();
      else if (isColumnsControlOpen) onColumnsControl?.();
    };

    document.addEventListener('keydown', onKeyDown);
    document.addEventListener('mousedown', onPointerDown);

    return () => {
      document.removeEventListener('keydown', onKeyDown);
      document.removeEventListener('mousedown', onPointerDown);
    };
  }, [isAdvancedFiltersOpen, isColumnsControlOpen, onAdvancedFilters, onColumnsControl]);

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

  const effectiveColumnKey = columns.find((col) => col.key === effectiveSortKey || col.sortKey === effectiveSortKey)?.key;

  const sortedData = useMemo(() => {
    if (onSortChange) return data; // server-side sort is active
    if (!effectiveSortKey) {
      return data;
    }

    const targetColumn = columns.find((col) => col.key === effectiveSortKey || col.sortKey === effectiveSortKey);
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
      effectiveColumnKey === columnKey && effectiveSortDirection === "asc" ? "desc" : "asc";
    const column = columns.find((col) => col.key === columnKey);

    if (onSortChange) {
      onSortChange(column?.sortKey ?? columnKey, nextDirection);
      return;
    }

    setInternalSortKey(columnKey);
    setInternalSortDirection(nextDirection);
  };

  const hasSortableColumns = columns.some((col) => isColumnSortable(col));

  // Reset compares against the table's declared or inferred default, not the
  // current server sort. A custom sort must remain resettable.
  const baselineSortKey = defaultSortKey ?? inferredDefault.key;
  const baselineSortDirection = defaultSortKey
    ? (defaultSortDirection ?? inferredDefault.direction)
    : inferredDefault.direction;

  // Reset Sort is an undo affordance, so it only exists while there is
  // something to undo: the effective sort must differ from that baseline.
  const isSortResettable =
    !!baselineSortKey &&
    (effectiveSortKey !== baselineSortKey || effectiveSortDirection !== baselineSortDirection);
  const canResetSort = showSortReset && hasSortableColumns && isSortResettable;

  const handleResetSort = () => {
    if (!baselineSortKey) {
      return;
    }

    if (onSortChange) {
      onSortChange(baselineSortKey, baselineSortDirection);
      return;
    }

    setInternalSortKey(baselineSortKey);
    setInternalSortDirection(baselineSortDirection);
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

  const ariaSortFor = (col) => {
    if (!isColumnSortable(col)) return undefined;
    if (effectiveColumnKey !== col.key) return "none";
    return effectiveSortDirection === "asc" ? "ascending" : "descending";
  };

  // Register pages carry up to thirteen columns plus selection and the row
  // action. Below a readable width the wrapper scrolls instead of squeezing
  // cells into unreadable wrapped text, so the page body never scrolls
  // sideways. Embedded tables live inside an already-bounded card and always
  // fit their container.
  const trackMinWidth = (() => {
    if (isEmbedded) return null;
    if (columns.length >= 12) return "min-w-[1360px]";
    if (columns.length >= 9) return "min-w-[1120px]";
    if (columns.length >= 7) return "min-w-[960px]";
    return null;
  })();
  const tableTrackClass = trackMinWidth ? `w-full ${trackMinWidth}` : "w-full";

  const controlBase =
    "inline-flex h-9 items-center justify-center gap-1.5 whitespace-nowrap rounded-md border border-slate-300 bg-white px-2.5 text-[12.5px] font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-1";
  const popoverClass =
    "absolute right-0 top-[calc(100%+6px)] z-50 w-[300px] max-h-[calc(100vh-180px)] overflow-y-auto rounded-md border border-slate-300 bg-white p-3 shadow-lg owb-scroll-wide";

  const rowPaddingClass = isEmbedded ? "px-4 py-2.5" : "px-4 py-4";
  const rowHeightClass = isEmbedded ? "" : "h-[76px]";

  const renderPagination = () => (
    <TablePagination currentPage={currentPage} totalPages={totalPages} onPageChange={onPageChange} />
  );

  const emptyState = (() => {
    const isFiltered = Boolean(searchValue) || (activeFilters && activeFilters.length > 0);

    return (
      <div className="flex flex-col items-center justify-center px-6 py-16 text-center">
        <span aria-hidden="true" className="material-symbols-outlined mb-3 text-[40px] text-slate-300">
          {isFiltered ? "search_off" : "inbox"}
        </span>
        <p className="text-[14px] font-semibold text-slate-700">
          {isFiltered ? "No results found" : (emptyStateMessage || "No records yet")}
        </p>
        <p className="mt-1 max-w-sm text-[12.5px] leading-relaxed text-slate-500">
          {isFiltered
            ? "No records match your current search or filters. Try adjusting your criteria."
            : "There are no records to display here yet. Data will appear once it's available."}
        </p>
      </div>
    );
  })();

  return (
    <div className={isEmbedded ? "w-full space-y-4" : "w-full space-y-6"}>
      {(title || description) && (
        <div className="space-y-1.5">
          {title && <h1 className="text-2xl font-bold tracking-tight text-slate-900">{title}</h1>}
          {description && <p className="max-w-2xl text-sm text-slate-500">{description}</p>}
        </div>
      )}

      <div
        className="w-full"
        aria-busy={isLoading}
      >
        {quickFilters && <div className="mb-3">{quickFilters}</div>}

        {!hideControlBar && (
          <div className="flex flex-col gap-3 pb-3 lg:flex-row lg:items-center lg:justify-between">
            {!hideSearch && (
              <div className="relative w-full lg:w-[418px] lg:shrink-0">
                <span
                  aria-hidden="true"
                  className="material-symbols-outlined pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-[18px] text-slate-400"
                >
                  search
                </span>
                <input
                  type="search"
                  aria-label={searchPlaceholder}
                  placeholder={searchPlaceholder}
                  value={searchValue}
                  onChange={(e) => onSearchChange?.(e.target.value)}
                  className="h-9 w-full rounded-md border border-slate-300 bg-white pl-9 pr-9 text-[13px] text-slate-800 placeholder:text-slate-400 outline-none transition-colors focus:border-primary focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-1"
                />
                {searchValue && onSearchClear && (
                  <button
                    type="button"
                    aria-label="Clear search"
                    onClick={onSearchClear}
                    className="absolute right-1.5 top-1/2 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                  >
                    <span aria-hidden="true" className="material-symbols-outlined text-[18px]">close</span>
                  </button>
                )}
              </div>
            )}

            <div className="flex flex-wrap items-center gap-2 lg:justify-end">
              {onAdvancedFilters && (
                <div className="relative" ref={filterPopoverRef}>
                  <button
                    type="button"
                    onClick={onAdvancedFilters}
                    aria-expanded={isAdvancedFiltersOpen}
                    aria-haspopup="dialog"
                    aria-label={activeFilterCount > 0 ? `Filters, ${activeFilterCount} active` : 'Filters'}
                    className={controlBase}
                  >
                    <span aria-hidden="true" className="material-symbols-outlined text-[17px]">tune</span>
                    Filters
                    {activeFilterCount > 0 && (
                      <span aria-hidden="true" className="ml-0.5 inline-flex h-[17px] min-w-[17px] items-center justify-center rounded-full bg-primary px-1 text-[10px] font-semibold leading-none text-white">
                        {activeFilterCount}
                      </span>
                    )}
                  </button>

                  {isAdvancedFiltersOpen && advancedFiltersContent ? (
                    <div role="dialog" aria-label="Filters" className={popoverClass}>
                      {advancedFiltersContent}
                    </div>
                  ) : null}
                </div>
              )}

              {onColumnsControl && (
                <div className="relative" ref={columnsPopoverRef}>
                  <button
                    type="button"
                    onClick={onColumnsControl}
                    aria-expanded={isColumnsControlOpen}
                    aria-haspopup="dialog"
                    className={controlBase}
                  >
                    <span aria-hidden="true" className="material-symbols-outlined text-[17px]">view_column</span>
                    Columns
                  </button>

                  {isColumnsControlOpen && columnsControlContent ? (
                    <div role="dialog" aria-label="Columns" className={popoverClass}>
                      {columnsControlContent}
                    </div>
                  ) : null}
                </div>
              )}

              {canResetSort && (
                <button type="button" onClick={handleResetSort} className={controlBase}>
                  <span aria-hidden="true" className="material-symbols-outlined text-[18px]">restart_alt</span>
                  Reset Sort
                </button>
              )}

              {onViewModeChange && (
                <div role="group" aria-label="Table layout" className="flex h-9 items-center rounded-md border border-slate-300 bg-white p-0.5">
                  <button
                    type="button"
                    aria-label="List view"
                    aria-pressed={viewMode === 'list'}
                    onClick={() => onViewModeChange('list')}
                    className={`flex h-full w-8 items-center justify-center rounded transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary ${
                      viewMode === 'list' ? 'bg-slate-100 text-primary' : 'text-slate-400 hover:text-slate-600'
                    }`}
                  >
                    <span aria-hidden="true" className="material-symbols-outlined text-[17px]">list</span>
                  </button>
                  <button
                    type="button"
                    aria-label="Grid view"
                    aria-pressed={viewMode === 'grid'}
                    onClick={() => onViewModeChange('grid')}
                    className={`flex h-full w-8 items-center justify-center rounded transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary ${
                      viewMode === 'grid' ? 'bg-slate-100 text-primary' : 'text-slate-400 hover:text-slate-600'
                    }`}
                  >
                    <span aria-hidden="true" className="material-symbols-outlined text-[17px]">grid_view</span>
                  </button>
                </div>
              )}

              {extraActions}
              {onNewRecord && (
                <button
                  type="button"
                  onClick={onNewRecord}
                  className="inline-flex h-9 items-center justify-center gap-2 whitespace-nowrap rounded-md bg-primary px-3.5 text-[12.5px] font-semibold text-white transition-colors hover:bg-primary-container focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                >
                  {newRecordLabel.replace('+ ', '')}
                </button>
              )}
            </div>
          </div>
        )}

        {viewMode === 'list' ? (
          <div className="relative overflow-x-auto owb-scroll-wide">
            <table className={`${tableTrackClass} border-collapse text-left`}>
              <thead>
                <tr className="border-b border-slate-200 bg-slate-50">
                  {selectable && (
                    <th scope="col" className={isEmbedded ? "w-10 px-4 py-2.5" : "w-10 px-4 py-3"}>
                      <input
                        type="checkbox"
                        aria-label="Select all rows"
                        checked={allSelected}
                        onChange={handleToggleAll}
                        className="h-4 w-4 rounded border-slate-300 text-primary focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-1"
                      />
                    </th>
                  )}
                  {columns.map((col) => (
                    <th
                      key={col.key}
                      scope="col"
                      aria-sort={ariaSortFor(col)}
                      className={`whitespace-nowrap px-4 text-[13px] font-semibold text-slate-500 ${isEmbedded ? 'py-2.5' : 'py-3'} ${col.className || ''}`}
                    >
                      {isColumnSortable(col) ? (
                        <button
                          type="button"
                          onClick={() => handleSortToggle(col.key)}
                          className="inline-flex items-center gap-1 rounded transition-colors hover:text-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-1"
                        >
                          <span>{col.title}</span>
                          <span aria-hidden="true" className="material-symbols-outlined text-[15px] leading-none">
                            {effectiveColumnKey === col.key ? (effectiveSortDirection === "asc" ? "arrow_upward" : "arrow_downward") : "unfold_more"}
                          </span>
                        </button>
                      ) : (
                        col.title
                      )}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200 bg-white">
                {sortedData.map((row) => (
                  <tr
                    key={keyExtractor(row)}
                    className={`align-top transition-colors hover:bg-slate-50/70 ${rowHeightClass} ${onRowContextMenu ? 'cursor-context-menu' : ''} ${isLoading ? 'opacity-30 pointer-events-none' : ''}`}
                    onContextMenu={onRowContextMenu ? (e) => onRowContextMenu(e, row) : undefined}
                  >
                    {selectable && (
                      <td className={rowPaddingClass}>
                        <input
                          type="checkbox"
                          aria-label={`Select row ${keyExtractor(row)}`}
                          checked={selectedKeys.includes(keyExtractor(row))}
                          onChange={() => handleToggleRow(keyExtractor(row))}
                          className="h-4 w-4 rounded border-slate-300 text-primary focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-1"
                        />
                      </td>
                    )}
                    {columns.map((col) => (
                      <td key={`${keyExtractor(row)}-${col.key}`} className={`${rowPaddingClass} text-[13.5px] text-slate-800 ${col.className || ''}`}>
                        {col.render ? col.render(row) : row[col.key]}
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>

            {sortedData.length === 0 && !isLoading && emptyState}

            {isLoading && (
              <div
                role="status"
                aria-live="polite"
                aria-label="Loading updated records"
                className="absolute inset-0 z-10 flex flex-col gap-3 bg-white/80 p-5"
              >
                <span className="sr-only">Loading updated records…</span>
                {[0, 1, 2, 3, 4].map((row) => (
                  <div key={row} className="flex animate-pulse items-center gap-5 border-b border-slate-100 py-4 last:border-b-0">
                    {selectable && <div className="h-4 w-4 shrink-0 rounded bg-slate-200" />}
                    {columns.map((col, ci) => {
                      const widths = ['110px', '150px', '90px', '130px', '70px', '170px', '100px'];
                      const w = col.key === 'actions' || String(col.title).toUpperCase() === 'ACTIONS' ? '50px' : widths[ci % widths.length];
                      return <div key={col.key} className="h-4 shrink-0 rounded bg-slate-200" style={{ width: w }} />;
                    })}
                  </div>
                ))}
              </div>
            )}
          </div>
        ) : (
          <div className="pt-4">
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
              {sortedData.map((row) => (
                <div key={keyExtractor(row)} className="flex flex-col gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm transition hover:border-slate-300 hover:shadow-md">
                  {columns.map((col) => (
                    <div
                      key={col.key}
                      className={
                        String(col.title).toUpperCase() === 'ACTIONS'
                          ? "mt-auto flex items-center justify-end border-t border-slate-100 pt-3"
                          : "flex flex-col gap-1"
                      }
                    >
                      {String(col.title).toUpperCase() !== 'ACTIONS' && (
                        <span className="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-400">{col.title}</span>
                      )}
                      <div>{col.render ? col.render(row) : row[col.key]}</div>
                    </div>
                  ))}
                </div>
              ))}
            </div>
            {sortedData.length === 0 && emptyState}
          </div>
        )}

        {!hidePagination && (
          <div className="flex flex-col items-center justify-between gap-4 border-t border-slate-200 pt-3 md:flex-row">
            <div className="text-[13px] text-slate-500">
              Showing <span className="font-semibold text-slate-700">{startIndex}–{endIndex}</span> of{" "}
              <span className="font-semibold text-slate-700">{totalRecords.toLocaleString()}</span> records
            </div>

            <div className="flex flex-wrap items-center justify-end gap-4">
              <div className="flex items-center gap-2">
                <label htmlFor={rowsPerPageId} className="text-[12.5px] text-slate-500">
                  Rows per page
                </label>
                <select
                  id={rowsPerPageId}
                  aria-label="Rows per page"
                  value={rowsPerPage}
                  onChange={(e) => onRowsPerPageChange?.(Number(e.target.value))}
                  className="h-9 rounded-md border border-slate-300 bg-white pl-2.5 pr-7 text-[13px] font-medium text-slate-700 outline-none transition-colors focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-1"
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
