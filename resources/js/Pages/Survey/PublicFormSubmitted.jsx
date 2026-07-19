import { Head } from '@inertiajs/react';

export default function PublicFormSubmitted({ message }) {
  return (
    <>
      <Head title="Feedback Submitted" />

      <div className="min-h-screen bg-slate-50">
        {/* Header bar */}
        <div className="bg-primary">
          <div className="mx-auto max-w-lg px-4 py-6 sm:px-6">
            <div className="flex items-center gap-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-white/15">
                <span className="material-symbols-outlined text-lg text-white">assignment</span>
              </div>
              <h1 className="text-lg font-bold text-white font-serif">Client Survey</h1>
            </div>
          </div>
        </div>

        {/* Success card */}
        <div className="mx-auto max-w-lg px-4 py-10 sm:px-6">
          <div className="rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm sm:p-10">
            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100">
              <span className="material-symbols-outlined text-4xl text-emerald-600">check_circle</span>
            </div>
            <h2 className="mt-5 text-2xl font-bold text-slate-900">Feedback Submitted</h2>
            <p className="mt-2 text-sm text-slate-600 leading-relaxed">
              {message || 'Thank you for taking the time to share your feedback. Your response has been recorded.'}
            </p>
            <div className="mt-6 rounded-lg bg-slate-50 px-5 py-4">
              <p className="text-xs text-slate-500 leading-relaxed">
                Your feedback is confidential and will be used to improve the quality of services provided by our partner agencies.
              </p>
            </div>
          </div>
        </div>
      </div>
    </>
  );
}
