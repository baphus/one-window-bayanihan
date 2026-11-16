import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { useState, useMemo, useRef, useEffect, useCallback } from 'react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import KpiCard from '@/Components/ui/KpiCard';
import Select from '@/Components/Select';
import useTableVisitLoading from '@/Hooks/useTableVisitLoading';
import TableTabs from '@/Components/ui/TableTabs';
import FilterPanel from '@/Components/ui/FilterPanel';
import { usePersistedViewMode, usePersistedFilters } from '@/Hooks/usePersistedViewState';
import usePersistedColumns from '@/Hooks/usePersistedColumns';
import { toFilterList, toggleFilterValue } from '@/Hooks/useArrayFilters';
import { useToast } from '@/Hooks/useToast';

const VULNERABILITIES = ['PWD', 'Senior Citizen', 'Solo Parent', 'Indigenous Person'];

const COLUMN_DEFS = [
  { key: 'name', label: 'Client', sortable: true, default: true },
  { key: 'contact', label: 'Contact', sortable: false, default: true },
  { key: 'location', label: 'Location', sortable: false, default: true },
  { key: 'employment', label: 'Employment', sortable: false, default: true },
  { key: 'cases', label: 'Cases / Latest case date', sortable: true, sortKey: 'latest_case_at', default: true },
  { key: 'vulnerability_indicators', label: 'Vulnerability indicators', sortable: false, default: true },
  { key: 'actions', label: 'Actions', sortable: false, default: true },
];

export default function ClientsIndex({ clients, kpis, filters: rawFilters }) {
  const filters = rawFilters && !Array.isArray(rawFilters) ? rawFilters : {};
  const [searchValue, setSearchValue] = useState(filters?.search ?? '');
  const searchTimeout = useRef(null);
  const filtersRef = useRef(filters);
  filtersRef.current = filters;
  const pendingParamsRef = useRef(null);
  const [filterOpen, setFilterOpen] = useState(false);
  const [columnsOpen, setColumnsOpen] = useState(false);
  const [viewMode, setViewMode] = usePersistedViewMode('owb-view_clients');
  const [visibleColumns, setVisibleColumns] = usePersistedColumns('clients', COLUMN_DEFS.map((col) => col.key));
  const toast = useToast();
  const { isLoading: tableLoading, withLoading } = useTableVisitLoading();

  useEffect(() => {
    return () => clearTimeout(searchTimeout.current);
  }, []);

  const updateTable = (params) => {
    const clean = Object.fromEntries(
      Object.entries(params).filter(([_, v]) => v !== null && v !== undefined && v !== '' && (!Array.isArray(v) || v.length))
    );
    if (searchTimeout.current) pendingParamsRef.current = clean;
    router.get(route('clients.index'), clean, withLoading({
      preserveState: true,
      preserveScroll: true,
      replace: true,
      only: ['clients', 'filters', 'kpis'],
      onError: () => {},
    }));
  };

  usePersistedFilters('owb-filters_clients', filters, (saved) => {
    setSearchValue(saved.search ?? '');
    updateTable(saved);
  });

  const handleSearchChange = (value) => {
    setSearchValue(value);
    clearTimeout(searchTimeout.current);
    searchTimeout.current = setTimeout(() => {
      const base = pendingParamsRef.current ?? filtersRef.current;
      pendingParamsRef.current = null;
      updateTable({ ...base, search: value || undefined, page: undefined });
      searchTimeout.current = null;
    }, 400);
  };

  const handleSearchClear = () => {
    setSearchValue('');
    clearTimeout(searchTimeout.current);
    searchTimeout.current = null;
    pendingParamsRef.current = null;
    updateTable({ ...filtersRef.current, search: undefined, page: undefined });
  };

  const handleStatusChange = useCallback((status) => {
    updateTable({ ...filters, status: status || undefined, page: undefined });
  }, [filters]);

  function paginatorProps(paginator) {
    return {
      totalRecords: paginator.total,
      startIndex: paginator.from,
      endIndex: paginator.to,
      currentPage: paginator.current_page,
      totalPages: paginator.last_page,
      rowsPerPage: paginator.per_page,
      onPageChange: (page) => updateTable({ ...filters, page }),
      onRowsPerPageChange: (per_page) => updateTable({ ...filters, per_page, page: undefined }),
    };
  }

  const columns = useMemo(() =>
    COLUMN_DEFS.filter((col) => visibleColumns.includes(col.key)).map((col) => {
      const base = { key: col.key, title: col.label, sortable: col.sortable, sortKey: col.sortKey };
      switch (col.key) {
        case 'name':
          return {
            ...base,
            render: (row) => (
              <div className="space-y-0.5">
                <button
                  type="button"
                  onClick={() => router.visit(row.show_url)}
                  className="block text-xs font-bold text-blue-900 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                >
                  {row.name}
                </button>
                <div className="text-[10px] text-slate-500" aria-label={`Age: ${row.current_age ?? '—'}`}>
                  Age: {row.current_age ?? '—'}
                </div>
              </div>
            ),
          };
        case 'contact':
          return {
            ...base,
            render: (row) => (
              <div className="space-y-0.5 text-xs text-slate-600">
                <div>{row.contact_number || '—'}</div>
                <div className="text-[10px] text-slate-500">{row.email || '—'}</div>
              </div>
            ),
          };
        case 'location':
          return {
            ...base,
            render: (row) => <span className="text-xs text-slate-600">{[row.address_city_municipality, row.address_province].filter(Boolean).join(', ') || '—'}</span>,
          };
        case 'employment':
          return {
            ...base,
            render: (row) => (
              <div className="space-y-0.5 text-xs text-slate-600">
                <div>{row.employment_country || '—'}</div>
                <div className="text-[10px] text-slate-500">{row.employment_status || '—'}</div>
              </div>
            ),
          };
        case 'cases':
          return {
            ...base,
            render: (row) => (
              <div className="text-xs text-slate-600">
                <span className="font-semibold">{row.case_count}</span>
                <span className="ml-1.5 text-[10px] text-slate-500">({row.open_case_count} open · {row.closed_case_count} closed)</span>
                <div className="text-[10px] text-slate-500">Latest: {row.latest_case_at ? new Date(row.latest_case_at).toLocaleDateString() : '—'}</div>
              </div>
            ),
          };
        case 'vulnerability_indicators':
          return {
            ...base,
            render: (row) => row.vulnerability_indicators?.length ? (
              <ul aria-label="Vulnerability indicators from latest eligible case" className="flex max-w-xs flex-wrap gap-1">
                {row.vulnerability_indicators.map((indicator) => (
                  <li key={indicator} className="rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-medium text-amber-800">{indicator}</li>
                ))}
              </ul>
            ) : <span aria-label="No vulnerability indicators" className="text-xs text-slate-500">—</span>,
          };
        case 'actions':
          return {
            ...base,
            render: (row) => (
              <button
                type="button"
                onClick={() => router.visit(row.show_url)}
                className="inline-flex h-8 w-8 items-center justify-center rounded text-slate-500 hover:bg-slate-100 hover:text-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                aria-label={`View ${row.name}`}
              >
                <span aria-hidden="true" className="material-symbols-outlined text-[18px]">visibility</span>
              </button>
            ),
          };
        default:
          return { ...base, render: (row) => row[col.key] };
      }
    }),
  [visibleColumns]);

  const selectedVulnerabilities = toFilterList(filters.vulnerability_indicator);
  const activeFilters = [
    ...(filters.client_type ? [{ key: 'client_type', label: 'Client type', value: filters.client_type === 'OFW' ? 'OFW' : 'Next of Kin' }] : []),
    ...(filters.status && filters.status !== 'Any' ? [{ key: 'status', label: 'Status', value: filters.status }] : []),
    ...selectedVulnerabilities.map((value) => ({ key: 'vulnerability_indicator', label: 'Vulnerability', value })),
  ];
  const clearFilters = () => {
    if (tableLoading) return;
    clearTimeout(searchTimeout.current);
    searchTimeout.current = null;
    pendingParamsRef.current = null;
    setSearchValue('');
    updateTable({});
  };
  const quickFilters = (
    <fieldset disabled={tableLoading} className="min-w-0 border-0 p-0 disabled:opacity-60">
      <TableTabs label="Filter clients by type" items={[
        { label: 'All', value: '', count: kpis.total_clients },
        { label: 'OFW', value: 'OFW', count: kpis.ofw_clients },
        { label: 'Next of Kin', value: 'NEXT_OF_KIN', count: kpis.next_of_kin_clients },
      ]} value={filters.client_type ?? ''} onSelect={(client_type) => updateTable({ ...filters, client_type: client_type || undefined, page: undefined })} />
    </fieldset>
  );
  const advancedFilterContent = (
    <fieldset disabled={tableLoading} className="flex min-w-0 flex-col gap-3 disabled:opacity-60">
      <FilterPanel groups={[{ key: 'vulnerability_indicator', label: 'Vulnerability', options: VULNERABILITIES.map((value) => ({ value, label: value })) }]}
        selected={{ vulnerability_indicator: selectedVulnerabilities }}
        onToggle={(_, value) => updateTable({ ...filters, vulnerability_indicator: toggleFilterValue(selectedVulnerabilities, value), page: undefined })}
        onClearAll={clearFilters} activeCount={activeFilters.length} />
      <label className="flex flex-col gap-1 text-xs text-slate-600">
        <span className="font-semibold">Case status</span>
        <Select aria-label="Filter clients by case status" value={filters.status ?? 'Any'} onChange={(e) => handleStatusChange(e.target.value)} className="h-9 min-w-[140px] px-2.5 text-[12.5px]">
          <option value="Any">Any</option><option value="Open">Open</option><option value="Closed">Closed</option><option value="Both">Both</option>
        </Select>
      </label>
    </fieldset>
  );
  const columnControlContent = (
    <div className="space-y-2">{COLUMN_DEFS.map((col) => (
      <label key={col.key} className="flex items-center gap-2.5 text-[13px] text-slate-700">
        <input type="checkbox" checked={visibleColumns.includes(col.key)} onChange={() => setVisibleColumns((prev) => prev.includes(col.key) ? prev.filter((key) => key !== col.key) : [...prev, col.key])} />{col.label}
      </label>
    ))}</div>
  );

  return (
    <AppLayout title="Client Directory">
      <Head title="Client Directory" />

      <header className="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-6">
        <div>
          <h1 className="text-2xl md:text-3xl font-extrabold font-headline tracking-tight text-slate-900">
            Clients
          </h1>
          <p className="text-sm text-slate-400 font-body mt-0.5">Client information and vulnerability indicators from the most recent eligible case</p>
        </div>
      </header>

      <section className="grid grid-cols-2 gap-3 mb-6 lg:grid-cols-4">
        <KpiCard
          label="OFW Clients"
          value={kpis.ofw_clients}
          icon="flight_takeoff"
          iconColor="text-blue-600"
          iconBg="bg-blue-50"
        />
        <KpiCard
          label="Next of Kin Clients"
          value={kpis.next_of_kin_clients}
          icon="family_restroom"
          iconColor="text-violet-600"
          iconBg="bg-violet-50"
          hint="Can overlap with OFW clients"
        />
        <KpiCard
          label="Vulnerable Clients"
          value={kpis.vulnerable_clients}
          icon="warning"
          iconColor="text-amber-600"
          iconBg="bg-amber-50"
        />
        <KpiCard
          label="Returning Clients"
          value={kpis.returning_clients}
          icon="history"
          iconColor="text-emerald-600"
          iconBg="bg-emerald-50"
          hint="Linked accounts with 2+ eligible cases"
        />
      </section>

      <UnifiedTable
        columns={columns}
        data={clients.data}
        keyExtractor={(row) => `${row.kind}-${row.id}`}
        {...paginatorProps(clients)}
        isLoading={tableLoading}
        emptyStateMessage="No clients found"
        searchValue={searchValue}
        searchPlaceholder="Search by name, email or case number..."
        onSearchChange={handleSearchChange}
        onSearchClear={handleSearchClear}
        sortKey={filters.sort ?? 'latest_case_at'}
        sortDirection={filters.direction ?? 'desc'}
        onSortChange={(sort, direction) => updateTable({ ...filters, sort, direction, page: undefined })}
        onAdvancedFilters={() => setFilterOpen((open) => { setColumnsOpen(false); return !open; })}
        isAdvancedFiltersOpen={filterOpen}
        advancedFiltersContent={advancedFilterContent}
        onColumnsControl={() => setColumnsOpen((open) => { setFilterOpen(false); return !open; })}
        isColumnsControlOpen={columnsOpen}
        columnsControlContent={columnControlContent}
        viewMode={viewMode}
        onViewModeChange={setViewMode}
        quickFilters={quickFilters}
        activeFilters={activeFilters}
        activeFilterCount={activeFilters.length}
        onRemoveFilter={(filter) => updateTable({ ...filters, [filter.key]: filter.key === 'vulnerability_indicator' ? selectedVulnerabilities.filter((value) => value !== filter.value) : undefined, page: undefined })}
        onClearFilters={clearFilters}
      />
    </AppLayout>
  );
}
