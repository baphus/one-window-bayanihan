import AppLayout from '@/Layouts/AppLayout';
import { Head, router, Link } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';

import StatusBadge from '@/Components/ui/StatusBadge';
import CaseCategoryFormModal from '@/Components/Admin/CaseCategoryFormModal';

export default function AdminCaseCategoryIndex({ categories }) {
  const [showForm, setShowForm] = useState(false);
  const [editingCategory, setEditingCategory] = useState(null);
  const { UnsavedModal, bypassNext } = useUnsavedChanges(showForm);

  const columns = useMemo(() => [
    {
      key: 'name',
      title: 'Name',
      sortable: true,
      render: (row) => (
        <span className="inline-flex items-center gap-2">
          {row.color && (
            <span className="w-3 h-3 rounded-full inline-block" style={{ backgroundColor: row.color }} />
          )}
          {row.name}
        </span>
      ),
    },
    {
      key: 'description',
      title: 'Description',
      sortable: false,
      render: (row) => row.description ?? <span className="text-slate-400">&mdash;</span>,
    },
    {
      key: 'case_files_count',
      title: 'Cases',
      sortable: true,
      render: (row) => row.case_files_count ?? 0,
    },
    {
      key: 'sort_order',
      title: 'Order',
      sortable: true,
      render: (row) => row.sort_order,
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
          <button
            onClick={() => { setEditingCategory(row); setShowForm(true); }}
            className="min-h-[28px] px-2.5 bg-slate-100 text-slate-700 hover:bg-slate-200 text-[11px] font-bold rounded-md transition-colors border border-slate-300"
          >
            Edit
          </button>
          {row.is_active ? (
            <button
              onClick={() => {
                if (confirm('Deactivate this category?')) {
                  router.delete(route('admin.case-categories.destroy', row.id), { preserveScroll: true });
                }
              }}
              className="min-h-[28px] px-2.5 bg-red-50 text-red-600 hover:bg-red-100 text-[11px] font-bold rounded-md transition-colors border border-red-200"
            >
              Deactivate
            </button>
          ) : (
            <button
              onClick={() => {
                if (confirm(`Reactivate category "${row.name}"?`)) {
                  router.patch(route('admin.case-categories.reactivate', row.id), {}, { preserveScroll: true });
                }
              }}
              className="min-h-[28px] px-2.5 bg-green-50 text-green-600 hover:bg-green-100 text-[11px] font-bold rounded-md transition-colors border border-green-200"
            >
              Reactivate
            </button>
          )}
        </div>
      ),
    },
  ], []);

  return (
    <AppLayout title="Manage Case Categories">
      {showForm && (
        <CaseCategoryFormModal
          category={editingCategory}
          onClose={() => { setShowForm(false); setEditingCategory(null); }}
          onBypass={bypassNext}
        />
      )}
      <Head title="Manage Case Categories" />
      <div data-tour="case-categories-header" className="mb-8 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Case Categories</h1>
          <p className="text-sm text-slate-500 mt-1">Manage categories used to classify client cases.</p>
        </div>
        <button data-tour="case-categories-new" onClick={() => setShowForm(true)} className="px-4 py-2 text-sm font-medium text-white bg-blue-900 rounded-md hover:bg-blue-800">
          + New Category
        </button>
      </div>

      <div data-tour="case-categories-table">
        <UnifiedTable
          columns={columns}
          data={categories}
          keyExtractor={(row) => row.id}
          hideControlBar
          hidePagination
        />
      </div>
      {UnsavedModal}
    </AppLayout>
  );
}
