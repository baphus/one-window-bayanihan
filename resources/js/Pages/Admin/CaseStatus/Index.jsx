import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { useState, useMemo, useCallback } from 'react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';

import StatusBadge from '@/Components/ui/StatusBadge';
import InputError from '@/Components/InputError';
import { caseStatusSchema } from '@/Schemas/adminSchemas';
import useClientValidation from '@/Hooks/useClientValidation';
import { RowContextMenu, RowContextMenuItem } from '@/Components/ui/RowContextMenu';

const typeColors = {
  case: 'bg-blue-100 text-blue-800',
  referral: 'bg-purple-100 text-purple-800',
};

function StatusForm({ status, onClose, onBypass }) {
  const isEdit = !!status;
  const { data, setData, post, patch, processing, errors, clearErrors, setError } = useForm({
    name: status?.name ?? '',
    type: status?.type ?? 'referral',
    color: status?.color ?? '',
    sort_order: status?.sort_order ?? 0,
    is_active: status?.is_active ?? true,
  });
  const { validate } = useClientValidation(caseStatusSchema, data, setError);

  function handleSubmit(e) {
    e.preventDefault();
    clearErrors();
    if (!validate()) return;
    onBypass?.();
    if (isEdit) {
      patch(route('admin.case-statuses.update', status.id), { onSuccess: onClose });
    } else {
      post(route('admin.case-statuses.store'), { onSuccess: onClose });
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40" onClick={onClose}>
      <div className="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto owb-modal-animate" onClick={(e) => e.stopPropagation()}>
        <div className="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
          <h3 className="text-lg font-semibold text-slate-900">{isEdit ? 'Edit Status' : 'New Status'}</h3>
          <button onClick={onClose} className="text-slate-400 hover:text-slate-600">&times;</button>
        </div>
        <form onSubmit={handleSubmit} className="px-6 py-4 space-y-4">
          <div>
            <label className="block text-sm font-medium text-slate-700">Name *</label>
            <input
              type="text"
              value={data.name}
              onChange={(e) => setData('name', e.target.value)}
              className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
              required
              maxLength={255}
            />
            <InputError message={errors.name} className="mt-1" />
          </div>
          <div>
            <label className="block text-sm font-medium text-slate-700">Type *</label>
            <select
              value={data.type}
              onChange={(e) => setData('type', e.target.value)}
              className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-2 text-sm"
              required
            >
              <option value="case">Case</option>
              <option value="referral">Referral</option>
            </select>
            <InputError message={errors.type} className="mt-1" />
          </div>
          <div>
            <label className="block text-sm font-medium text-slate-700">Color</label>
            <div className="mt-1 flex items-center gap-3">
              <input
                type="color"
                value={data.color || '#3b82f6'}
                onChange={(e) => setData('color', e.target.value)}
                className="h-9 w-9 rounded border border-slate-300 cursor-pointer"
              />
              <input
                type="text"
                value={data.color}
                onChange={(e) => setData('color', e.target.value)}
                placeholder="#3b82f6"
                className="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
              />
            </div>
            <InputError message={errors.color} className="mt-1" />
          </div>
          <div>
            <label className="block text-sm font-medium text-slate-700">Sort Order</label>
            <input
              type="number"
              min="0"
              value={data.sort_order}
              onChange={(e) => setData('sort_order', parseInt(e.target.value) || 0)}
              className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
            />
            <InputError message={errors.sort_order} className="mt-1" />
          </div>
          <div className="flex items-center gap-2">
            <input
              type="checkbox"
              id="is_active"
              checked={data.is_active}
              onChange={(e) => setData('is_active', e.target.checked)}
              className="rounded border-slate-300"
            />
            <label htmlFor="is_active" className="text-sm text-slate-700">Active</label>
          </div>
          <div className="flex justify-end gap-3 pt-2">
            <button type="button" onClick={onClose} className="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-50">Cancel</button>
            <button type="submit" disabled={processing} className="px-4 py-2 text-sm font-medium text-white bg-blue-900 rounded-md hover:bg-blue-800 disabled:opacity-50">
              {isEdit ? 'Update' : 'Create'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

export default function CaseStatusIndex({ statuses }) {
  const [showForm, setShowForm] = useState(false);
  const [editingStatus, setEditingStatus] = useState(null);
  const { UnsavedModal, bypassNext } = useUnsavedChanges(showForm);
  const [contextMenu, setContextMenu] = useState(null);

  const handleRowContextMenu = (e, row) => {
    e.preventDefault();
    setContextMenu({ x: e.clientX, y: e.clientY, row });
  };

  const caseStatuses = useMemo(() => statuses.filter((s) => s.type === 'case'), [statuses]);
  const referralStatuses = useMemo(() => statuses.filter((s) => s.type === 'referral'), [statuses]);

  function handleDelete(status) {
    if (!confirm(`Delete "${status.name}"? This action cannot be undone.`)) return;
    router.delete(route('admin.case-statuses.destroy', status.id), {
      preserveScroll: true,
    });
  }

  const statusColumns = useMemo(() => [
    {
      key: 'name',
      title: 'Name',
      render: (s) => (
        <div className="flex items-center gap-2">
          {s.color && (
            <span className="inline-block w-4 h-4 rounded-full border border-slate-200" style={{ backgroundColor: s.color }} />
          )}
          <span className="font-medium text-slate-900">{s.name}</span>
          {s.is_system && (
            <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded">System</span>
          )}
        </div>
      ),
    },
    { key: 'slug', title: 'Slug', render: (s) => <code className="text-xs font-mono bg-slate-100 px-2 py-0.5 rounded text-slate-700">{s.slug}</code> },
    { key: 'sort_order', title: 'Sort Order', render: (s) => <span className="text-sm text-slate-600">{s.sort_order}</span> },
    {
      key: 'is_active',
      title: 'Status',
      render: (s) => <StatusBadge status={s.is_active ? 'ACTIVE' : 'INACTIVE'} />,
    },
    {
      key: 'actions',
      title: 'Actions',
      render: (s) => (
        <>
          <button onClick={() => { setEditingStatus(s); setShowForm(true); }} className="min-h-[28px] px-2.5 bg-slate-100 text-slate-700 hover:bg-slate-200 text-[11px] font-bold rounded-md transition-colors border border-slate-300">Edit</button>
          {!s.is_system && (
            <button onClick={() => handleDelete(s)} className="min-h-[28px] px-2.5 bg-red-50 text-red-600 hover:bg-red-100 text-[11px] font-bold rounded-md transition-colors border border-red-200">Delete</button>
          )}
        </>
      ),
    },
  ], []);

  function renderSection(title, list) {
    return (
      <div className="rounded-lg bg-white shadow-sm border border-slate-200 overflow-hidden">
        <div className="px-6 py-4 border-b border-slate-100 bg-slate-50">
          <h3 className="text-base font-semibold text-slate-900">{title}</h3>
        </div>
        <UnifiedTable
          columns={statusColumns}
          data={list}
          keyExtractor={(s) => s.id}
          hideControlBar
          hidePagination
          onRowContextMenu={handleRowContextMenu}
        />
      </div>
    );
  }

  return (
    <AppLayout title="Case Statuses">
      {showForm && <StatusForm status={editingStatus} onClose={() => { setShowForm(false); setEditingStatus(null); }} onBypass={bypassNext} />}
      <Head title="Case Statuses" />
      <div data-tour="case-statuses-header" className="mb-8 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Case Statuses</h1>
          <p className="text-sm text-slate-500 mt-1">Manage configurable statuses for cases and referrals.</p>
        </div>
        <button data-tour="case-statuses-new" onClick={() => setShowForm(true)} className="px-4 py-2 text-sm font-medium text-white bg-blue-900 rounded-md hover:bg-blue-800">
          + New Status
        </button>
      </div>

      <div data-tour="case-statuses-list" className="space-y-8">
        {renderSection('Case Statuses', caseStatuses)}
        {renderSection('Referral Statuses', referralStatuses)}
      </div>
      {UnsavedModal}
      {contextMenu && (
        <RowContextMenu x={contextMenu.x} y={contextMenu.y} onClose={() => setContextMenu(null)}>
          <RowContextMenuItem icon="edit" label="Edit" onClick={() => {
            setEditingStatus(contextMenu.row);
            setContextMenu(null);
          }} />
        </RowContextMenu>
      )}
    </AppLayout>
  );
}
