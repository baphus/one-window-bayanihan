import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import ConfirmDialog from '@/Components/ui/ConfirmDialog';

import StatusBadge from '@/Components/ui/StatusBadge';
import CaseCategoryFormModal from '@/Components/Admin/CaseCategoryFormModal';

export default function AdminCaseCategoryIndex({ categories, filters }) {
  const [showForm, setShowForm] = useState(false);
  const [editingCategory, setEditingCategory] = useState(null);
  const [confirmAction, setConfirmAction] = useState(null);
  const { UnsavedModal, bypassNext } = useUnsavedChanges(showForm);

  const showDeleted = filters?.show_deleted === 'true' || filters?.show_deleted === true;

  function toggleShowDeleted() {
    router.get(route('admin.case-categories.index'), {
      show_deleted: showDeleted ? undefined : 'true',
    }, { preserveState: true, replace: true });
  }

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
                setConfirmAction({ type: 'deactivate', id: row.id });
              }}
              className="min-h-[28px] px-2.5 bg-red-50 text-red-600 hover:bg-red-100 text-[11px] font-bold rounded-md transition-colors border border-red-200"
            >
              Deactivate
            </button>
          ) : (
            <button
              onClick={() => {
                setConfirmAction({ type: 'reactivate', id: row.id, name: row.name });
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
    <AppLayout title="Case Categories">
      {showForm && (
        <CaseCategoryFormModal
          category={editingCategory}
          onClose={() => { setShowForm(false); setEditingCategory(null); }}
          onBypass={bypassNext}
        />
      )}
      <Head title="Case Categories" />
      <div data-tour="case-categories-header" className="mb-4 flex items-start justify-between">
        <div>
          <h1 className="text-2xl md:text-3xl font-extrabold font-headline tracking-tight text-slate-900">Case Categories</h1>
          <p className="text-sm text-slate-400 font-body mt-0.5">Manage categories used to classify client cases.</p>
        </div>
        <button data-tour="case-categories-new" onClick={() => setShowForm(true)} className="px-4 py-2 text-sm font-medium text-white bg-blue-900 rounded-md hover:bg-blue-800">
          + New Category
        </button>
      </div>
      <div className="mb-8 pt-2">
        <label className="flex items-center gap-2 cursor-pointer">
          <input
            type="checkbox"
            checked={showDeleted}
            onChange={toggleShowDeleted}
            className="rounded border-slate-300 text-red-600 focus:ring-red-500 focus:ring-offset-0"
          />
          <span className="text-[13px] text-slate-500 whitespace-nowrap">Show deactivated</span>
        </label>
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
      <ConfirmDialog
        open={!!confirmAction}
        title={confirmAction?.type === 'deactivate' ? 'Deactivate Category' : 'Reactivate Category'}
        message={confirmAction?.type === 'deactivate'
          ? 'Deactivate this category?'
          : `Reactivate category "${confirmAction?.name}"?`}
        confirmLabel={confirmAction?.type === 'deactivate' ? 'Deactivate' : 'Reactivate'}
        tone={confirmAction?.type === 'deactivate' ? 'danger' : 'default'}
        onConfirm={() => {
          if (confirmAction.type === 'deactivate') {
            router.delete(route('admin.case-categories.destroy', confirmAction.id), { preserveScroll: true });
          } else {
            router.patch(route('admin.case-categories.reactivate', confirmAction.id), {}, { preserveScroll: true });
          }
          setConfirmAction(null);
        }}
        onCancel={() => setConfirmAction(null)}
      />
    </AppLayout>
  );
}
