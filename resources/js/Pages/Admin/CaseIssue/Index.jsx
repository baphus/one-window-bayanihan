import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';
import StatusBadge from '@/Components/ui/StatusBadge';
import CaseIssueFormModal from '@/Components/Admin/CaseIssueFormModal';

export default function AdminCaseIssueIndex({ issues }) {
  const [showForm, setShowForm] = useState(false);
  const [editingIssue, setEditingIssue] = useState(null);
  const { showModal, confirmNavigation, cancelNavigation, bypassNext } = useUnsavedChanges(showForm);

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
          <button
            onClick={() => {
              if (confirm('Deactivate this issue?')) {
                router.delete(route('admin.case-issues.destroy', row.id), { preserveScroll: true });
              }
            }}
            className="min-h-[28px] px-2.5 bg-red-50 text-red-600 hover:bg-red-100 text-[11px] font-bold rounded-md transition-colors border border-red-200"
          >
            Deactivate
          </button>
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
      <div className="mb-8 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Case Issues</h1>
          <p className="text-sm text-slate-500 mt-1">Manage issues used to classify client cases.</p>
        </div>
        <button onClick={() => setShowForm(true)} className="px-4 py-2 text-sm font-medium text-white bg-blue-900 rounded-md hover:bg-blue-800">
          + New Issue
        </button>
      </div>

      <UnifiedTable
        columns={columns}
        data={issues}
        keyExtractor={(row) => row.id}
      />
      <UnsavedChangesModal show={showModal} onConfirm={confirmNavigation} onCancel={cancelNavigation} />
    </AppLayout>
  );
}
