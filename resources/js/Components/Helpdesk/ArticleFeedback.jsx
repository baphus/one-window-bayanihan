import { useEffect, useState } from 'react';
import { Link } from '@inertiajs/react';

const STORAGE_PREFIX = 'helpdesk-feedback:';

// Persistence is isolated here so a future backend endpoint can replace
// localStorage without touching the UI.
function readStoredVote(slug) {
  try {
    return window.localStorage.getItem(STORAGE_PREFIX + slug);
  } catch {
    return null;
  }
}

function storeVote(slug, vote) {
  try {
    window.localStorage.setItem(STORAGE_PREFIX + slug, vote);
  } catch {
    // Storage unavailable (private mode, quota) — the vote just won't persist.
  }
}

export default function ArticleFeedback({ slug }) {
  const [vote, setVote] = useState(null);

  useEffect(() => {
    setVote(readStoredVote(slug));
  }, [slug]);

  const handleVote = (value) => {
    storeVote(slug, value);
    setVote(value);
  };

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
      <div aria-live="polite">
        {vote === null ? (
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p className="font-headline text-base font-bold text-slate-900">Was this article helpful?</p>
            <div className="flex gap-2">
              <button
                type="button"
                onClick={() => handleVote('yes')}
                className="inline-flex items-center gap-2 rounded-none border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition-colors hover:border-primary/40 hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
              >
                <span className="material-symbols-outlined text-base" aria-hidden="true">thumb_up</span>
                Yes
              </button>
              <button
                type="button"
                onClick={() => handleVote('no')}
                className="inline-flex items-center gap-2 rounded-none border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition-colors hover:border-primary/40 hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
              >
                <span className="material-symbols-outlined text-base" aria-hidden="true">thumb_down</span>
                No
              </button>
            </div>
          </div>
        ) : vote === 'yes' ? (
          <p className="text-sm text-slate-600">
            <span className="font-semibold text-slate-900">Thank you for your feedback.</span> We're glad
            this article helped.
          </p>
        ) : (
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-sm text-slate-600">
              <span className="font-semibold text-slate-900">Thank you for your feedback.</span> Sorry this
              article didn't help — our support team can assist you directly.
            </p>
            <Link
              href={route('contact')}
              className="inline-flex flex-shrink-0 items-center justify-center rounded-none bg-primary px-4 py-2 font-label text-xs font-semibold uppercase tracking-[0.18em] text-white transition-colors hover:bg-[#00446f] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
            >
              Contact support
            </Link>
          </div>
        )}
      </div>
    </div>
  );
}
