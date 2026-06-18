import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import { formatDisplayDate } from '@/lib/utils';

const DIMENSION_META = {
  Tangibles: { key: 'tangibles_avg', color: 'border-indigo-300 bg-indigo-50 text-indigo-800', label: 'Tangibles' },
  Reliability: { key: 'reliability_avg', color: 'border-emerald-300 bg-emerald-50 text-emerald-800', label: 'Reliability' },
  Responsiveness: { key: 'responsiveness_avg', color: 'border-amber-300 bg-amber-50 text-amber-800', label: 'Responsiveness' },
  Assurance: { key: 'assurance_avg', color: 'border-violet-300 bg-violet-50 text-violet-800', label: 'Assurance' },
  Empathy: { key: 'empathy_avg', color: 'border-rose-300 bg-rose-50 text-rose-800', label: 'Empathy' },
};

const DIMENSION_ORDER = ['Tangibles', 'Reliability', 'Responsiveness', 'Assurance', 'Empathy'];

function StarRating({ rating, size = 'md' }) {
  const starSize = size === 'lg' ? 'w-5 h-5' : 'w-4 h-4';
  return (
    <div className="flex gap-0.5">
      {[1, 2, 3, 4, 5].map((star) => (
        <svg
          key={star}
          className={`${starSize} ${star <= rating ? 'text-yellow-400' : 'text-slate-200'}`}
          fill="currentColor"
          viewBox="0 0 20 20"
        >
          <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
        </svg>
      ))}
    </div>
  );
}

function GapBadge({ gap }) {
  let bgColor = 'bg-slate-100 text-slate-600 border-slate-200';
  let label = `${gap >= 0 ? '+' : ''}${gap}`;

  if (gap > 0) {
    bgColor = 'bg-emerald-50 text-emerald-700 border-emerald-200';
  } else if (gap < 0) {
    bgColor = 'bg-red-50 text-red-700 border-red-200';
  } else {
    bgColor = 'bg-slate-100 text-slate-500 border-slate-200';
  }

  return (
    <span className={`inline-flex items-center rounded px-2 py-0.5 text-xs font-bold border ${bgColor}`}>
      {label}
    </span>
  );
}

function roundToTwo(n) {
  return Math.round(n * 100) / 100;
}

export default function FeedbackShow({ feedback }) {
  const da = feedback.dimension_averages ?? {};

  const groupedResponses = {};
  DIMENSION_ORDER.forEach((dim) => {
    const items = (feedback.servqual_responses ?? []).filter(
      (r) => r.dimension === dim
    );
    if (items.length > 0) groupedResponses[dim] = items;
  });

  return (
    <AppLayout title="Feedback Detail">
      <Head title="Feedback Detail" />

      {/* Breadcrumb */}
      <div className="text-xs font-medium text-slate-500 mb-4">
        <Link href={route('feedbacks.index')} className="hover:text-indigo-600 transition-colors">
          Feedbacks
        </Link>
        <span className="mx-2">&rsaquo;</span>
        <span className="text-slate-800">{feedback.client_name ?? 'Feedback Detail'}</span>
      </div>

      {/* Header */}
      <div className="flex items-start justify-between gap-4 flex-wrap mb-6">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Feedback Detail</h1>
          <p className="text-sm text-slate-500 mt-1">
            Submitted on {formatDisplayDate(feedback.created_at)}
          </p>
        </div>
        <Link
          href={route('feedbacks.index')}
          className="inline-flex items-center rounded-md bg-white px-3 py-1.5 text-sm font-medium text-slate-700 border border-slate-200 shadow-sm hover:bg-slate-50 transition-colors"
        >
          &larr; Back to Feedbacks
        </Link>
      </div>

      {/* Client / Agency / Service Info */}
      <div className="rounded-lg bg-white shadow-sm border border-slate-200 p-6 mb-6">
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-6">
          <div>
            <p className="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">Client</p>
            <p className="text-sm font-semibold text-slate-900">{feedback.client_name || 'Anonymous'}</p>
          </div>
          <div>
            <p className="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">Agency</p>
            <p className="text-sm font-semibold text-slate-900">{feedback.agency_name || 'N/A'}</p>
          </div>
          <div>
            <p className="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">Service</p>
            <p className="text-sm font-semibold text-slate-900">{feedback.service_name || 'N/A'}</p>
          </div>
        </div>

        {feedback.overall_rating && (
          <div className="mt-4 pt-4 border-t border-slate-100 flex items-center gap-3">
            <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Overall Rating</p>
            <StarRating rating={feedback.overall_rating} size="lg" />
            <span className="text-sm font-bold text-slate-700">{feedback.overall_rating}/5</span>
          </div>
        )}
      </div>

      {/* Dimension Averages */}
      <h2 className="text-base font-bold text-slate-900 mb-3">Dimension Averages</h2>
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3 mb-6">
        {DIMENSION_ORDER.map((dim) => {
          const meta = DIMENSION_META[dim];
          const avg = da[meta.key];
          return (
            <div
              key={dim}
              className={`rounded-lg border shadow-sm p-4 ${meta.color}`}
            >
              <p className="text-xs font-bold uppercase tracking-wider opacity-75">{meta.label}</p>
              <p className="mt-1 text-2xl font-black">
                {avg != null ? roundToTwo(avg) : '—'}
              </p>
              <p className="text-xs font-medium opacity-70 mt-0.5">/ 5</p>
            </div>
          );
        })}
      </div>

      {/* SERVQUAL Responses by Dimension */}
      {Object.keys(groupedResponses).length > 0 && (
        <div className="space-y-5 mb-6">
          <h2 className="text-base font-bold text-slate-900">SERVQUAL Responses</h2>
          {DIMENSION_ORDER.map((dim) => {
            const items = groupedResponses[dim];
            if (!items || items.length === 0) return null;
            const meta = DIMENSION_META[dim];
            const avg = da[meta.key];
            return (
              <div key={dim} className="rounded-lg bg-white shadow-sm border border-slate-200 overflow-hidden">
                <div className={`px-4 py-3 border-b ${meta.color.replace('bg-', 'border-').split(' ')[0]} ${meta.color.split(' ').slice(0, 2).join(' ')}`}>
                  <div className="flex items-center justify-between">
                    <h3 className="text-sm font-bold">{meta.label}</h3>
                    <span className="text-xs font-semibold opacity-75">
                      Avg: {avg != null ? roundToTwo(avg) : '—'}/5
                    </span>
                  </div>
                </div>
                <div className="divide-y divide-slate-100">
                  {items.map((sr) => {
                    const gap = sr.perception - sr.expectation;
                    return (
                      <div key={sr.id} className="px-4 py-3">
                        <p className="text-sm text-slate-700 mb-2 leading-relaxed">{sr.question_text}</p>
                        <div className="flex flex-wrap items-center gap-4 text-xs">
                          <div className="flex items-center gap-1.5">
                            <span className="font-medium text-slate-500">Expectation:</span>
                            <span className="font-bold text-slate-800">{sr.expectation}</span>
                          </div>
                          <div className="flex items-center gap-1.5">
                            <span className="font-medium text-slate-500">Perception:</span>
                            <span className="font-bold text-slate-800">{sr.perception}</span>
                          </div>
                          <div className="flex items-center gap-1.5">
                            <span className="font-medium text-slate-500">Gap:</span>
                            <GapBadge gap={gap} />
                          </div>
                        </div>
                      </div>
                    );
                  })}
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* Comments */}
      {feedback.comments && (
        <div className="rounded-lg bg-white shadow-sm border border-slate-200 p-6 mb-6">
          <h2 className="text-sm font-bold text-slate-900 mb-2">Client Comments</h2>
          <p className="text-sm text-slate-600 whitespace-pre-wrap leading-relaxed">{feedback.comments}</p>
        </div>
      )}

      {/* Back link footer */}
      <div className="pt-2">
        <Link
          href={route('feedbacks.index')}
          className="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-700 transition-colors"
        >
          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
          </svg>
          Back to Feedbacks
        </Link>
      </div>
    </AppLayout>
  );
}
