import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import ConfirmDialog from '@/Components/ui/ConfirmDialog';

import StatusBadge from '@/Components/ui/StatusBadge';
import CaseIssueFormModal from '@/Components/Admin/CaseIssueFormModal';

export default function AdminCaseIssueIndex({ issues, filters }) {
  const [showForm, setShowForm] = useState(false);
  const [editingIssue, setEditingIssue] = useState(null);
  const [confirmAction, setConfirmAction] = useState(null);
  const { UnsavedModal, bypassNext } = useUnsavedChanges(showForm);

  const showDeleted = filters?.show_deleted === 'true' || filters?.show_deleted === true;

  function toggleShowDeleted() {
    router.get(route('admin.case-issues.index'), {
      show_deleted: showDeleted ? undefined : 'true',
    }, { preserveState: true, replace: true });
  }

  const columns = useMemo(() => [
    {
      key: 'name',
      title: 'Name',
      sortable: true,
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
            onClick={() => { setEditingIssue(row); setShowForm(true); }}
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
    <AppLayout title="Manage Case Issues">
      {showForm && (
        <CaseIssueFormModal
          issue={editingIssue}
          onClose={() => { setShowForm(false); setEditingIssue(null); }}
          onBypass={bypassNext}
        />
      )}
      <Head title="Manage Case Issues" />
      <div data-tour="case-issues-header" className="mb-8 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Case Issues</h1>
          <p className="text-sm text-slate-500 mt-1">Manage issues used to classify client cases.</p>
        </div>
        <button data-tour="case-issues-new" onClick={() => setShowForm(true)} className="px-4 py-2 text-sm font-medium text-white bg-blue-900 rounded-md hover:bg-blue-800">
          + New Issue
        </button>
      </div>

      <div data-tour="case-issues-table">
      <UnifiedTable
        columns={columns}
        data={issues}
        keyExtractor={(row) => row.id}
        extraActions={(
          <label className="flex items-center gap-2 cursor-pointer ml-auto shrink-0">
            <input
              type="checkbox"
              checked={showDeleted}
              onChange={toggleShowDeleted}
              className="rounded border-slate-300 text-red-600 focus:ring-red-500 focus:ring-offset-0"
            />
            <span className="text-[13px] text-slate-500 whitespace-nowrap">Show deactivated</span>
          </label>
        )}
      />
      </div>
      {UnsavedModal}
      <ConfirmDialog
        open={!!confirmAction}
        title={confirmAction?.type === 'deactivate' ? 'Deactivate Issue' : 'Reactivate Issue'}
        message={confirmAction?.type === 'deactivate'
          ? 'Deactivate this issue?'
          : `Reactivate issue "${confirmAction?.name}"?`}
        confirmLabel={confirmAction?.type === 'deactivate' ? 'Deactivate' : 'Reactivate'}
        tone={confirmAction?.type === 'deactivate' ? 'danger' : 'default'}
        onConfirm={() => {
          if (confirmAction.type === 'deactivate') {
            router.delete(route('admin.case-issues.destroy', confirmAction.id), { preserveScroll: true });
          } else {
            router.patch(route('admin.case-issues.reactivate', confirmAction.id), {}, { preserveScroll: true });
          }
          setConfirmAction(null);
        }}
        onCancel={() => setConfirmAction(null)}
      />
    </AppLayout>
  );
}
