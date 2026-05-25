import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';

function ServiceForm({ service, allAgencies, onClose }) {
  const isEdit = !!service;
  const { data, setData, post, patch, processing, errors } = useForm({
    name: service?.name ?? '',
    description: service?.description ?? '',
    agcy_id: service?.agcy_id ?? '',
    processing_days: service?.processing_days ?? '',
  });

  function handleSubmit(e) {
    e.preventDefault();
    if (isEdit) {
      patch(route('admin.services.update', service.id), { onSuccess: onClose });
    } else {
      post(route('admin.services.store'), { onSuccess: onClose });
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40" onClick={onClose}>
      <div className="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
        <div className="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
          <h3 className="text-lg font-semibold text-slate-900">{isEdit ? 'Edit Service' : 'New Service'}</h3>
          <button onClick={onClose} className="text-slate-400 hover:text-slate-600">&times;</button>
        </div>
        <form onSubmit={handleSubmit} className="px-6 py-4 space-y-4">
          <div>
            <label className="block text-sm font-medium text-slate-700">Name *</label>
            <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
            {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
          </div>
          <div>
            <label className="block text-sm font-medium text-slate-700">Description</label>
            <textarea rows={3} value={data.description} onChange={(e) => setData('description', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
          </div>
          <div>
            <label className="block text-sm font-medium text-slate-700 mb-2">Agency *</label>
            <select value={data.agcy_id} onChange={(e) => setData('agcy_id', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
              <option value="">Select an agency...</option>
              {allAgencies.map((agency) => (
                <option key={agency.id} value={agency.id}>{agency.name}</option>
              ))}
            </select>
            {errors.agcy_id && <p className="mt-1 text-sm text-red-600">{errors.agcy_id}</p>}
          </div>
          <div>
            <label className="block text-sm font-medium text-slate-700">Processing Days</label>
            <input type="number" min="0" max="365" value={data.processing_days} onChange={(e) => setData('processing_days', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
          </div>
          <div className="flex justify-end gap-3 pt-2">
            <button type="button" onClick={onClose} className="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-50">Cancel</button>
            <button type="submit" disabled={processing} className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-500 disabled:opacity-50">
              {isEdit ? 'Update' : 'Create'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

export default function AdminServiceIndex({ services, allAgencies }) {
  const [showForm, setShowForm] = useState(false);
  const [editingService, setEditingService] = useState(null);
  const { showModal, confirmNavigation, cancelNavigation } = useUnsavedChanges(showForm);

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
        window.location = url.toString();
      },
      onRowsPerPageChange: (n) => {
        const url = new URL(window.location);
        url.searchParams.set('per_page', n);
        url.searchParams.delete('page');
        window.location = url.toString();
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
      title: 'Agencies',
      sortable: true,
      render: (row) => row.agency?.name ?? 'N/A',
    },
    {
      key: 'id',
      title: 'Actions',
      sortable: false,
      render: (row) => (
        <button onClick={() => { setEditingService(row); setShowForm(true); }} className="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
      ),
    },
  ], []);

  return (
    <AppLayout title="Manage Services">
      {showForm && <ServiceForm service={editingService} allAgencies={allAgencies} onClose={() => { setShowForm(false); setEditingService(null); }} />}
      <Head title="Manage Services" />
      <div className="mb-8 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Services</h1>
          <p className="text-sm text-slate-500 mt-1">Manage all services offered through the system.</p>
        </div>
        <button onClick={() => setShowForm(true)} className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-500">
          + New Service
        </button>
      </div>

      <UnifiedTable
        columns={columns}
        data={services.data}
        keyExtractor={(row) => row.id}
        {...paginatorProps(services)}
      />
      <UnsavedChangesModal show={showModal} onConfirm={confirmNavigation} onCancel={cancelNavigation} />
    </AppLayout>
  );
}
