import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import { RowContextMenu, RowContextMenuItem } from '@/Components/ui/RowContextMenu';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';
import ServiceFormModal from '@/Components/Admin/ServiceFormModal';

export default function AdminServiceIndex({ services, allAgencies }) {
  const [showForm, setShowForm] = useState(false);
  const [editingService, setEditingService] = useState(null);
  const [contextMenu, setContextMenu] = useState(null);
  const { showModal, confirmNavigation, cancelNavigation, bypassNext } = useUnsavedChanges(showForm);

  function handleRowContextMenu(e, row) {
    e.preventDefault();
    setContextMenu({ x: e.clientX, y: e.clientY, row });
  }

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
        router.get(url.toString(), {}, { preserveState: true, preserveScroll: true, only: ['services'] });
      },
      onRowsPerPageChange: (n) => {
        const url = new URL(window.location);
        url.searchParams.set('per_page', n);
        url.searchParams.delete('page');
        router.get(url.toString(), {}, { preserveState: true, preserveScroll: true, only: ['services'] });
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
      key: 'description',
      title: 'Description',
      sortable: false,
      render: (row) => <span className="max-w-xs truncate block">{row.description || '—'}</span>,
    },
    {
      key: 'agency',
      title: 'Agency',
      sortable: true,
      render: (row) => row.agency?.name ?? 'N/A',
    },
    {
      key: 'processing_days',
      title: 'Processing Days',
      sortable: true,
      render: (row) => <span>{row.processing_days ?? '—'}</span>,
    },
    {
      key: 'id',
      title: 'Actions',
      sortable: false,
      render: (row) => (
        <div className="flex items-center gap-1.5">
          <button onClick={() => { setEditingService(row); setShowForm(true); }} className="min-h-[28px] px-2.5 bg-slate-100 text-slate-700 hover:bg-slate-200 text-[11px] font-bold rounded-md transition-colors border border-slate-300">Edit</button>
          <button onClick={() => { if (confirm(`Delete service "${row.name}"?`)) router.delete(route('admin.services.destroy', row.id), { preserveScroll: true }); }} className="min-h-[28px] px-2.5 bg-red-50 text-red-600 hover:bg-red-100 text-[11px] font-bold rounded-md transition-colors border border-red-200">Delete</button>
        </div>
      ),
    },
  ], []);

  return (
    <AppLayout title="Manage Services">
      {showForm && <ServiceFormModal service={editingService} allAgencies={allAgencies} onClose={() => { setShowForm(false); setEditingService(null); }} onBypass={bypassNext} />}
      <Head title="Manage Services" />
      <div data-tour="admin-services-header" className="mb-8 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Services</h1>
          <p className="text-sm text-slate-500 mt-1">Manage all services offered through the system.</p>
        </div>
        <button data-tour="admin-services-new" onClick={() => setShowForm(true)} className="px-4 py-2 text-sm font-medium text-white bg-blue-900 rounded-md hover:bg-blue-800">
          + New Service
        </button>
      </div>

      <div data-tour="admin-services-table">
      <UnifiedTable
        columns={columns}
        data={services.data}
        keyExtractor={(row) => row.id}
        {...paginatorProps(services)}
        onRowContextMenu={handleRowContextMenu}
      />
      </div>
      <UnsavedChangesModal show={showModal} onConfirm={confirmNavigation} onCancel={cancelNavigation} />
      {contextMenu && (
        <RowContextMenu x={contextMenu.x} y={contextMenu.y} onClose={() => setContextMenu(null)}>
          <RowContextMenuItem icon="edit" label="Edit" onClick={() => {
            setEditingService(contextMenu.row);
            setShowForm(true);
            setContextMenu(null);
          }} />
          <RowContextMenuItem icon="delete" label="Delete" variant="danger" onClick={() => {
            if (confirm(`Delete service "${contextMenu.row.name}"?`)) {
              router.delete(route('admin.services.destroy', contextMenu.row.id), { preserveScroll: true });
            }
            setContextMenu(null);
          }} />
        </RowContextMenu>
      )}
    </AppLayout>
  );
}
