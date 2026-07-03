import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState, useMemo, useRef, useEffect, useCallback } from 'react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import { useToast } from '@/Hooks/useToast';
import { formatDisplayDate } from '@/lib/utils';

const COLUMN_DEFS = [
  { key: 'name', label: 'Name', default: true },
  { key: 'sex', label: 'Sex', default: true },
  { key: 'date_of_birth', label: 'Date of Birth', default: true },
  { key: 'case_number', label: 'Case #', default: true },
  { key: 'referrals', label: 'Referrals', default: false },
  { key: 'id', label: 'Actions', default: true },
];

export default function ClientIndex({ clients, filters }) {
  const [searchValue, setSearchValue] = useState(filters?.search ?? '');
  const [viewMode, setViewMode] = useState('list');
  const [filterOpen, setFilterOpen] = useState(false);
  const [columnsOpen, setColumnsOpen] = useState(false);

  const searchTimeout = useRef(null);

  const [visibleColumns, setVisibleColumns] = useState(
    COLUMN_DEFS.filter((c) => c.default).map((c) => c.key),
  );

  const [typeFilter, setTypeFilter] = useState('');
  const [sexFilter, setSexFilter] = useState('');

  const toast = useToast();
  const [isExporting, setIsExporting] = useState(false);

  const handleExport = useCallback(() => {
    const params = new URLSearchParams();
    if (filters.search) params.set('search', filters.search);
    if (sexFilter) params.set('sex', sexFilter);
    if (typeFilter) params.set('client_type', typeFilter);

    const qs = params.toString();
    const url = route('clients.export-excel') + (qs ? '?' + qs : '');

    setIsExporting(true);
    toast.info('Preparing your export…');

    // Trigger download without opening a new tab
    const link = document.createElement('a');
    link.href = url;
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    // Reset after a reasonable timeout
    setTimeout(() => setIsExporting(false), 5000);
  }, [filters, sexFilter, typeFilter, toast]);

  useEffect(() => {
    return () => clearTimeout(searchTimeout.current);
  }, []);

  const navigateWith = (params) => {
    const url = new URL(window.location);
    Object.entries(params).forEach(([k, v]) => {
      if (v) url.searchParams.set(k, v);
      else url.searchParams.delete(k);
    });
    url.searchParams.delete('page');
    router.get(url.toString(), {}, { preserveState: true, replace: true });
  };

  const handleSearchChange = (value) => {
    setSearchValue(value);
    clearTimeout(searchTimeout.current);
    searchTimeout.current = setTimeout(() => {
      navigateWith({ search: value || undefined });
    }, 400);
  };

  const activeFilters = useMemo(() => {
    const chips = [];
    if (typeFilter) chips.push({ key: 'client_type', label: 'Client Type', value: typeFilter === 'OFW' ? 'OFW' : 'NOK' });
    if (sexFilter) chips.push({ key: 'sex', label: 'Sex', value: sexFilter });
    return chips;
  }, [typeFilter, sexFilter]);

  const handleRemoveFilter = (filter) => {
    if (filter.key === 'client_type') { setTypeFilter(''); navigateWith({ client_type: undefined }); }
    if (filter.key === 'sex') { setSexFilter(''); navigateWith({ sex: undefined }); }
  };

  const handleClearFilters = () => {
    setTypeFilter('');
    setSexFilter('');
    navigateWith({ client_type: undefined, sex: undefined });
  };

  function paginatorProps(paginator) {
    return {
      totalRecords: paginator.total,
      startIndex: paginator.from,
      endIndex: paginator.to,
      currentPage: paginator.current_page,
      totalPages: paginator.last_page,
      rowsPerPage: paginator.per_page,
      onPageChange: (page) => {
        const url = new URL(window.location);
        url.searchParams.set('page', page);
        router.get(url.toString(), {}, { preserveState: true, preserveScroll: true, only: ['clients'] });
      },
      onRowsPerPageChange: (n) => {
        const url = new URL(window.location);
        url.searchParams.set('per_page', n);
        url.searchParams.delete('page');
        router.get(url.toString(), {}, { preserveState: true, preserveScroll: true, only: ['clients'] });
      },
    };
  }

  const columns = useMemo(() =>
    COLUMN_DEFS
      .filter((col) => visibleColumns.includes(col.key))
      .map((col) => {
        const base = { key: col.key, title: col.label, sortable: true };
        switch (col.key) {
          case 'name':
            return {
              ...base,
              render: (row) =>
                [row.first_name, row.middle_name, row.last_name, row.suffix].filter(Boolean).join(' '),
              sortAccessor: (row) => `${row.last_name}, ${row.first_name}`,
            };
          case 'sex':
            return {
              ...base,
              render: (row) => row.sex || 'N/A',
            };
          case 'date_of_birth':
            return {
              ...base,
              render: (row) =>
                row.date_of_birth ? formatDisplayDate(row.date_of_birth) : 'N/A',
            };
          case 'case_number':
            return {
              ...base,
              sortable: true,
              render: (row) =>
                row.case_file ? (
                  <Link href={route('cases.show', row.case_file.id)} className="text-indigo-600 hover:text-indigo-900">
                    {row.case_file.case_number}
                  </Link>
                ) : 'N/A',
            };
          case 'referrals':
            return {
              ...base,
              sortable: false,
              render: (row) => row.case_file?.referrals?.length ?? 0,
            };
          case 'id':
            return {
              ...base,
              sortable: false,
              title: 'Actions',
              render: (row) => (
                <button onClick={() => router.visit(route('clients.show', row.id))} className="min-h-[28px] px-2.5 bg-blue-900 text-white hover:bg-blue-800 text-[11px] font-bold rounded-[3px] transition-colors border border-blue-900">
                  View Details
                </button>
              ),
            };
          default:
            return { ...base, render: (row) => row[col.key] };
        }
      }),
  [visibleColumns]);

  const advancedFilterContent = useMemo(() => (
    <div className="space-y-4">
      <div>
        <label className="block text-[11px] font-bold uppercase tracking-widest text-slate-500 mb-1.5">Client Type</label>
        <select
          value={typeFilter}
          onChange={(e) => {
            const val = e.target.value;
            setTypeFilter(val);
            setFilterOpen(false);
            navigateWith({ client_type: val || undefined });
          }}
          className="w-full border border-slate-300 rounded-[2px] px-3 py-2 text-[13px] font-medium text-slate-700 outline-none focus:ring-1 focus:ring-blue-900"
        >
          <option value="">All Types</option>
          <option value="OFW">OFW</option>
          <option value="NOK">Next of Kin</option>
        </select>
      </div>
      <div>
        <label className="block text-[11px] font-bold uppercase tracking-widest text-slate-500 mb-1.5">Sex</label>
        <select
          value={sexFilter}
          onChange={(e) => {
            const val = e.target.value;
            setSexFilter(val);
            setFilterOpen(false);
            navigateWith({ sex: val || undefined });
          }}
          className="w-full border border-slate-300 rounded-[2px] px-3 py-2 text-[13px] font-medium text-slate-700 outline-none focus:ring-1 focus:ring-blue-900"
        >
          <option value="">All</option>
          <option value="MALE">Male</option>
          <option value="FEMALE">Female</option>
        </select>
      </div>
    </div>
  ), [typeFilter, sexFilter]);

  const columnControlContent = useMemo(() => (
    <div className="space-y-2">
      {COLUMN_DEFS.map((col) => (
        <label
          key={col.key}
          className="flex items-center gap-2.5 text-[13px] text-slate-700 cursor-pointer select-none hover:text-slate-900 transition-colors"
        >
          <input
            type="checkbox"
            checked={visibleColumns.includes(col.key)}
            onChange={() => {
              setVisibleColumns((prev) =>
                prev.includes(col.key)
                  ? prev.filter((k) => k !== col.key)
                  : [...prev, col.key],
              );
            }}
            className="rounded border-slate-300 text-blue-900 focus:ring-blue-900 focus:ring-offset-0"
          />
          {col.label}
        </label>
      ))}
    </div>
  ), [visibleColumns]);

  return (
    <AppLayout title="Clients">
      <Head title="Clients" />
      <div data-tour="clients-header" className="mb-8 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Clients</h1>
          <p className="text-sm text-slate-500 mt-1">View all registered clients and their associated cases.</p>
        </div>
        <button
          type="button"
          onClick={handleExport}
          disabled={isExporting}
          className="inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-bold text-white bg-[#0b5384] rounded-md hover:bg-[#09416a] transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
        >
          <span className="material-symbols-outlined text-[18px]">{isExporting ? 'sync' : 'download'}</span>
          {isExporting ? 'Exporting…' : 'Export Excel'}
        </button>
      </div>

      <div data-tour="clients-table">
      <UnifiedTable
        columns={columns}
        data={clients.data}
        keyExtractor={(row) => row.id}
        {...paginatorProps(clients)}
        searchValue={searchValue}
        searchPlaceholder="Search clients..."
        onSearchChange={handleSearchChange}
        onAdvancedFilters={() => setFilterOpen((v) => { setColumnsOpen(false); return !v; })}
        isAdvancedFiltersOpen={filterOpen}
        advancedFiltersContent={advancedFilterContent}
        onColumnsControl={() => setColumnsOpen((v) => { setFilterOpen(false); return !v; })}
        isColumnsControlOpen={columnsOpen}
        columnsControlContent={columnControlContent}
        onViewModeChange={setViewMode}
        viewMode={viewMode}
        activeFilters={activeFilters}
        onRemoveFilter={handleRemoveFilter}
        onClearFilters={handleClearFilters}
      />
      </div>
    </AppLayout>
  );
}
