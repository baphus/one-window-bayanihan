import { useState } from 'react';
import { router } from '@inertiajs/react';

export default function FeedbackWidget({ articleId }) {
  const [submitted, setSubmitted] = useState(false);
  const [helpful, setHelpful] = useState(null);
  const [comment, setComment] = useState('');
  const [showComment, setShowComment] = useState(false);

  const handleFeedback = (value) => {
    setHelpful(value);
    router.post(route('helpdesk.feedback'), {
      article_id: articleId,
      helpful: value,
    }, {
      onSuccess: () => {
        setSubmitted(true);
        if (!value) {
          setShowComment(true);
        }
      },
      preserveScroll: true,
    });
  };

  const submitComment = () => {
    if (!comment.trim()) return;
    router.post(route('helpdesk.feedback'), {
      article_id: articleId,
      helpful: false,
      comment: comment.trim(),
    }, {
      onSuccess: () => {
        setShowComment(false);
      },
      preserveScroll: true,
    });
  };

  if (submitted && !showComment) {
    return (
      <div className="rounded-lg border border-green-200 bg-green-50 p-4 text-center">
        <p className="text-sm font-medium text-green-800">
          Thank you for your feedback!
        </p>
      </div>
    );
  }

  return (
    <div className="rounded-lg border border-slate-200 bg-slate-50 p-4">
      <p className="mb-3 text-center text-sm font-medium text-slate-700">
        Was this article helpful?
      </p>
      <div className="flex justify-center gap-3">
        <button
          onClick={() => handleFeedback(true)}
          className={`flex items-center gap-1.5 rounded-md px-4 py-2 text-xs font-semibold uppercase tracking-widest transition-colors ${
            helpful === true
              ? 'bg-green-600 text-white'
              : 'bg-white border border-slate-300 text-slate-700 hover:bg-slate-100'
          }`}
        >
          <span className="material-symbols-outlined text-sm">thumb_up</span>
          Yes
        </button>
        <button
          onClick={() => handleFeedback(false)}
          className={`flex items-center gap-1.5 rounded-md px-4 py-2 text-xs font-semibold uppercase tracking-widest transition-colors ${
            helpful === false
              ? 'bg-red-600 text-white'
              : 'bg-white border border-slate-300 text-slate-700 hover:bg-slate-100'
          }`}
        >
          <span className="material-symbols-outlined text-sm">thumb_down</span>
          No
        </button>
      </div>
      {showComment && (
        <div className="mt-4">
          <textarea
            value={comment}
            onChange={(e) => setComment(e.target.value)}
            placeholder="How can we improve this article?"
            rows={3}
            className="w-full rounded-md border border-slate-300 p-2.5 text-xs text-slate-700 placeholder-slate-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
          />
          <div className="mt-2 flex justify-end">
            <button
              onClick={submitComment}
              disabled={!comment.trim()}
              className="rounded-md bg-primary px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white disabled:opacity-50"
            >
              Submit
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
