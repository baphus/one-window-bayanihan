import AppLayout from '@/Layouts/AppLayout';
import { Head, router, Link } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';
import StatusBadge from '@/Components/ui/StatusBadge';
import AgencyFormModal from '@/Components/Admin/AgencyFormModal';

export default function AdminAgencyIndex({ agencies }) {
  const [showForm, setShowForm] = useState(false);
  const [editingAgency, setEditingAgency] = useState(null);
  const { showModal, confirmNavigation, cancelNavigation, bypassNext } = useUnsavedChanges(showForm);

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
    {
      key: 'name',
      title: 'Name',
      sortable: true,
      render: (row) => row.name,
    },
    {
      key: 'short',
      title: 'Short',
      sortable: true,
      render: (row) => row.short,
    },
    {
      key: 'referrals_count',
      title: 'Referrals',
      sortable: true,
      render: (row) => row.referrals_count ?? 0,
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
          <Link href={route('admin.agencies.show', row.id)} className="min-h-[28px] px-2.5 bg-[#f1f5f9] text-slate-700 hover:bg-slate-200 text-[11px] font-bold rounded-[3px] transition-colors border border-slate-300 inline-flex items-center">View</Link>
          <button onClick={() => { setEditingAgency(row); setShowForm(true); }} className="min-h-[28px] px-2.5 bg-[#f1f5f9] text-slate-700 hover:bg-slate-200 text-[11px] font-bold rounded-[3px] transition-colors border border-slate-300">Edit</button>
          <button onClick={() => { if (confirm('Deactivate this agency?')) router.delete(route('admin.agencies.destroy', row.id), { preserveScroll: true }); }} className="min-h-[28px] px-2.5 bg-red-50 text-red-600 hover:bg-red-100 text-[11px] font-bold rounded-[3px] transition-colors border border-red-200">Deactivate</button>
        </div>
      ),
    },
  ], []);

  return (
    <AppLayout title="Manage Agencies">
      {showForm && <AgencyFormModal agency={editingAgency} onClose={() => { setShowForm(false); setEditingAgency(null); }} onBypass={bypassNext} />}
      <Head title="Manage Agencies" />
      <div className="mb-8 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Agencies</h1>
          <p className="text-sm text-slate-500 mt-1">Manage all partner agencies in the system.</p>
        </div>
        <button onClick={() => setShowForm(true)} className="px-4 py-2 text-sm font-medium text-white bg-[#0b5384] rounded-md hover:bg-[#09416a]">
          + New Agency
        </button>
      </div>

      <UnifiedTable
        columns={columns}
        data={agencies.data}
        keyExtractor={(row) => row.id}
        {...paginatorProps(agencies)}
      />
      <UnsavedChangesModal show={showModal} onConfirm={confirmNavigation} onCancel={cancelNavigation} />
    </AppLayout>
  );
}
