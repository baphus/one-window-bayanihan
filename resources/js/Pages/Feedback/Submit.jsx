import { useMemo, useState, useRef, useCallback } from 'react';
import { Head, useForm, usePage, Link } from '@inertiajs/react';
import InputError from '@/Components/InputError';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import useClientValidation from '@/Hooks/useClientValidation';
import { z } from 'zod';
import { FlashMessageWatcher } from '@/Components/ToastProvider';
import { useToast } from '@/Hooks/useToast';

const DIMENSION_ORDER = [
  'Tangibles',
  'Reliability',
  'Responsiveness',
  'Assurance',
  'Empathy',
];

const DIMENSION_DESCRIPTIONS = {
  Tangibles: 'Physical facilities, equipment, and appearance of personnel',
  Reliability: 'Ability to perform the promised service dependably and accurately',
  Responsiveness: 'Willingness to help clients and provide prompt service',
  Assurance: 'Knowledge and courtesy of employees and their ability to inspire trust',
  Empathy: 'Caring, individualized attention the agency provides its clients',
};

const DEFAULT_RATING_LABELS = {
  1: 'Very Low',
  2: 'Low',
  3: 'Moderate',
  4: 'High',
  5: 'Very High',
};

const surveySchema = z.object({
  servqual_responses: z
    .array(
      z.object({
        dimension: z.string(),
        question_text: z.string(),
        expectation: z
          .number({
            required_error: 'Please rate your minimum expectation',
            invalid_type_error: 'Please rate your minimum expectation',
          })
          .min(1)
          .max(5),
        perception: z
          .number({
            required_error: 'Please rate your actual experience',
            invalid_type_error: 'Please rate your actual experience',
          })
          .min(1)
          .max(5),
      }),
    )
    .min(1),
  overall_rating: z.number().min(1).max(5).nullable().optional(),
  comments: z.string().max(1000).optional(),
});

/** Inline clickable star rating 1-5 with hover highlight */
function StarRating({ value, onChange }) {
  const [hovered, setHovered] = useState(null);

  return (
    <div className="flex gap-1">
      {[1, 2, 3, 4, 5].map((star) => {
        const active = star <= (hovered ?? value);
        return (
          <button
            key={star}
            type="button"
            onClick={() => onChange(star === value ? null : star)}
            onMouseEnter={() => setHovered(star)}
            onMouseLeave={() => setHovered(null)}
            className="focus:outline-none transition-transform hover:scale-110"
            aria-label={`${star} star${star > 1 ? 's' : ''}`}
          >
            <svg
              className={`w-8 h-8 ${
                active ? 'text-yellow-400' : 'text-gray-300'
              } transition-colors`}
              fill="currentColor"
              viewBox="0 0 20 20"
            >
              <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
            </svg>
          </button>
        );
      })}
    </div>
  );
}

/** Single 1-5 radio column for either Expectation or Perception */
function ServqualRadioColumn({ selected, onChange, label, ratingLabels }) {
  const labels = ratingLabels || DEFAULT_RATING_LABELS;

  return (
    <div className="flex items-center gap-1 sm:gap-1.5">
      {[1, 2, 3, 4, 5].map((rating) => (
        <button
          key={rating}
          type="button"
          onClick={() => onChange(rating === selected ? null : rating)}
          className={`flex flex-col items-center gap-0.5 p-0.5 sm:p-1 rounded-lg transition-all focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-1 ${
            selected === rating
              ? 'text-primary'
              : 'text-gray-400 hover:text-gray-600'
          }`}
          aria-label={`${label}: ${rating} - ${labels[rating]}`}
        >
          <span
            className={`w-7 h-7 sm:w-8 sm:h-8 rounded-full flex items-center justify-center text-xs font-bold border-2 transition-all ${
              selected === rating
                ? 'bg-primary text-white border-primary shadow-sm'
                : 'border-gray-300 bg-white hover:border-gray-400'
            }`}
          >
            {rating}
          </span>
          <span className="text-[9px] sm:text-[10px] leading-tight text-center whitespace-nowrap">
            {labels[rating]}
          </span>
        </button>
      ))}
    </div>
  );
}

export default function FeedbackSubmit({
  invitation,
  client_name: clientName,
  // Old-style fallback props
  tracking_token: oldTrackingToken,
  case_id,
  agency_id,
  referral_id,
  service_name,
  questions,
}) {
  const { url } = usePage();
  const [submitted, setSubmitted] = useState(false);

  // Resolve the token from invitation (new) or old-style prop or URL
  const token = useMemo(() => {
    if (invitation?.token) return invitation.token;
    if (oldTrackingToken) return oldTrackingToken;
    try {
      const query = url.split('?')[1] || '';
      return new URLSearchParams(query).get('tracking_token') || '';
    } catch {
      return '';
    }
  }, [invitation, oldTrackingToken, url]);

  // Derive questions from invitation form_snapshot or old-style questions prop
  const surveyQuestions = useMemo(() => {
    if (invitation?.form_snapshot?.length > 0) {
      return invitation.form_snapshot;
    }
    return questions || [];
  }, [invitation, questions]);

  // Derive rating labels from invitation or fallback to defaults
  const ratingLabels = useMemo(() => {
    if (invitation?.rating_labels?.length > 0) {
      // API returns array of {value, label}; convert to map {1: 'label', 2: 'label', ...}
      const map = {};
      invitation.rating_labels.forEach((rl) => {
        map[rl.value] = rl.label;
      });
      return map;
    }
    return DEFAULT_RATING_LABELS;
  }, [invitation]);

  // Build initial SERVQUAL responses from questions
  const initialServqual = useMemo(
    () =>
      (surveyQuestions || []).map((q) => ({
        dimension: q.dimension || '',
        question_text: q.question_text || q.question || '',
        expectation: null,
        perception: null,
      })),
    [surveyQuestions],
  );

  const { data, setData, post, processing, errors, setError, clearErrors } = useForm({
    servqual_responses: initialServqual,
    overall_rating: null,
    comments: '',
  });

  const { validate } = useClientValidation(surveySchema, data, setError);
  const toast = useToast();

  // Unsaved changes tracking
  const initialSnapshotRef = useRef(null);
  if (!initialSnapshotRef.current && initialServqual.length > 0) {
    initialSnapshotRef.current = {
      servqual_responses: initialServqual.map((r) => ({
        expectation: r.expectation,
        perception: r.perception,
      })),
      overall_rating: null,
      comments: '',
    };
  }

  const isDirty = useMemo(() => {
    const snap = initialSnapshotRef.current;
    if (!snap) return false;

    const sameServqual =
      data.servqual_responses.length === snap.servqual_responses.length &&
      data.servqual_responses.every(
        (r, i) =>
          r.expectation === snap.servqual_responses[i].expectation &&
          r.perception === snap.servqual_responses[i].perception,
      );
    const sameOverall = data.overall_rating === snap.overall_rating;
    const sameComments = data.comments === snap.comments;

    return !(sameServqual && sameOverall && sameComments);
  }, [data]);

  const { showModal, confirmNavigation, cancelNavigation } =
    useUnsavedChanges(isDirty);

  // Helpers
  const updateServqual = useCallback(
    (index, field, value) => {
      setData((prev) => {
        const updated = [...prev.servqual_responses];
        updated[index] = { ...updated[index], [field]: value };
        return { ...prev, servqual_responses: updated };
      });
    },
    [setData],
  );

  const getQuestionIndex = useCallback(
    (dimension, questionText) =>
      data.servqual_responses.findIndex(
        (r) => r.dimension === dimension && r.question_text === questionText,
      ),
    [data.servqual_responses],
  );

  // Group responses by dimension in canonical order
  const groupedResponses = useMemo(() => {
    const map = {};
    DIMENSION_ORDER.forEach((dim) => {
      map[dim] = [];
    });
    data.servqual_responses.forEach((r) => {
      if (map[r.dimension]) {
        map[r.dimension].push(r);
      }
    });
    return map;
  }, [data.servqual_responses]);

  // Submit
  const handleSubmit = (e) => {
    e.preventDefault();
    clearErrors();
    if (!validate()) return;

    const submitUrl = token ? `/feedback/${encodeURIComponent(token)}` : '/feedbacks/submit';

    post(submitUrl, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Thank you for your feedback!');
        setSubmitted(true);
      },
      onError: (errs) => {
        toast.error(typeof errs === 'string' ? errs : (errs?.message || 'Failed to submit feedback. Please try again.'));
      },
    });
  };

  // Validation helpers
  const hasIncompleteQuestions =
    data.servqual_responses.length > 0 &&
    data.servqual_responses.some(
      (r) => r.expectation == null || r.perception == null,
    );

  // ====================== RENDER ======================

  function PageWrapper({ children }) {
    return (
      <div className="flex min-h-screen flex-col items-center bg-slate-50 pt-8 sm:justify-center sm:pt-0">
        <FlashMessageWatcher />
        <Link href="/" className="mb-6 block">
          <img src="/logo.png" alt="One Window Bayanihan" className="h-16 w-auto" />
        </Link>
        <div className="w-full max-w-2xl bg-white px-6 py-6 sm:px-8 sm:py-8 shadow-sm border border-slate-200 sm:rounded-xl">
          {children}
        </div>
      </div>
    );
  }



  // Thank-you success screen
  if (submitted) {
    return (
      <PageWrapper>
        <Head title="Feedback Submitted" />

        <div className="text-center py-8 px-4">
          <div className="mx-auto mb-6 w-16 h-16 rounded-full bg-green-100 flex items-center justify-center">
            <svg
              className="w-8 h-8 text-green-600"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M5 13l4 4L19 7"
              />
            </svg>
          </div>
          <h2 className="text-2xl font-bold text-slate-900 mb-2">
            Thank You for Your Feedback!
          </h2>
          <p className="text-slate-500 max-w-sm mx-auto text-sm leading-relaxed">
            Your responses have been recorded and will help us improve the
            quality of services we provide.
          </p>
        </div>
      </PageWrapper>
    );
  }

  // Missing token — show error
  if (!token) {
    return (
      <PageWrapper>
        <Head title="Invalid Link" />

        <div className="text-center py-8 px-4">
          <div className="mx-auto mb-6 w-16 h-16 rounded-full bg-red-100 flex items-center justify-center">
            <svg
              className="w-8 h-8 text-red-600"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
          </div>
          <h2 className="text-xl font-bold text-slate-900 mb-2">
            Invalid Feedback Link
          </h2>
          <p className="text-sm text-slate-500 max-w-xs mx-auto">
            This feedback link is missing or invalid. Please check the link you
            received in your email and try again.
          </p>
        </div>
      </PageWrapper>
    );
  }

  const displayName = clientName || service_name || '';

  return (
    <PageWrapper>
      <Head title="Submit Feedback" />

      <UnsavedChangesModal
        show={showModal}
        onConfirm={confirmNavigation}
        onCancel={cancelNavigation}
      />

      <form onSubmit={handleSubmit} className="space-y-8">
        {/* ── Header ── */}
        <div className="text-center border-b border-slate-200 pb-5">
          <h1 className="text-xl font-bold text-slate-900">
            Share Your Feedback
          </h1>
          {displayName && (
            <p className="text-sm text-slate-500 mt-1">{displayName}</p>
          )}
        </div>

        {/* ── Instructions ── */}
        <div className="bg-[#0b5384]/5 border border-[#0b5384]/20 rounded-lg p-4 text-sm text-[#0b5384]">
          <p className="font-semibold mb-1.5">How to rate each question:</p>
          <ul className="list-disc list-inside space-y-0.5 text-[#0b5384]/80 text-[13px]">
            <li>
              <strong>Minimum Expectation</strong> — How important is this
              aspect to you?
            </li>
            <li>
              <strong>Actual Experience</strong> — How well did the agency
              perform?
            </li>
          </ul>
        </div>

        {/* ── SERVQUAL Questionnaire ── */}
        {surveyQuestions.length > 0 ? (
          <div className="space-y-8">
            {DIMENSION_ORDER.filter(
              (dim) => (groupedResponses[dim]?.length ?? 0) > 0,
            ).map((dimension) => (
              <div key={dimension}>
                {/* Dimension header */}
                <div className="mb-3 pb-2 border-b border-slate-200">
                  <h3 className="text-[11px] font-extrabold uppercase tracking-wider text-slate-600">
                    {dimension}
                  </h3>
                  <p className="text-xs text-slate-400 mt-0.5">
                    {DIMENSION_DESCRIPTIONS[dimension]}
                  </p>
                </div>

                {/* Column headers — visible only on sm+ */}
                <div className="hidden sm:grid sm:grid-cols-[1fr_minmax(14rem,auto)_minmax(14rem,auto)] sm:gap-4 mb-2 px-3">
                  <div />
                  <div className="flex items-center justify-center gap-1 sm:gap-1.5 w-full">
                    <span className="text-[11px] font-semibold text-slate-600 uppercase tracking-wider leading-tight text-center">
                      Minimum<br />Expectation
                    </span>
                  </div>
                  <div className="flex items-center justify-center gap-1 sm:gap-1.5 w-full">
                    <span className="text-[11px] font-semibold text-slate-600 uppercase tracking-wider leading-tight text-center">
                      Actual<br />Experience
                    </span>
                  </div>
                </div>

                {/* Question rows */}
                <div className="space-y-2">
                  {groupedResponses[dimension].map((response) => {
                    const globalIdx = getQuestionIndex(
                      dimension,
                      response.question_text,
                    );
                    if (globalIdx === -1) return null;

                    const expErr =
                      errors[
                        `servqual_responses.${globalIdx}.expectation`
                      ];
                    const perErr =
                      errors[
                        `servqual_responses.${globalIdx}.perception`
                      ];

                    return (
                      <div
                        key={globalIdx}
                        className="sm:grid sm:grid-cols-[1fr_minmax(14rem,auto)_minmax(14rem,auto)] sm:gap-4 items-start p-3 rounded-lg hover:bg-gray-50 transition-colors"
                      >
                        {/* Question text */}
                        <div className="text-sm text-gray-700 mb-2 sm:mb-0 sm:pt-1.5">
                          {response.question_text}
                        </div>

                        {/* Expectation column */}
                        <div className="mb-1.5 sm:mb-0">
                          <span className="sm:hidden text-[11px] font-medium text-gray-500 block mb-1">
                            Minimum Expectation
                          </span>
                          <ServqualRadioColumn
                            selected={response.expectation}
                            onChange={(val) =>
                              updateServqual(
                                globalIdx,
                                'expectation',
                                val,
                              )
                            }
                            label="Expectation"
                            ratingLabels={ratingLabels}
                          />
                          {expErr && (
                            <InputError
                              message={expErr}
                              className="mt-1"
                            />
                          )}
                        </div>

                        {/* Perception column */}
                        <div>
                          <span className="sm:hidden text-[11px] font-medium text-gray-500 block mb-1">
                            Actual Experience
                          </span>
                          <ServqualRadioColumn
                            selected={response.perception}
                            onChange={(val) =>
                              updateServqual(
                                globalIdx,
                                'perception',
                                val,
                              )
                            }
                            label="Perception"
                            ratingLabels={ratingLabels}
                          />
                          {perErr && (
                            <InputError
                              message={perErr}
                              className="mt-1"
                            />
                          )}
                        </div>
                      </div>
                    );
                  })}
                </div>
              </div>
            ))}
          </div>
        ) : (
          <p className="text-sm text-gray-500 text-center py-4">
            No survey questions are configured. Please contact the system
            administrator.
          </p>
        )}

        {/* ── Overall Rating ── */}
        <div className="border-t border-slate-200 pt-6">
          <h3 className="text-[11px] font-extrabold uppercase tracking-wider text-slate-600 mb-1">
            Overall Rating
          </h3>
          <p className="text-xs text-slate-400 mb-3">
            How would you rate your overall experience?
          </p>
          <StarRating
            value={data.overall_rating}
            onChange={(val) => setData('overall_rating', val)}
          />
          {errors.overall_rating && (
            <InputError message={errors.overall_rating} className="mt-2" />
          )}
        </div>

        {/* ── Comments ── */}
        <div>
          <label
            htmlFor="feedback-comments"
            className="block text-[11px] font-extrabold uppercase tracking-wider text-slate-600 mb-1.5"
          >
            Additional Comments{' '}
            <span className="text-slate-400 font-normal">(optional)</span>
          </label>
          <textarea
            id="feedback-comments"
            rows={4}
            value={data.comments}
            onChange={(e) => setData('comments', e.target.value)}
            placeholder="Share any additional thoughts about your experience..."
            maxLength={1000}
            className="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm resize-y"
          />
          <div className="flex justify-between items-start mt-1">
            {errors.comments ? (
              <InputError message={errors.comments} />
            ) : (
              <span />
            )}
            <span className="text-xs text-gray-400">
              {data.comments.length}/1000
            </span>
          </div>
        </div>

        {/* ── Submit ── */}
        <div className="border-t border-slate-200 pt-5">
          <button
            type="submit"
            disabled={processing || hasIncompleteQuestions}
            className="w-full inline-flex items-center justify-center rounded-md border border-transparent bg-[#0b5384] px-6 py-3 text-sm font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-[#09416a] focus:bg-[#09416a] focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 active:bg-[#073353] disabled:opacity-40 disabled:cursor-not-allowed"
          >
            {processing ? (
              <span className="flex items-center gap-2">
                <svg
                  className="animate-spin h-4 w-4 text-white"
                  xmlns="http://www.w3.org/2000/svg"
                  fill="none"
                  viewBox="0 0 24 24"
                >
                  <circle
                    className="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    strokeWidth="4"
                  />
                  <path
                    className="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                  />
                </svg>
                Submitting…
              </span>
            ) : (
              'Submit Feedback'
            )}
          </button>

          {hasIncompleteQuestions && (
            <p className="text-xs text-amber-600 text-center mt-2">
              Please rate all questions before submitting.
            </p>
          )}
        </div>
      </form>
    </PageWrapper>
  );
}
