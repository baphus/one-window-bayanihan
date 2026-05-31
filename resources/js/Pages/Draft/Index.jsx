import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import ConfirmDialog from '@/Components/ui/ConfirmDialog';
import StatusBadge from '@/Components/ui/StatusBadge';
import { formatDisplayDate } from '@/lib/utils';
import { NotepadText, Delete } from 'lucide-react';

export default function DraftIndex({ drafts }) {
  const [deleteTarget, setDeleteTarget] = useState(null);
  const [publishing, setPublishing] = useState(null);

  function handlePublish(id) {
    setPublishing(id);
    router.post(route('cases.publish', id), {}, {
      onFinish: () => setPublishing(null),
    });
  }

  function handleDelete(id) {
    router.delete(route('cases.drafts.destroy', id), {
      preserveScroll: true,
      onSuccess: () => setDeleteTarget(null),
    });
  }

  return (
    <AppLayout title="My Drafts">
      <Head title="My Drafts" />

      <div className="max-w-5xl mx-auto pb-6">
        <header className="flex items-center justify-between mb-6">
          <div>
            <h1 className="text-2xl font-extrabold text-slate-900">My Drafts</h1>
            <p className="text-sm text-slate-500 mt-0.5">
              Draft cases saved but not yet submitted. Only visible to you.
            </p>
          </div>
          <button
            onClick={() => router.visit(route('cases.create'))}
            className="px-4 py-2 bg-blue-900 text-white rounded-lg text-[13px] font-bold hover:bg-blue-800 transition-colors"
          >
            + New Case
          </button>
        </header>

        <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
          {drafts.data.length === 0 ? (
            <div className="p-12 text-center">
              <div className="mx-auto w-16 h-16 bg-amber-50 rounded-full flex items-center justify-center mb-4">
                <NotepadText className="w-8 h-8 text-amber-400" />
              </div>
              <h3 className="text-base font-bold text-slate-700 mb-1">No Drafts Yet</h3>
              <p className="text-sm text-slate-500 max-w-sm mx-auto">
                When you start creating a case and save it as a draft, it will appear here. Only you can see your drafts.
              </p>
              <button
                onClick={() => router.visit(route('cases.create'))}
                className="mt-4 px-4 py-2 bg-blue-900 text-white rounded-lg text-[13px] font-bold hover:bg-blue-800 transition-colors"
              >
                Create a Case
              </button>
            </div>
          ) : (
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="bg-slate-50/50">
                  <th className="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-500">Case #</th>
                  <th className="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-500">Client</th>
                  <th className="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-500">Type</th>
                  <th className="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-500">Created</th>
                  <th className="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-500 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {drafts.data.map((draft) => (
                  <tr key={draft.id} className="hover:bg-slate-50 transition-colors">
                    <td className="px-4 py-3">
                      <button
                        onClick={() => router.visit(route('cases.show', draft.id))}
                        className="text-xs font-bold text-blue-900 hover:underline"
                      >
                        {draft.case_number}
                      </button>
                    </td>
                    <td className="px-4 py-3 text-xs text-slate-700">{draft.client_name}</td>
                    <td className="px-4 py-3">
                      <StatusBadge status={draft.client_type === 'OFW' ? 'OFW' : 'NOK'} />
                    </td>
                    <td className="px-4 py-3 text-xs text-slate-500">
                      {draft.created_at ? formatDisplayDate(draft.created_at) : '—'}
                    </td>
                    <td className="px-4 py-3 text-right">
                      <div className="flex items-center justify-end gap-2">
                        <button
                          onClick={() => handlePublish(draft.id)}
                          disabled={publishing === draft.id}
                          className="min-h-[28px] px-3 bg-blue-900 text-white hover:bg-blue-800 text-[11px] font-bold rounded-[3px] transition-colors disabled:opacity-60"
                        >
                          {publishing === draft.id ? '...' : 'Publish'}
                        </button>
                        <button
                          onClick={() => setDeleteTarget(draft.id)}
                          className="min-h-[28px] px-3 bg-white text-red-600 border border-red-200 hover:bg-red-50 text-[11px] font-bold rounded-[3px] transition-colors"
                        >
                          <Delete className="w-3.5 h-3.5" />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>

      <ConfirmDialog
        open={!!deleteTarget}
        title="Delete Draft?"
        message="This will permanently delete this draft case and all associated client data. This action cannot be undone."
        confirmLabel="Delete"
        cancelLabel="Cancel"
        tone="danger"
        onConfirm={() => handleDelete(deleteTarget)}
        onCancel={() => setDeleteTarget(null)}
      />
    </AppLayout>
  );
}
