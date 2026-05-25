import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import { formatDisplayDate } from '@/lib/utils';

function StarRating({ rating }) {
  return (
    <div className="flex gap-0.5">
      {[1, 2, 3, 4, 5].map((star) => (
        <svg key={star} className={`w-4 h-4 ${star <= rating ? 'text-yellow-400' : 'text-slate-200'}`} fill="currentColor" viewBox="0 0 20 20">
          <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
        </svg>
      ))}
    </div>
  );
}

export default function FeedbackIndex({ feedbacks }) {
  return (
    <AppLayout title="Feedbacks">
      <Head title="Feedbacks" />
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-slate-900">Client Feedbacks</h1>
        <p className="text-sm text-slate-500 mt-1">Feedback and satisfaction ratings from clients.</p>
      </div>

      <div className="grid grid-cols-1 gap-4">
        {feedbacks.data.length === 0 ? (
          <div className="rounded-lg bg-white shadow-sm border border-slate-200 p-6 text-center text-sm text-slate-500">
            No feedback records found.
          </div>
        ) : (
          feedbacks.data.map((fb) => (
            <div key={fb.id} className="rounded-lg bg-white shadow-sm border border-slate-200 p-6">
              <div className="flex items-start justify-between mb-4">
                <div>
                  <h3 className="text-base font-semibold text-slate-900">
                    {fb.case_file?.client
                      ? `${fb.case_file.client.first_name} ${fb.case_file.client.last_name}`
                      : 'Anonymous'}
                  </h3>
                  <p className="text-xs text-slate-500">
                    {fb.agency?.name ?? 'N/A'} &middot; {formatDisplayDate(fb.created_at)}
                  </p>
                </div>
                {fb.overall_rating && (
                  <StarRating rating={fb.overall_rating} />
                )}
              </div>
              {fb.comments && (
                <p className="text-sm text-slate-600 mb-3">{fb.comments}</p>
              )}
              {fb.servqual_responses?.length > 0 && (
                <div className="border-t border-slate-100 pt-3">
                  <p className="text-xs font-medium text-slate-500 uppercase tracking-wider mb-2">SERVQUAL Ratings</p>
                  <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2">
                    {fb.servqual_responses.map((sr) => (
                      <div key={sr.id} className="text-xs">
                        <span className="text-slate-500">{sr.dimension}:</span>{' '}
                        <span className="font-medium text-slate-900">{sr.rating}/5</span>
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>
          ))
        )}

        {feedbacks.last_page > 1 && (
          <div className="flex items-center justify-between mt-4">
            <div className="text-sm text-slate-700">
              Showing {feedbacks.from} to {feedbacks.to} of {feedbacks.total}
            </div>
            <div className="flex gap-2">
              {feedbacks.links.map((link, i) => (
                <Link
                  key={i}
                  href={link.url || '#'}
                  className={`inline-flex items-center rounded-md px-3 py-1 text-sm ${link.active ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700 hover:bg-slate-50'} ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                  dangerouslySetInnerHTML={{ __html: link.label }}
                />
              ))}
            </div>
          </div>
        )}
      </div>
    </AppLayout>
  );
}
