import AppLayout from '@/Layouts/AppLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { useState, useMemo } from 'react';

const TYPE_LABELS = {
  region: 'Region',
  province: 'Province',
  city: 'City',
  municipality: 'Municipality',
  barangay: 'Barangay',
};

const TYPE_COLORS = {
  region: 'bg-blue-100 text-blue-800 border-blue-200',
  province: 'bg-indigo-100 text-indigo-800 border-indigo-200',
  city: 'bg-purple-100 text-purple-800 border-purple-200',
  municipality: 'bg-teal-100 text-teal-800 border-teal-200',
  barangay: 'bg-amber-100 text-amber-800 border-amber-200',
};

const TYPE_ICONS = {
  region: 'map',
  province: 'flag',
  city: 'location_city',
  municipality: 'house',
  barangay: 'pin_drop',
};

export default function Index({ addresses, counts, filters }) {
  const [syncing, setSyncing] = useState(false);
  const [search, setSearch] = useState(filters?.search || '');
  const [typeFilter, setTypeFilter] = useState(filters?.type || '');

  const total = useMemo(() => {
    return Object.values(counts).reduce((sum, c) => sum + c, 0);
  }, [counts]);

  const applyFilters = (newType, newSearch) => {
    router.get(route('admin.system.addresses'), {
      type: newType || undefined,
      search: newSearch || undefined,
    }, { preserveScroll: true, preserveState: true });
  };

  const handleTypeChange = (e) => {
    const val = e.target.value;
    setTypeFilter(val);
    applyFilters(val, search);
  };

  const handleSearch = () => {
    applyFilters(typeFilter, search);
  };

  const handleSearchKeyDown = (e) => {
    if (e.key === 'Enter') handleSearch();
  };

  const handleSync = () => {
    if (syncing) return;
    setSyncing(true);
    router.post(route('admin.system.addresses.sync'), {}, {
      preserveScroll: true,
      onFinish: () => setSyncing(false),
    });
  };

  const flash = usePage().props.flash;

  return (
    <AppLayout title="Philippine Addresses">
      <Head title="Philippine Addresses" />

      <div className="mb-8 flex items-start justify-between gap-4 flex-wrap">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Philippine Addresses</h1>
          <p className="mt-1 text-sm text-slate-500">
            Browse, search, and manage the Philippine address reference database.
          </p>
        </div>

        <button
          onClick={handleSync}
          disabled={syncing}
          className={`inline-flex items-center gap-2 rounded-md px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition-all ${
            syncing
              ? 'bg-gray-400 cursor-not-allowed'
              : 'bg-gray-800 hover:bg-gray-700'
          }`}
        >
          {syncing ? (
            <>
              <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              Syncing...
            </>
          ) : (
            <>
              <span className="material-symbols-outlined text-[16px]">sync</span>
              Sync from PSGC
            </>
          )}
        </button>
      </div>

      {flash?.success && (
        <div className="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
          {flash.success}
        </div>
      )}

      {/* Summary Cards */}
      <div className="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
          <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Total</p>
          <p className="mt-1 text-2xl font-bold text-slate-900">{total.toLocaleString()}</p>
        </div>
        {Object.entries(TYPE_LABELS).map(([type, label]) => (
          <div key={type} className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div className="flex items-center gap-2">
              <span className="material-symbols-outlined text-lg text-slate-400">{TYPE_ICONS[type]}</span>
              <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">{label}</p>
            </div>
            <p className="mt-1 text-2xl font-bold text-slate-900">
              {(counts[type] || 0).toLocaleString()}
            </p>
          </div>
        ))}
      </div>

      {/* Filters */}
      <div className="mb-6 flex flex-wrap items-center gap-3">
        <select
          value={typeFilter}
          onChange={handleTypeChange}
          className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none"
        >
          <option value="">All Types</option>
          {Object.entries(TYPE_LABELS).map(([type, label]) => (
            <option key={type} value={type}>{label}</option>
          ))}
        </select>

        <div className="flex gap-2">
          <input
            type="text"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            onKeyDown={handleSearchKeyDown}
            placeholder="Search by name or code..."
            className="w-64 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 placeholder-slate-400 focus:border-blue-500 focus:outline-none"
          />
          <button
            onClick={handleSearch}
            className="rounded-lg bg-slate-100 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-200"
          >
            <span className="material-symbols-outlined text-[18px]">search</span>
          </button>
        </div>
      </div>

      {/* Address Table */}
      <div className="rounded-lg border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-slate-200">
            <thead className="bg-slate-50">
              <tr>
                <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Type</th>
                <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Code</th>
                <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Name</th>
                <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Parent Code</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {addresses.data.length === 0 ? (
                <tr>
                  <td colSpan={4} className="px-4 py-12 text-center text-sm text-slate-400">
                    No addresses found.
                  </td>
                </tr>
              ) : (
                addresses.data.map((addr) => (
                  <tr key={addr.id} className="hover:bg-slate-50 transition-colors">
                    <td className="px-4 py-3 whitespace-nowrap">
                      <span className={`inline-flex rounded-full border px-2.5 py-0.5 text-xs font-semibold capitalize ${TYPE_COLORS[addr.type] || 'bg-slate-100 text-slate-700'}`}>
                        {TYPE_LABELS[addr.type] || addr.type}
                      </span>
                    </td>
                    <td className="px-4 py-3 whitespace-nowrap text-sm font-mono text-slate-600">{addr.code}</td>
                    <td className="px-4 py-3 whitespace-nowrap text-sm font-medium text-slate-900">{addr.name}</td>
                    <td className="px-4 py-3 whitespace-nowrap text-sm font-mono text-slate-400">{addr.parent_code || '—'}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        {addresses.total > addresses.per_page && (
          <div className="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-4 py-3">
            <p className="text-sm text-slate-500">
              Showing {addresses.from}–{addresses.to} of {addresses.total}
            </p>
            <div className="flex gap-2">
              {addresses.links.map((link, i) => {
                if (!link.label.match(/^\d+$/) && link.label !== '...') return null;
                return (
                  <button
                    key={i}
                    disabled={link.active || !link.url}
                    onClick={() => {
                      if (link.url) {
                        router.get(link.url, {}, { preserveScroll: true, preserveState: true });
                      }
                    }}
                    className={`min-w-[32px] rounded-md px-2 py-1 text-sm font-medium ${
                      link.active
                        ? 'bg-gray-800 text-white'
                        : link.url
                          ? 'text-slate-600 hover:bg-slate-200'
                          : 'text-slate-300 cursor-default'
                    }`}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                  />
                );
              })}
            </div>
            <div className="flex gap-1">
              {addresses.prev_page_url && (
                <button
                  onClick={() => router.get(addresses.prev_page_url, {}, { preserveScroll: true, preserveState: true })}
                  className="rounded-md px-3 py-1 text-sm font-medium text-slate-600 hover:bg-slate-200"
                >
                  Previous
                </button>
              )}
              {addresses.next_page_url && (
                <button
                  onClick={() => router.get(addresses.next_page_url, {}, { preserveScroll: true, preserveState: true })}
                  className="rounded-md px-3 py-1 text-sm font-medium text-slate-600 hover:bg-slate-200"
                >
                  Next
                </button>
              )}
            </div>
          </div>
        )}
      </div>
    </AppLayout>
  );
}
