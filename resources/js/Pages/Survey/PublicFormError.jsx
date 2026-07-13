import { Head } from '@inertiajs/react';

export default function PublicFormError({ error }) {
  return (
    <>
      <Head title="Survey Unavailable" />

      <div className="min-h-screen bg-slate-50 flex items-center justify-center p-4">
        <div className="w-full max-w-md rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">
          <span className="material-symbols-outlined text-5xl text-red-400">error</span>
          <h1 className="mt-4 text-2xl font-bold text-slate-900">Survey Unavailable</h1>
          <p className="mt-2 text-sm text-slate-600">{error}</p>
          <p className="mt-4 text-xs text-slate-500">
            If you believe this is an error, please contact the agency that sent you this link.
          </p>
        </div>
      </div>
    </>
  );
}
