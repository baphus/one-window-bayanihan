import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';

function formatDateTime(value) {
  if (!value) return '—';
  return new Date(value).toLocaleString();
}

function Field({ label, children }) {
  return (
    <div>
      <dt className="text-xs font-semibold uppercase tracking-wider text-slate-500">{label}</dt>
      <dd className="mt-1 text-sm text-slate-900 break-words">{children}</dd>
    </div>
  );
}

export default function Show({ log }) {
  const isFailed = log.status === 'failed';

  return (
    <AppLayout title="Email Detail">
      <Head title={`Email — ${log.subject}`} />

      <div className="max-w-3xl space-y-6">
        <div className="flex items-center justify-between gap-4">
          <h1 className="text-2xl md:text-3xl font-extrabold font-headline tracking-tight text-slate-900">Email Detail</h1>
          <Link href={route('admin.email-logs.index')} className="text-sm font-semibold text-slate-600 hover:text-slate-900">
            Back to logs
          </Link>
        </div>

        <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
          <dl className="grid gap-5 sm:grid-cols-2">
            <Field label="Recipient">{log.to_email}</Field>
            <Field label="Status">{isFailed ? 'Failed' : 'Sent'}</Field>
            <Field label="Subject">{log.subject}</Field>
            <Field label="Mailable">{log.mailable_type}</Field>
            <Field label="Queued">{formatDateTime(log.created_at)}</Field>
            <Field label="Sent">{formatDateTime(log.sent_at)}</Field>
            <Field label="Delivered">{formatDateTime(log.delivered_at)}</Field>
          </dl>
        </div>

        {isFailed && (
          <div className="rounded-lg border border-red-200 bg-red-50 p-6">
            <h2 className="text-sm font-semibold text-red-900">Why it failed</h2>
            {/* The provider's own message. This is the whole reason the page
                exists: it answers "the client never got it" without a shell. */}
            <pre className="mt-3 whitespace-pre-wrap break-words text-xs text-red-800">{log.error_message || 'No error message was recorded.'}</pre>
          </div>
        )}

        <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
          <h2 className="text-sm font-semibold text-slate-900">Provider delivery events</h2>
          <p className="mt-1 text-xs text-slate-400">
            Reported by the mail provider&apos;s webhook. A message can be accepted and still fail to arrive.
          </p>

          {(log.events ?? []).length === 0 ? (
            <p className="mt-4 text-sm text-slate-400">No delivery events have been reported.</p>
          ) : (
            <ul className="mt-4 divide-y divide-slate-100">
              {log.events.map((event) => (
                <li key={event.id} className="flex items-center justify-between gap-4 py-2.5">
                  <span className="text-sm font-medium text-slate-800">{event.event_type}</span>
                  <span className="text-xs text-slate-500">{formatDateTime(event.occurred_at)}</span>
                </li>
              ))}
            </ul>
          )}
        </div>
      </div>
    </AppLayout>
  );
}
