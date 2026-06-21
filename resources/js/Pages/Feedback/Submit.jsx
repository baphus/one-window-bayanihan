import { useMemo, useState, useRef, useCallback } from 'react';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import InputError from '@/Components/InputError';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import useClientValidation from '@/Hooks/useClientValidation';
import { z } from 'zod';

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

const RATING_LABELS = {
  1: 'Very Low',
  2: 'Low',
  3: 'Moderate',
  4: 'High',
  5: 'Very High',
};

const surveySchema = z.object({
  tracking_token: z.string().min(1),
  servqual_responses: z
    .array(
      z.object({
        dimension: z.string(),
        question_text: z.string(),
        question: z.string(),
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
function ServqualRadioColumn({ selected, onChange, label }) {
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
          aria-label={`${label}: ${rating} - ${RATING_LABELS[rating]}`}
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
            {RATING_LABELS[rating]}
          </span>
        </button>
      ))}
    </div>
  );
}

export default function FeedbackSubmit({
  case_id,
  agency_id,
  referral_id,
  service_name,
  questions,
}) {
  const { url } = usePage();
  const [submitted, setSubmitted] = useState(false);
  const [submitError, setSubmitError] = useState(null);

  // Extract tracking token from URL query string
  const trackingToken = useMemo(() => {
    try {
      const query = url.split('?')[1] || '';
      return new URLSearchParams(query).get('tracking_token') || '';
    } catch {
      return '';
    }
  }, [url]);

  // Build initial SERVQUAL responses from questions prop
  const initialServqual = useMemo(
    () =>
      (questions || []).map((q) => ({
        dimension: q.dimension || '',
        question_text: q.question_text || q.question || '',
        question: q.question_text || q.question || '',
        expectation: null,
        perception: null,
      })),
    [questions],
  );

  const { data, setData, post, processing, errors, reset, setError, clearErrors } = useForm({
    tracking_token: trackingToken,
    servqual_responses: initialServqual,
    overall_rating: null,
    comments: '',
  });

  const { validate } = useClientValidation(surveySchema, data, setError);

  // --- Unsaved changes tracking ---
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

  // --- Helpers ---
  const updateServqual = useCallback(
    (index, field, value) => {
      setData('servqual_responses', (prev) => {
        const updated = [...prev];
        updated[index] = { ...updated[index], [field]: value };
        return updated;
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

  // --- Submit ---
  const handleSubmit = (e) => {
    e.preventDefault();
    setSubmitError(null);
    clearErrors();
    if (!validate()) return;

    post('/feedbacks/submit', {
      preserveScroll: true,
      onSuccess: () => setSubmitted(true),
      onError: (errs) => {
        if (typeof errs === 'string') {
          setSubmitError(errs);
        } else if (errs?.message) {
          setSubmitError(errs.message);
        }
      },
    });
  };

  // --- Validation helpers ---
  const hasIncompleteQuestions =
    data.servqual_responses.length > 0 &&
    data.servqual_responses.some(
      (r) => r.expectation == null || r.perception == null,
    );

  // ====================== RENDER ======================

  // Thank-you success screen
  if (submitted) {
    return (
      <GuestLayout>
        <Head title="Feedback Submitted" />

        <div className="text-center py-12 px-4">
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
          <h2 className="text-2xl font-bold text-gray-900 mb-2">
            Thank You for Your Feedback!
          </h2>
          <p className="text-gray-600 max-w-sm mx-auto text-sm leading-relaxed">
            Your responses have been recorded and will help us improve the
            quality of services we provide.
          </p>
        </div>
      </GuestLayout>
    );
  }

  // Missing tracking token — show error
  if (!trackingToken) {
    return (
      <GuestLayout>
        <Head title="Invalid Link" />

        <div className="text-center py-12 px-4">
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
          <h2 className="text-xl font-bold text-gray-900 mb-2">
            Invalid Feedback Link
          </h2>
          <p className="text-sm text-gray-600 max-w-xs mx-auto">
            This feedback link is missing or invalid. Please check the link you
            received in your email and try again.
          </p>
        </div>
      </GuestLayout>
    );
  }

  return (
    <GuestLayout>
      <Head title="Submit Feedback" />

      <UnsavedChangesModal
        show={showModal}
        onConfirm={confirmNavigation}
        onCancel={cancelNavigation}
      />

      <form onSubmit={handleSubmit} className="space-y-8">
        {/* ── Header ── */}
        <div className="text-center border-b border-gray-200 pb-5">
          <h1 className="text-xl font-bold text-gray-900">
            Share Your Feedback
          </h1>
          {service_name && (
            <p className="text-sm text-gray-500 mt-1">{service_name}</p>
          )}
        </div>

        {/* ── Instructions ── */}
        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-800">
          <p className="font-semibold mb-1.5">How to rate each question:</p>
          <ul className="list-disc list-inside space-y-0.5 text-blue-700 text-[13px]">
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

        {/* ── General error ── */}
        {submitError && (
          <div className="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-700">
            {submitError}
          </div>
        )}

        {/* ── SERVQUAL Questionnaire ── */}
        {questions?.length > 0 ? (
          <div className="space-y-8">
            {DIMENSION_ORDER.filter(
              (dim) => (groupedResponses[dim]?.length ?? 0) > 0,
            ).map((dimension) => (
              <div key={dimension}>
                {/* Dimension header */}
                <div className="mb-3 pb-2 border-b border-gray-100">
                  <h3 className="text-base font-semibold text-gray-900">
                    {dimension}
                  </h3>
                  <p className="text-xs text-gray-500 mt-0.5">
                    {DIMENSION_DESCRIPTIONS[dimension]}
                  </p>
                </div>

                {/* Column headers — visible only on sm+ */}
                <div className="hidden sm:grid sm:grid-cols-[1fr_auto_auto] sm:gap-4 mb-2 px-1">
                  <div />
                  <div className="text-center">
                    <span className="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">
                      Minimum<br />Expectation
                    </span>
                  </div>
                  <div className="text-center">
                    <span className="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">
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
                        className="sm:grid sm:grid-cols-[1fr_auto_auto] sm:gap-4 items-start p-3 rounded-lg hover:bg-gray-50 transition-colors"
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
        <div className="border-t border-gray-200 pt-6">
          <h3 className="text-base font-semibold text-gray-900 mb-1">
            Overall Rating
          </h3>
          <p className="text-xs text-gray-500 mb-3">
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
            className="block text-sm font-medium text-gray-700 mb-1"
          >
            Additional Comments{' '}
            <span className="text-gray-400 font-normal">(optional)</span>
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
        <div className="border-t border-gray-200 pt-5">
          <button
            type="submit"
            disabled={processing || hasIncompleteQuestions}
            className="w-full inline-flex items-center justify-center rounded-md border border-transparent bg-gray-800 px-6 py-3 text-sm font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 active:bg-gray-900 disabled:opacity-40 disabled:cursor-not-allowed"
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
    </GuestLayout>
  );
}
