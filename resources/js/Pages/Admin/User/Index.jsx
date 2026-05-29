import AppLayout from '@/Layouts/AppLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';
import StatusBadge from '@/Components/ui/StatusBadge';

const roleOptions = [
  { value: 'CASE_MANAGER', label: 'Case Manager' },
  { value: 'AGENCY', label: 'Agency Focal' },
  { value: 'ADMIN', label: 'System Admin' },
];

function UserForm({ user, agencies, onClose, onBypass }) {
  const isEdit = !!user;
  const { data, setData, post, patch, processing, errors } = useForm({
    name: user?.name ?? '',
    email: user?.email ?? '',
    password: '',
    password_confirmation: '',
    role: user?.role ?? 'CASE_MANAGER',
    agcy_id: user?.agcy_id ?? '',
    contact_number: user?.contact_number ?? '',
    is_active: user?.is_active ?? true,
  });

  function handleSubmit(e) {
    e.preventDefault();
    onBypass?.();
    if (isEdit) {
      patch(route('admin.users.update', user.id), { onSuccess: onClose });
    } else {
      post(route('admin.users.store'), { onSuccess: onClose });
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40" onClick={onClose}>
      <div className="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
        <div className="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
          <h3 className="text-lg font-semibold text-slate-900">{isEdit ? 'Edit User' : 'New User'}</h3>
          <button onClick={onClose} className="text-slate-400 hover:text-slate-600">&times;</button>
        </div>
        <form onSubmit={handleSubmit} className="px-6 py-4 space-y-4">
          <div>
            <label className="block text-sm font-medium text-slate-700">Name *</label>
            <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
            {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
          </div>
          <div>
            <label className="block text-sm font-medium text-slate-700">Email *</label>
            <input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
            {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email}</p>}
          </div>
          <div>
            <label className="block text-sm font-medium text-slate-700">Password {isEdit ? '(leave blank to keep current)' : '*'}</label>
            <input type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
            {errors.password && <p className="mt-1 text-sm text-red-600">{errors.password}</p>}
          </div>
          <div>
            <label className="block text-sm font-medium text-slate-700">Role *</label>
            <select value={data.role} onChange={(e) => setData('role', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
              {roleOptions.map((opt) => (
                <option key={opt.value} value={opt.value}>{opt.label}</option>
              ))}
            </select>
          </div>
          {data.role === 'AGENCY' && (
            <div>
              <label className="block text-sm font-medium text-slate-700">Agency</label>
              <select value={data.agcy_id} onChange={(e) => setData('agcy_id', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="">Select agency...</option>
                {agencies.map((a) => (
                  <option key={a.id} value={a.id}>{a.name}</option>
                ))}
              </select>
            </div>
          )}
          <div>
            <label className="block text-sm font-medium text-slate-700">Contact Number</label>
            <input type="text" value={data.contact_number} onChange={(e) => setData('contact_number', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
          </div>
          {isEdit && (
            <div className="flex items-center gap-2">
              <input type="checkbox" id="is_active" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} className="rounded border-slate-300" />
              <label htmlFor="is_active" className="text-sm text-slate-700">Active</label>
            </div>
          )}
          <div className="flex justify-end gap-3 pt-2">
            <button type="button" onClick={onClose} className="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-50">Cancel</button>
            <button type="submit" disabled={processing} className="px-4 py-2 text-sm font-medium text-white bg-[#0b5384] rounded-md hover:bg-[#09416a] disabled:opacity-50">
              {isEdit ? 'Update' : 'Create'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

export default function AdminUserIndex({ users, agencies }) {
  const [showForm, setShowForm] = useState(false);
  const [editingUser, setEditingUser] = useState(null);
  const { showModal, confirmNavigation, cancelNavigation, bypassNext } = useUnsavedChanges(showForm);

  const roleLabels = { CASE_MANAGER: 'Case Manager', AGENCY: 'Agency Focal', ADMIN: 'System Admin' };

  function paginatorProps(paginator) {
    return {
      totalRecords: paginator.total,
      startIndex: paginator.from,
      endIndex: paginator.to,
      currentPage: paginator.current_page,
      totalPages: paginator.last_page,
      rowsPerPage: paginator.per_page,
      hideControlBar: true,
      onPageChange: (page) => {
        const url = new URL(window.location);
        url.searchParams.set('page', page);
        router.get(url.toString());
      },
      onRowsPerPageChange: (n) => {
        const url = new URL(window.location);
        url.searchParams.set('per_page', n);
        url.searchParams.delete('page');
        router.get(url.toString());
      },
    };
  }

  const columns = useMemo(() => [
    { key: 'name', title: 'Name', sortable: true,
      render: (row) => row.name,
    },
    {
      key: 'email',
      title: 'Email',
      sortable: true,
      render: (row) => row.email,
    },
    {
      key: 'role',
      title: 'Role',
      sortable: true,
      render: (row) => roleLabels[row.role] || row.role,
    },
    {
      key: 'agency',
      title: 'Agency',
      sortable: false,
      render: (row) => row.agency?.name || '—',
    },
    {
      key: 'is_active',
      title: 'Status',
      sortable: true,
      render: (row) => <StatusBadge status={row.is_active ? 'ACTIVE' : 'INACTIVE'} />,
    },
    {
      key: 'id',
      title: 'Actions',
      sortable: false,
      render: (row) => (
        <button onClick={() => { setEditingUser(row); setShowForm(true); }} className="min-h-[28px] px-2.5 bg-[#f1f5f9] text-slate-700 hover:bg-slate-200 text-[11px] font-bold rounded-[3px] transition-colors border border-slate-300">Edit</button>
      ),
    },
  ], []);

  return (
    <AppLayout title="Manage Users">
      {showForm && <UserForm user={editingUser} agencies={agencies} onClose={() => { setShowForm(false); setEditingUser(null); }} onBypass={bypassNext} />}
      <Head title="Manage Users" />
      <div className="mb-8 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Users</h1>
          <p className="text-sm text-slate-500 mt-1">Manage system users and their roles.</p>
        </div>
        <button onClick={() => setShowForm(true)} className="px-4 py-2 text-sm font-medium text-white bg-[#0b5384] rounded-md hover:bg-[#09416a]">
          + New User
        </button>
      </div>

      <UnifiedTable
        columns={columns}
        data={users.data}
        keyExtractor={(row) => row.id}
        {...paginatorProps(users)}
      />
      <UnsavedChangesModal show={showModal} onConfirm={confirmNavigation} onCancel={cancelNavigation} />
    </AppLayout>
  );
}
