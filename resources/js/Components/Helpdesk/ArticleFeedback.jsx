import { useEffect, useState } from 'react';
import { Link } from '@inertiajs/react';

export default function ArticleFeedback({ slug, vote: initialVote = null }) {
  const [vote, setVote] = useState(initialVote);
  const [pending, setPending] = useState(true);
  const [error, setError] = useState(null);

  const [ready, setReady] = useState(false);

  const initialize = async (signal) => {
    setPending(true);
    setError(null);
    try {
      const response = await fetch(route('helpdesk.feedback.show', { slug }), {
        credentials: 'same-origin', headers: { Accept: 'application/json' }, signal,
      });
      if (!response.ok) throw new Error('Feedback unavailable');
      const data = await response.json();
      if (signal?.aborted) return;
      setVote(data.vote);
      setReady(true);
    } catch (failure) {
      if (signal?.aborted) return;
      setError('Feedback is temporarily unavailable. Please retry.');
    } finally {
      if (!signal?.aborted) setPending(false);
    }
  };

  useEffect(() => {
    const controller = new AbortController();
    setVote(null);
    setReady(false);
    initialize(controller.signal);
    return () => controller.abort();
  }, [slug]);

  const handleVote = async (value) => {
    if (pending || !ready) return;
    setPending(true);
    setError(null);
    try {
      const response = await fetch(route('helpdesk.feedback.store', { slug }), {
        method: 'POST', credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ vote: value }),
      });
      if (!response.ok) throw new Error('Feedback could not be saved');
      const data = await response.json();
      if (data.vote !== value) throw new Error('Unexpected feedback response');
      setVote(data.vote);
    } catch {
      setError('Feedback could not be saved. Please try again.');
    } finally {
      setPending(false);
    }
  };

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
      <div aria-live="polite">
        {vote === null || pending || error ? (
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <p className="font-headline text-base font-bold text-slate-900">Was this article helpful?</p>
              {error && <p className="mt-1 text-sm text-red-700" role="alert">{error} {!ready && <button type="button" onClick={() => initialize()} disabled={pending} className="underline">Retry</button>}</p>}
            </div>
            <div className="flex gap-2">
              <button
                type="button"
                onClick={() => handleVote('yes')}
                disabled={pending || !ready}
                aria-pressed={vote === 'yes'}
                className="inline-flex items-center gap-2 rounded-none border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition-colors hover:border-primary/40 hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
              >
                <span className="material-symbols-outlined text-base" aria-hidden="true">thumb_up</span>
                Yes
              </button>
              <button
                type="button"
                onClick={() => handleVote('no')}
                disabled={pending || !ready}
                aria-pressed={vote === 'no'}
                className="inline-flex items-center gap-2 rounded-none border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition-colors hover:border-primary/40 hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
              >
                <span className="material-symbols-outlined text-base" aria-hidden="true">thumb_down</span>
                No
              </button>
            </div>
          </div>
        ) : vote === 'yes' ? (
          <div className="flex items-center justify-between gap-3">
            <p className="text-sm text-slate-600"><span className="font-semibold text-slate-900">Thank you for your feedback.</span> We're glad this article helped.</p>
            <button type="button" onClick={() => setVote(null)} className="text-sm font-semibold text-primary underline">Change vote</button>
          </div>
        ) : (
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <p className="text-sm text-slate-600"><span className="font-semibold text-slate-900">Thank you for your feedback.</span> Sorry this article didn't help — our support team can assist you directly.</p>
              <button type="button" onClick={() => setVote(null)} className="mt-2 text-sm font-semibold text-primary underline">Change vote</button>
            </div>
            <Link
              href={route('contact')}
              className="inline-flex flex-shrink-0 items-center justify-center rounded-none bg-primary px-4 py-2 font-label text-xs font-semibold uppercase tracking-[0.18em] text-white transition-colors hover:bg-primary-container focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
            >
              Contact support
            </Link>
          </div>
        )}
      </div>
    </div>
  );
}
