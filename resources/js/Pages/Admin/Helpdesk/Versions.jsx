import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';

export default function Versions({ article, revisions }) {
  return (
    <AppLayout title="Article Versions">
      <Head title={`Versions: ${article.title}`} />

      <div className="mb-8 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Version History</h1>
          <p className="text-sm text-slate-500 mt-1">
            Revision history for "{article.title}"
          </p>
        </div>
        <Link
          href={route('admin.helpdesk.articles.edit', article.id)}
          className="rounded-md border border-slate-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 hover:bg-slate-100"
        >
          Back to Editor
        </Link>
      </div>

      <div className="rounded-lg border border-slate-200 bg-white shadow-sm">
        {revisions?.length > 0 ? (
          <div className="divide-y divide-slate-200">
            {revisions.map((revision, index) => (
              <div key={revision.id} className="flex items-start gap-4 px-6 py-4">
                <div className="flex flex-col items-center">
                  <div className={`flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold text-white ${
                    index === 0 ? 'bg-primary' : 'bg-slate-400'
                  }`}>
                    {revisions.length - index}
                  </div>
                  {index < revisions.length - 1 && (
                    <div className="mt-1 h-full w-px bg-slate-200" />
                  )}
                </div>
                <div className="min-w-0 flex-1">
                  <div className="flex items-center gap-2">
                    <h3 className="text-sm font-semibold text-slate-800">
                      {revision.editor?.name || 'Unknown'}
                    </h3>
                    <span className="text-xs text-slate-400">
                      {new Date(revision.created_at).toLocaleString('en-US', {
                        month: 'short',
                        day: 'numeric',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                      })}
                    </span>
                  </div>
                  {revision.edit_notes && (
                    <p className="mt-0.5 text-xs text-slate-500 italic">
                      "{revision.edit_notes}"
                    </p>
                  )}
                </div>
              </div>
            ))}
          </div>
        ) : (
          <div className="py-12 text-center">
            <span className="material-symbols-outlined text-4xl text-slate-300 mb-3">history</span>
            <p className="text-sm text-slate-500">No revision history available.</p>
          </div>
        )}
      </div>
    </AppLayout>
  );
}
