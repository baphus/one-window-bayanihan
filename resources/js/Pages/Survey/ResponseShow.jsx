import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import { formatDisplayDate } from '@/lib/utils';

const LIKERT_LABELS = {
  1: 'Strongly Disagree',
  2: 'Disagree',
  3: 'Neutral',
  4: 'Agree',
  5: 'Strongly Agree',
};

function ResponseItem({ response }) {
  const question = response.question;

  const renderAnswer = () => {
    if (!question) {
      return <p className="text-sm text-slate-500 italic">Question has been removed</p>;
    }

    switch (question.type) {
      case 'likert':
        return (
          <div className="flex items-center gap-2">
            <span className="inline-flex h-7 w-7 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-800">
              {response.answer}
            </span>
            <span className="text-sm text-slate-700">{LIKERT_LABELS[response.answer] || response.answer}</span>
          </div>
        );

      case 'rating':
        return (
          <div className="flex items-center gap-1">
            {[1, 2, 3, 4, 5].map((star) => (
              <span
                key={star}
                className={`text-lg ${parseInt(response.answer) >= star ? 'text-amber-500' : 'text-slate-300'}`}
              >
                ★
              </span>
            ))}
            <span className="ml-2 text-sm text-slate-600">({response.answer}/5)</span>
          </div>
        );

      case 'text':
        return (
          <p className="text-sm text-slate-700 whitespace-pre-wrap">{response.answer || <span className="italic text-slate-400">No response</span>}</p>
        );

      case 'radio':
        return (
          <span className="inline-flex rounded-full border border-slate-200 bg-slate-100 px-3 py-1 text-sm text-slate-700">
            {response.answer || <span className="italic text-slate-400">No selection</span>}
          </span>
        );

      case 'checkbox':
        return (
          <div className="flex flex-wrap gap-2">
            {(response.selected_options || []).length > 0 ? (
              response.selected_options.map((opt, i) => (
                <span key={i} className="inline-flex rounded-full border border-slate-200 bg-slate-100 px-3 py-1 text-sm text-slate-700">
                  {opt}
                </span>
              ))
            ) : (
              <span className="text-sm italic text-slate-400">No selections</span>
            )}
          </div>
        );

      default:
        return <p className="text-sm text-slate-700">{response.answer || '—'}</p>;
    }
  };

  return (
    <div className="border-b border-slate-100 px-5 py-4 last:border-b-0">
      <div className="flex items-start gap-3">
        <div className="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">
          {question?.order != null ? question.order + 1 : '—'}
        </div>
        <div className="min-w-0 flex-1">
          <p className="text-sm font-medium text-slate-900">
            {question?.label || 'Unknown question'}
            {question?.is_required && <span className="ml-1 text-red-500">*</span>}
          </p>
          <p className="mt-0.5 text-xs text-slate-500 capitalize">{question?.type || 'unknown'}</p>
          <div className="mt-2">{renderAnswer()}</div>
        </div>
      </div>
    </div>
  );
}

export default function ResponseShow({ invitation }) {
  const responses = invitation.responses || [];

  return (
    <AppLayout title="Survey Response">
      <Head title="Survey Response" />

      <div className="space-y-6">
        <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
          <div className="flex flex-col gap-3 px-5 py-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <p className="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Survey Response</p>
              <h1 className="mt-2 text-2xl font-bold tracking-tight text-slate-900">
                {invitation.client_name}'s Response
              </h1>
              <p className="mt-1 text-sm text-slate-500">
                {invitation.survey_form?.title || 'Survey'} • Submitted {formatDisplayDate(invitation.submitted_at)}
              </p>
            </div>
            <Link
              href={route('survey.responses.index')}
              className="inline-flex h-10 items-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50"
            >
              Back to responses
            </Link>
          </div>
        </section>

        {/* Client & Service Info */}
        <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
          <div className="border-b border-slate-100 px-5 py-4">
            <h2 className="text-sm font-bold uppercase tracking-[0.16em] text-slate-900">Details</h2>
          </div>
          <div className="grid gap-4 px-5 py-5 md:grid-cols-4">
            <div>
              <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Client Name</p>
              <p className="mt-1 text-sm font-medium text-slate-900">{invitation.client_name}</p>
            </div>
            <div>
              <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Service</p>
              <p className="mt-1 text-sm font-medium text-slate-900">{invitation.service_name}</p>
            </div>
            <div>
              <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Agency</p>
              <p className="mt-1 text-sm font-medium text-slate-900">{invitation.agency?.name || '—'}</p>
            </div>
            <div>
              <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Date of Evaluation</p>
              <p className="mt-1 text-sm font-medium text-slate-900">{formatDisplayDate(invitation.submitted_at)}</p>
            </div>
          </div>
        </section>

        {/* Responses */}
        <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
          <div className="border-b border-slate-100 px-5 py-4">
            <h2 className="text-sm font-bold uppercase tracking-[0.16em] text-slate-900">
              Answers ({responses.length})
            </h2>
          </div>
          {responses.length === 0 ? (
            <div className="px-5 py-8 text-center text-sm text-slate-500">
              No responses recorded.
            </div>
          ) : (
            <div>
              {responses.map((response) => (
                <ResponseItem key={response.id} response={response} />
              ))}
            </div>
          )}
        </section>
      </div>
    </AppLayout>
  );
}
