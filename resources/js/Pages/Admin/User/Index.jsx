import AppLayout from '@/Layouts/AppLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { useState, useMemo, useRef, useEffect } from 'react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import StatusBadge from '@/Components/ui/StatusBadge';
import UserAvatar from '@/Components/ui/UserAvatar';
import { Users, UserCheck, Briefcase, Building2, Shield } from 'lucide-react';
import { formatDisplayDate, formatDisplayTime } from '@/lib/utils';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import useTableVisitLoading from '@/Hooks/useTableVisitLoading';

import UserFormModal from '@/Components/Admin/UserFormModal';
import PeerProfileModal from '@/Components/PeerProfileModal';
import { RowContextMenu, RowContextMenuItem } from '@/Components/ui/RowContextMenu';

const roleBadgeStyles = {
  ADMIN: 'bg-purple-100 text-purple-800 border-purple-300',
  CASE_MANAGER: 'bg-blue-100 text-blue-800 border-blue-300',
  AGENCY: 'bg-amber-100 text-amber-800 border-amber-300',
};

const roleLabels = {
  CASE_MANAGER: 'Case Manager',
  AGENCY: 'Agency Focal',
  ADMIN: 'System Admin',
};

const COLUMN_DEFS = [
  { key: 'name', label: 'Name', default: true },
  { key: 'email', label: 'Email', default: true },
  { key: 'role', label: 'Role', default: true },
  { key: 'agency', label: 'Agency', default: true },
  { key: 'position', label: 'Position', default: false },
  { key: 'department', label: 'Department', default: false },
  { key: 'contact_number', label: 'Contact', default: false },
  { key: 'email_verified', label: 'Email Verified', default: true },
  { key: 'mfa_status', label: 'MFA', default: false },
  { key: 'status', label: 'Status', default: true },
  { key: 'created_at', label: 'Date Created', default: false },
  { key: 'actions', label: 'Actions', default: true },
];

export default function AdminUserIndex({ users, filters, stats, agencies = [] }) {
  const { auth } = usePage().props;
  const isAdmin = auth.user.role === 'ADMIN';

  const [showForm, setShowForm] = useState(false);
  const [editingUser, setEditingUser] = useState(null);
  const [peerProfileUser, setPeerProfileUser] = useState(null);
  const { UnsavedModal, bypassNext } = useUnsavedChanges(showForm);
  const { isLoading: tableLoading, withLoading } = useTableVisitLoading();

  const [searchValue, setSearchValue] = useState(filters?.search ?? '');
  const [contextMenu, setContextMenu] = useState(null);
  const [viewMode, setViewMode] = useState('list');

  const handleRowContextMenu = (e, row) => {
    e.preventDefault();
    setContextMenu({ x: e.clientX, y: e.clientY, row });
  };
  const [filterOpen, setFilterOpen] = useState(false);
  const [columnsOpen, setColumnsOpen] = useState(false);

  const searchTimeout = useRef(null);

  const [visibleColumns, setVisibleColumns] = useState(
    COLUMN_DEFS.filter((c) => c.default).map((c) => c.key),
  );

  const [roleFilter, setRoleFilter] = useState(filters?.role ?? '');
  const [statusFilter, setStatusFilter] = useState(filters?.status ?? '');
  const [agencyFilter, setAgencyFilter] = useState(filters?.agcy_id ?? '');
  const [mfaFilter, setMfaFilter] = useState(filters?.mfa_status ?? '');
  const [showDeleted, setShowDeleted] = useState(filters?.show_deleted ?? false);

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
    router.get(url.toString(), {}, withLoading({ preserveState: true, replace: true }));
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
    if (roleFilter) chips.push({ key: 'role', label: 'Role', value: roleLabels[roleFilter] || roleFilter });
    if (statusFilter) chips.push({ key: 'status', label: 'Status', value: statusFilter === 'active' ? 'Active' : 'Inactive' });
    if (agencyFilter) {
      const agency = agencies.find(a => a.id === agencyFilter);
      chips.push({ key: 'agcy_id', label: 'Agency', value: agency?.name || agencyFilter });
    }
    if (mfaFilter) chips.push({ key: 'mfa_status', label: 'MFA', value: mfaFilter === 'enabled' ? 'Enabled' : 'Disabled' });
    if (showDeleted) chips.push({ key: 'show_deleted', label: 'Deleted', value: 'Including deleted' });
    return chips;
  }, [roleFilter, statusFilter, agencyFilter, mfaFilter, agencies]);

  const handleRemoveFilter = (filter) => {
    if (filter.key === 'role') { setRoleFilter(''); navigateWith({ role: undefined }); }
    if (filter.key === 'status') { setStatusFilter(''); navigateWith({ status: undefined }); }
    if (filter.key === 'agcy_id') { setAgencyFilter(''); navigateWith({ agcy_id: undefined }); }
    if (filter.key === 'mfa_status') { setMfaFilter(''); navigateWith({ mfa_status: undefined }); }
    if (filter.key === 'show_deleted') { setShowDeleted(false); navigateWith({ show_deleted: undefined }); }
  };

  const handleClearFilters = () => {
    setRoleFilter('');
    setStatusFilter('');
    setAgencyFilter('');
    setMfaFilter('');
    setShowDeleted(false);
    navigateWith({ role: undefined, status: undefined, agcy_id: undefined, mfa_status: undefined, show_deleted: undefined });
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
        router.get(url.toString(), {}, withLoading({ preserveState: true, preserveScroll: true, only: ['users'] }));
      },
      onRowsPerPageChange: (n) => {
        const url = new URL(window.location);
        url.searchParams.set('per_page', n);
        url.searchParams.delete('page');
        router.get(url.toString(), {}, withLoading({ preserveState: true, preserveScroll: true, only: ['users'] }));
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
              render: (row) => (
                <div className="flex items-center gap-3">
                  <UserAvatar user={row} size="sm" onClick={() => setPeerProfileUser(row)} />
                  <div className="min-w-0">
                    <div className="text-sm font-semibold text-slate-800 truncate max-w-[180px]">{row.name}</div>
                  </div>
                </div>
              ),
              sortAccessor: (row) => row.name,
            };
          case 'email':
            return {
              ...base,
              render: (row) => (
                <span className="text-xs text-slate-500">{row.email}</span>
              ),
            };
          case 'role':
            return {
              ...base,
              render: (row) => (
                <span className={`inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-bold border ${roleBadgeStyles[row.role] || 'bg-slate-100 text-slate-700 border-slate-200'}`}>
                  {roleLabels[row.role] || row.role}
                </span>
              ),
            };
          case 'agency':
            return {
              ...base,
              sortable: false,
              render: (row) => {
                const agency = row.agency;
                if (!agency) return <span className="text-slate-400">&mdash;</span>;
                return (
                  <div className="flex items-center gap-2">
                    {agency.logo_url ? (
                      <img
                        src={agency.logo_url}
                        alt={agency.name}
                        className="w-5 h-5 rounded object-contain border border-slate-200 shrink-0"
                        onError={(e) => { e.target.style.display = 'none'; }}
                      />
                    ) : (
                      <span className="w-5 h-5 rounded bg-slate-100 flex items-center justify-center shrink-0">
                        <Building2 className="w-3 h-3 text-slate-400" />
                      </span>
                    )}
                    <span className="text-xs text-slate-700 truncate max-w-[140px]">{agency.name}</span>
                  </div>
                );
              },
            };
          case 'position':
            return {
              ...base,
              sortable: false,
              render: (row) => (
                <span className="text-xs text-slate-600">{row.position || <span className="text-slate-400">&mdash;</span>}</span>
              ),
            };
          case 'department':
            return {
              ...base,
              sortable: false,
              render: (row) => (
                <span className="text-xs text-slate-600">{row.department || <span className="text-slate-400">&mdash;</span>}</span>
              ),
            };
          case 'contact_number':
            return {
              ...base,
              sortable: false,
              render: (row) => (
                <span className="text-xs text-slate-600">{row.contact_number || <span className="text-slate-400">&mdash;</span>}</span>
              ),
            };
          case 'email_verified':
            return {
              ...base,
              sortable: false,
              render: (row) => (
                <button
                  type="button"
                  role="switch"
                  aria-checked={!!row.email_verified_at}
                  title={row.email_verified_at ? 'Verified' : 'Unverified'}
                  onClick={() => {
                    if (row.email_verified_at && !confirm('Unverifying this user will lock them out until an admin re-verifies them. Continue?')) return;
                    router.patch(route('admin.users.verify', row.id), {}, { preserveScroll: true });
                  }}
                  className={`relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors focus:outline-none focus:ring-2 focus:ring-blue-900 focus:ring-offset-1 ${row.email_verified_at ? 'bg-emerald-500' : 'bg-slate-300'}`}
                >
                  <span className={`inline-block h-4 w-4 transform rounded-full bg-white shadow transition ${row.email_verified_at ? 'translate-x-4' : 'translate-x-0'}`} />
                </button>
              ),
            };
          case 'mfa_status':
            return {
              ...base,
              sortable: false,
              render: (row) => (
                <span className={`inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-bold border ${row.mfa_enabled ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200'}`}>
                  {row.mfa_enabled ? 'On' : 'Off'}
                </span>
              ),
            };
          case 'status':
            return {
              ...base,
              sortable: false,
              render: (row) => (
                <StatusBadge status={row.is_active ? 'ACTIVE' : 'INACTIVE'} />
              ),
            };
          case 'created_at':
            return {
              ...base,
              render: (row) => (
                <div>
                  <div className="text-xs text-slate-700">{formatDisplayDate(row.created_at)}</div>
                  <div className="text-[10px] text-slate-500">{formatDisplayTime(row.created_at)}</div>
                </div>
              ),
            };
          case 'actions':
            return {
              ...base,
              sortable: false,
              title: 'Actions',
              render: (row) => (
                <div className="flex items-center gap-1.5">
                  <a
                    href={route('admin.users.show', row.id)}
                    className="min-h-[28px] px-2.5 bg-blue-900 text-white hover:bg-blue-800 text-[11px] font-bold rounded-md transition-colors border border-blue-900 inline-flex items-center"
                  >
                    View
                  </a>
                  <button
                    onClick={() => { setEditingUser(row); setShowForm(true); }}
                    className="min-h-[28px] px-2.5 bg-slate-100 text-slate-700 hover:bg-slate-200 text-[11px] font-bold rounded-md transition-colors border border-slate-300"
                  >
                    Edit
                  </button>
                  {isAdmin && row.is_active && !row.is_deleted && (
                    <button
                      onClick={() => {
                        if (confirm('Deactivate this user? They will lose access to the system.')) {
                          router.delete(route('admin.users.destroy', row.id), { preserveScroll: true });
                        }
                      }}
                      className="min-h-[28px] px-2.5 bg-red-50 text-red-600 hover:bg-red-100 text-[11px] font-bold rounded-md transition-colors border border-red-200"
                    >
                      Deactivate
                    </button>
                  )}
                  {isAdmin && (!row.is_active || row.is_deleted) && (
                    <>
                    <button
                      onClick={() => {
                        if (confirm(`Reactivate user "${row.name}"?`)) {
                          router.patch(route('admin.users.reactivate', row.id), {}, { preserveScroll: true });
                        }
                      }}
                      className="min-h-[28px] px-2.5 bg-green-50 text-green-600 hover:bg-green-100 text-[11px] font-bold rounded-md transition-colors border border-green-200"
                    >
                      Reactivate
                    </button>
                    <button
                      onClick={() => {
                        if (confirm(`Permanently delete user "${row.name}"? This action cannot be undone.`)) {
                          router.delete(route('admin.users.destroy', row.id), { preserveScroll: true });
                        }
                      }}
                      className="min-h-[28px] px-2.5 bg-red-600 text-white hover:bg-red-700 text-[11px] font-bold rounded-md transition-colors border border-red-700"
                    >
                      Delete
                    </button>
                    </>
                  )}
                </div>
              ),
            };
          default:
            return { ...base, render: (row) => row[col.key] };
        }
      }),
  [visibleColumns, editingUser, showForm, isAdmin]);

  const advancedFilterContent = useMemo(() => (
    <div className="space-y-4">
      <div>
        <label className="block text-[11px] font-bold uppercase tracking-widest text-slate-500 mb-1.5">Role</label>
        <select
          value={roleFilter}
          onChange={(e) => {
            const val = e.target.value;
            setRoleFilter(val);
            setFilterOpen(false);
            navigateWith({ role: val || undefined });
          }}
          className="w-full border border-slate-200 rounded-md px-3 py-2 text-[13px] font-medium text-slate-700 outline-none focus:ring-1 focus:ring-blue-900"
        >
          <option value="">All Roles</option>
          <option value="CASE_MANAGER">Case Manager</option>
          <option value="AGENCY">Agency Focal</option>
          <option value="ADMIN">System Admin</option>
        </select>
      </div>
      <div>
        <label className="block text-[11px] font-bold uppercase tracking-widest text-slate-500 mb-1.5">Agency</label>
        <select
          value={agencyFilter}
          onChange={(e) => {
            const val = e.target.value;
            setAgencyFilter(val);
            setFilterOpen(false);
            navigateWith({ agcy_id: val || undefined });
          }}
          className="w-full border border-slate-200 rounded-md px-3 py-2 text-[13px] font-medium text-slate-700 outline-none focus:ring-1 focus:ring-blue-900"
        >
          <option value="">All Agencies</option>
          {agencies.map((a) => (
            <option key={a.id} value={a.id}>{a.name}</option>
          ))}
        </select>
      </div>
      <div>
        <label className="block text-[11px] font-bold uppercase tracking-widest text-slate-500 mb-1.5">Status</label>
        <select
          value={statusFilter}
          onChange={(e) => {
            const val = e.target.value;
            setStatusFilter(val);
            setFilterOpen(false);
            navigateWith({ status: val || undefined });
          }}
          className="w-full border border-slate-200 rounded-md px-3 py-2 text-[13px] font-medium text-slate-700 outline-none focus:ring-1 focus:ring-blue-900"
        >
          <option value="">All Statuses</option>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>
      <div>
        <label className="block text-[11px] font-bold uppercase tracking-widest text-slate-500 mb-1.5">MFA Status</label>
        <select
          value={mfaFilter}
          onChange={(e) => {
            const val = e.target.value;
            setMfaFilter(val);
            setFilterOpen(false);
            navigateWith({ mfa_status: val || undefined });
          }}
          className="w-full border border-slate-200 rounded-md px-3 py-2 text-[13px] font-medium text-slate-700 outline-none focus:ring-1 focus:ring-blue-900"
        >
          <option value="">All</option>
          <option value="enabled">Enabled</option>
          <option value="disabled">Disabled</option>
        </select>
      </div>
      <div>
        <label className="flex items-center gap-2.5 cursor-pointer">
          <input
            type="checkbox"
            checked={showDeleted}
            onChange={(e) => {
              const val = e.target.checked;
              setShowDeleted(val);
              setFilterOpen(false);
              navigateWith({ show_deleted: val ? '1' : undefined });
            }}
            className="rounded border-slate-300 text-red-600 focus:ring-red-500 focus:ring-offset-0"
          />
          <span className="text-[12px] font-semibold text-slate-700">Show deactivated users</span>
        </label>
      </div>
    </div>
  ), [roleFilter, statusFilter, agencyFilter, mfaFilter, showDeleted, agencies]);

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
            className="rounded border-slate-200 text-blue-900 focus:ring-blue-900 focus:ring-offset-0"
          />
          {col.label}
        </label>
      ))}
    </div>
  ), [visibleColumns]);

  return (
    <AppLayout title="Manage Users">
      <Head title="Manage Users" />

      {showForm && (
        <UserFormModal
          user={editingUser}
          agencies={agencies}
          onClose={() => { setShowForm(false); setEditingUser(null); }}
          onBypass={bypassNext}
        />
      )}

      <header data-tour="users-header" className="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-6">
        <div>
          <h1 className="text-2xl md:text-3xl font-extrabold font-headline tracking-tight text-slate-900">
            Users
          </h1>
          <p className="text-sm text-slate-400 font-body mt-0.5">Manage system users, roles, and agency assignments.</p>
        </div>
      </header>

      <section data-tour="users-stats" className="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
        <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
          <div className="flex items-start justify-between mb-2">
            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Total Users</p>
            <span className="p-1.5 bg-blue-50 rounded-lg"><Users className="w-4 h-4 text-blue-900" /></span>
          </div>
          <p className="text-2xl font-black text-slate-900">{stats?.total ?? 0}</p>
        </div>

        <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
          <div className="flex items-start justify-between mb-2">
            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Active</p>
            <span className="p-1.5 bg-emerald-50 rounded-lg"><UserCheck className="w-4 h-4 text-emerald-600" /></span>
          </div>
          <p className="text-2xl font-black text-slate-900">{stats?.active ?? 0}</p>
          <div className="flex items-center gap-1.5 mt-1.5">
            <span className="text-[11px] font-medium text-slate-500">
              {stats?.total > 0 ? `${((stats.active / stats.total) * 100).toFixed(0)}%` : '—'}
            </span>
          </div>
        </div>

        <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
          <div className="flex items-start justify-between mb-2">
            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Case Managers</p>
            <span className="p-1.5 bg-blue-50 rounded-lg"><Briefcase className="w-4 h-4 text-blue-600" /></span>
          </div>
          <p className="text-2xl font-black text-slate-900">{stats?.case_managers ?? 0}</p>
        </div>

        <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
          <div className="flex items-start justify-between mb-2">
            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Agency Focals</p>
            <span className="p-1.5 bg-amber-50 rounded-lg"><Building2 className="w-4 h-4 text-amber-600" /></span>
          </div>
          <p className="text-2xl font-black text-slate-900">{stats?.agency_focals ?? 0}</p>
        </div>

        <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
          <div className="flex items-start justify-between mb-2">
            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Admins</p>
            <span className="p-1.5 bg-purple-50 rounded-lg"><Shield className="w-4 h-4 text-purple-600" /></span>
          </div>
          <p className="text-2xl font-black text-slate-900">{stats?.admins ?? 0}</p>
        </div>
      </section>

      <div data-tour="users-table">
      <UnifiedTable
        columns={columns}
        data={users.data}
        keyExtractor={(row) => row.id}
        {...paginatorProps(users)}
        searchValue={searchValue}
        searchPlaceholder="Search by name, email, or position..."
        onSearchChange={handleSearchChange}
        onAdvancedFilters={() => setFilterOpen((v) => { setColumnsOpen(false); return !v; })}
        isAdvancedFiltersOpen={filterOpen}
        advancedFiltersContent={advancedFilterContent}
        onColumnsControl={() => setColumnsOpen((v) => { setFilterOpen(false); return !v; })}
        isColumnsControlOpen={columnsOpen}
        columnsControlContent={columnControlContent}
        onViewModeChange={setViewMode}
        viewMode={viewMode}
        onNewRecord={() => { setEditingUser(null); setShowForm(true); }}
        newRecordLabel="New User"
        activeFilters={activeFilters}
        onRemoveFilter={handleRemoveFilter}
        onClearFilters={handleClearFilters}
        onRowContextMenu={handleRowContextMenu}
        isLoading={tableLoading}
      />
      </div>

      {UnsavedModal}
      <PeerProfileModal user={peerProfileUser} show={!!peerProfileUser} onClose={() => setPeerProfileUser(null)} />
      {contextMenu && (
        <RowContextMenu x={contextMenu.x} y={contextMenu.y} onClose={() => setContextMenu(null)}>
          <RowContextMenuItem icon="visibility" label="View Profile" onClick={() => {
            router.visit(route('admin.users.show', contextMenu.row.id));
            setContextMenu(null);
          }} />
          <RowContextMenuItem icon="edit" label="Edit" onClick={() => {
            setEditingUser(contextMenu.row);
            setShowForm(true);
            setContextMenu(null);
          }} />
          {contextMenu.row.is_active && !contextMenu.row.is_deleted && (
            <RowContextMenuItem icon="block" label="Deactivate" variant="danger" onClick={() => {
              if (confirm(`Deactivate user ${contextMenu.row.name}?`)) {
                router.delete(route('admin.users.destroy', contextMenu.row.id), { preserveScroll: true });
              }
              setContextMenu(null);
            }} />
          )}
          {(!contextMenu.row.is_active || contextMenu.row.is_deleted) && (
            <>
            <RowContextMenuItem icon="restart_alt" label="Reactivate" variant="success" onClick={() => {
              if (confirm(`Reactivate user "${contextMenu.row.name}"?`)) {
                router.patch(route('admin.users.reactivate', contextMenu.row.id), {}, { preserveScroll: true });
              }
              setContextMenu(null);
            }} />
            <RowContextMenuItem icon="delete" label="Delete permanently" variant="danger" onClick={() => {
              if (confirm(`Permanently delete user "${contextMenu.row.name}"? This cannot be undone.`)) {
                router.delete(route('admin.users.destroy', contextMenu.row.id), { preserveScroll: true });
              }
              setContextMenu(null);
            }} />
            </>
          )}
        </RowContextMenu>
      )}
    </AppLayout>
  );
}
