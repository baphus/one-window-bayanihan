import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';
import StatusBadge from '@/Components/ui/StatusBadge';
import UserFormModal from '@/Components/Admin/UserFormModal';

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
        <div className="flex items-center gap-1.5">
          <button onClick={() => { setEditingUser(row); setShowForm(true); }} className="min-h-[28px] px-2.5 bg-[#f1f5f9] text-slate-700 hover:bg-slate-200 text-[11px] font-bold rounded-[3px] transition-colors border border-slate-300">Edit</button>
          <button onClick={() => { if (confirm('Deactivate this user?')) router.delete(route('admin.users.destroy', row.id), { preserveScroll: true }); }} className="min-h-[28px] px-2.5 bg-red-50 text-red-600 hover:bg-red-100 text-[11px] font-bold rounded-[3px] transition-colors border border-red-200">Deactivate</button>
        </div>
      ),
    },
  ], []);

  return (
    <AppLayout title="Manage Users">
      {showForm && <UserFormModal user={editingUser} agencies={agencies} onClose={() => { setShowForm(false); setEditingUser(null); }} onBypass={bypassNext} />}
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
