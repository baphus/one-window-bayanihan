import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { formatDisplayDate } from '@/lib/utils';
import ConfirmDialog from '@/Components/ui/ConfirmDialog';

export default function FormIndex({ forms = [] }) {
  const [deleteTarget, setDeleteTarget] = useState(null);
  const [deleting, setDeleting] = useState(false);
  const [activatingId, setActivatingId] = useState(null);

  const handleDelete = () => {
    if (!deleteTarget) return;
    setDeleting(true);
    router.delete(route('survey.forms.destroy', deleteTarget.id), {
      preserveScroll: true,
      onSuccess: () => setDeleteTarget(null),
      onError: () => setDeleteTarget(null),
      onFinish: () => setDeleting(false),
    });
  };

  const handleActivate = (form) => {
    setActivatingId(form.id);
    router.patch(route('survey.forms.activate', form.id), {}, {
      preserveScroll: true,
      onFinish: () => setActivatingId(null),
    });
  };

  return (
    <AppLayout title="Survey Forms">
      <Head title="Survey Forms" />

      <div className="space-y-6">
        <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
          <div data-tour="survey-forms-header" className="flex flex-col gap-3 px-5 py-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <p className="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Surveys</p>
              <h1 className="mt-2 text-2xl font-bold tracking-tight text-slate-900">Survey Forms</h1>
              <p className="mt-1 text-sm text-slate-500">
                Create and manage survey questionnaires sent to clients after service completion.
              </p>
            </div>
            <Link
              data-tour="survey-forms-create"
              href={route('survey.forms.create')}
              className="inline-flex h-10 items-center rounded-md bg-blue-900 px-4 text-sm font-semibold text-white hover:bg-blue-800"
            >
              + Create Survey Form
            </Link>
          </div>
        </section>

        {forms.length === 0 ? (
          <section data-tour="survey-forms-list" className="rounded-xl border border-slate-200 bg-white p-10 text-center shadow-sm">
            <div className="mx-auto max-w-sm">
              <span className="material-symbols-outlined text-5xl text-slate-300">assignment</span>
              <p className="mt-3 text-sm text-slate-500">No survey forms yet. Create one to start collecting client feedback.</p>
              <Link
                href={route('survey.forms.create')}
                className="mt-4 inline-flex h-10 items-center rounded-md bg-blue-900 px-4 text-sm font-semibold text-white hover:bg-blue-800"
              >
                Create your first form
              </Link>
            </div>
          </section>
        ) : (
          <section data-tour="survey-forms-list" className="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div className="border-b border-slate-100 px-5 py-4">
              <h2 className="text-sm font-bold uppercase tracking-[0.16em] text-slate-900">Your forms</h2>
            </div>
            <div className="overflow-hidden">
              <table className="min-w-full divide-y divide-slate-200">
                <thead className="bg-slate-50">
                  <tr>
                    <th className="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Title</th>
                    <th className="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Questions</th>
                    <th className="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Status</th>
                    <th className="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Created</th>
                    <th className="px-5 py-3 text-right text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 bg-white">
                  {forms.map((form) => (
                    <tr key={form.id} className="hover:bg-slate-50 transition-colors">
                      <td className="px-5 py-4 align-top">
                        <p className="text-sm font-semibold text-slate-900">{form.title}</p>
                        {form.description && (
                          <p className="mt-0.5 text-xs text-slate-500 line-clamp-1">{form.description}</p>
                        )}
                      </td>
                      <td className="px-5 py-4 align-top text-sm text-slate-600">
                        {form.questions_count} question{form.questions_count !== 1 ? 's' : ''}
                      </td>
                      <td className="px-5 py-4 align-top">
                        {form.is_active ? (
                          <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                            <span className="material-symbols-outlined text-[14px]">check_circle</span>
                            Active
                          </span>
                        ) : (
                          <span className="inline-flex rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                            Inactive
                          </span>
                        )}
                      </td>
                      <td className="px-5 py-4 align-top text-sm text-slate-600">
                        {formatDisplayDate(form.created_at)}
                      </td>
                      <td className="px-5 py-4 align-top">
                        <div className="flex flex-wrap justify-end gap-2">
                          {!form.is_active && (
                            <button
                              type="button"
                              disabled={activatingId === form.id}
                              onClick={() => handleActivate(form)}
                              className="inline-flex h-8 items-center rounded-md bg-blue-900 px-3 text-[11px] font-semibold text-white hover:bg-blue-800 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                              {activatingId === form.id ? 'Activating…' : 'Activate'}
                            </button>
                          )}
                          {form.is_active && (
                            <span className="inline-flex h-8 items-center rounded-md border border-emerald-200 bg-emerald-50 px-3 text-[11px] font-semibold text-emerald-700">
                              Current
                            </span>
                          )}
                          <Link
                            href={route('survey.forms.edit', form.id)}
                            className="inline-flex h-8 items-center rounded-md border border-slate-300 bg-white px-3 text-[11px] font-semibold text-slate-700 hover:bg-slate-50"
                          >
                            Edit
                          </Link>
                          <button
                            type="button"
                            onClick={() => setDeleteTarget(form)}
                            className="inline-flex h-8 items-center rounded-md border border-red-200 bg-white px-3 text-[11px] font-semibold text-red-700 hover:bg-red-50"
                          >
                            Delete
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>
        )}

        <ConfirmDialog
          open={!!deleteTarget}
          title="Delete Survey Form"
          message={deleteTarget
            ? `Are you sure you want to delete "${deleteTarget.title}"? This will remove the form and all its questions. This cannot be undone.${deleteTarget.is_active ? ' This is your active form — deleting it means no survey will be sent until you activate another form.' : ''}`
            : ''}
          confirmLabel="Delete"
          cancelLabel="Cancel"
          tone="danger"
          onConfirm={handleDelete}
          onCancel={() => setDeleteTarget(null)}
        />
      </div>
    </AppLayout>
  );
}
