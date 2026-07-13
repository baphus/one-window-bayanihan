import { useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import InputError from '@/Components/InputError';
import { FlashMessageWatcher } from '@/Components/ToastProvider';
import { useToast } from '@/Hooks/useToast';

const LIKERT_LABELS = {
  1: 'Strongly Disagree',
  2: 'Disagree',
  3: 'Neutral',
  4: 'Agree',
  5: 'Strongly Agree',
};

function LikertInput({ value, onChange }) {
  return (
    <div className="flex flex-wrap gap-2">
      {Object.entries(LIKERT_LABELS).map(([val, label]) => {
        const numVal = parseInt(val);
        const isActive = parseInt(value) === numVal;
        return (
          <button
            key={val}
            type="button"
            onClick={() => onChange(val)}
            className={`rounded-lg border px-3 py-2 text-sm font-medium transition-colors ${
              isActive
                ? 'border-blue-900 bg-blue-900 text-white'
                : 'border-slate-300 bg-white text-slate-700 hover:border-blue-300 hover:bg-blue-50'
            }`}
          >
            {label}
          </button>
        );
      })}
    </div>
  );
}

function RatingInput({ value, onChange }) {
  const [hovered, setHovered] = useState(null);

  return (
    <div className="flex gap-1">
      {[1, 2, 3, 4, 5].map((star) => {
        const active = star <= (hovered ?? (parseInt(value) || 0));
        return (
          <button
            key={star}
            type="button"
            onClick={() => onChange(String(star))}
            onMouseEnter={() => setHovered(star)}
            onMouseLeave={() => setHovered(null)}
            className="focus:outline-none transition-transform hover:scale-110"
            aria-label={`${star} star${star > 1 ? 's' : ''}`}
          >
            <span className={`text-3xl ${active ? 'text-amber-500' : 'text-slate-300'}`}>★</span>
          </button>
        );
      })}
      {value && <span className="ml-2 self-center text-sm text-slate-600">({value}/5)</span>}
    </div>
  );
}

function RadioInput({ options, value, onChange }) {
  return (
    <div className="space-y-2">
      {(options || []).map((option, i) => (
        <label key={i} className="flex items-center gap-3 cursor-pointer">
          <input
            type="radio"
            name={`radio-${i}`}
            checked={value === option}
            onChange={() => onChange(option)}
            className="h-4 w-4 border-slate-300 text-blue-900 focus:ring-blue-900"
          />
          <span className="text-sm text-slate-700">{option}</span>
        </label>
      ))}
    </div>
  );
}

function CheckboxInput({ options, value, onChange }) {
  const selected = value || [];

  const toggleOption = (option) => {
    if (selected.includes(option)) {
      onChange(selected.filter((o) => o !== option));
    } else {
      onChange([...selected, option]);
    }
  };

  return (
    <div className="space-y-2">
      {(options || []).map((option, i) => (
        <label key={i} className="flex items-center gap-3 cursor-pointer">
          <input
            type="checkbox"
            checked={selected.includes(option)}
            onChange={() => toggleOption(option)}
            className="h-4 w-4 rounded border-slate-300 text-blue-900 focus:ring-blue-900"
          />
          <span className="text-sm text-slate-700">{option}</span>
        </label>
      ))}
    </div>
  );
}

function TextInput({ value, onChange }) {
  return (
    <textarea
      value={value || ''}
      onChange={(e) => onChange(e.target.value)}
      rows={3}
      maxLength={2000}
      placeholder="Type your answer here..."
      className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700 outline-none focus:border-blue-900 focus:ring-1 focus:ring-blue-900"
    />
  );
}

function QuestionField({ question, answer, onAnswerChange, error }) {
  const renderInput = () => {
    switch (question.type) {
      case 'likert':
        return <LikertInput value={answer.answer} onChange={(v) => onAnswerChange({ answer: v })} />;
      case 'rating':
        return <RatingInput value={answer.answer} onChange={(v) => onAnswerChange({ answer: v })} />;
      case 'radio':
        return <RadioInput options={question.options} value={answer.answer} onChange={(v) => onAnswerChange({ answer: v })} />;
      case 'checkbox':
        return <CheckboxInput options={question.options} value={answer.selected_options} onChange={(v) => onAnswerChange({ selected_options: v })} />;
      case 'text':
        return <TextInput value={answer.answer} onChange={(v) => onAnswerChange({ answer: v })} />;
      default:
        return <TextInput value={answer.answer} onChange={(v) => onAnswerChange({ answer: v })} />;
    }
  };

  return (
    <div className="border-b border-slate-100 px-6 py-5 last:border-b-0">
      <div className="mb-3">
        <p className="text-sm font-medium text-slate-900">
          {question.label}
          {question.is_required && <span className="ml-1 text-red-500">*</span>}
        </p>
      </div>
      {renderInput()}
      {error && <InputError message={error} className="mt-2" />}
    </div>
  );
}

export default function PublicForm({ invitation, surveyForm, questions }) {
  const { props } = usePage();
  const toast = useToast();
  const today = new Date().toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' });

  // Build initial answers state
  const initialAnswers = questions.map((q) => ({
    question_id: q.id,
    answer: null,
    selected_options: q.type === 'checkbox' ? [] : null,
  }));

  const { data, setData, post, processing, errors } = useForm({
    answers: initialAnswers,
  });

  const updateAnswer = (index, changes) => {
    const updated = [...data.answers];
    updated[index] = { ...updated[index], ...changes };
    setData('answers', updated);
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    post(route('survey.public.submit', invitation.token));
  };

  // Check if already submitted (flash success)
  if (props.flash?.success) {
    return (
      <>
        <Head title="Survey Submitted" />
        <div className="min-h-screen bg-slate-50 flex items-center justify-center p-4">
          <div className="w-full max-w-lg rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">
            <span className="material-symbols-outlined text-5xl text-emerald-500">check_circle</span>
            <h1 className="mt-4 text-2xl font-bold text-slate-900">Thank You!</h1>
            <p className="mt-2 text-sm text-slate-600">{props.flash.success}</p>
          </div>
        </div>
      </>
    );
  }

  return (
    <>
      <Head title={surveyForm?.title || 'Survey'} />
      <FlashMessageWatcher />

      <div className="min-h-screen bg-slate-50 py-8 px-4">
        <div className="mx-auto max-w-2xl space-y-6">
          {/* Header - Agency & Form Title */}
          <div className="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div className="h-2 bg-blue-900" />
            <div className="px-6 py-6">
              <h1 className="text-2xl font-bold text-slate-900">{surveyForm?.title || 'Client Survey'}</h1>
              {surveyForm?.description && (
                <p className="mt-2 text-sm text-slate-600">{surveyForm.description}</p>
              )}
            </div>
          </div>

          {/* Client Details (auto-filled, read-only) */}
          <div className="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div className="border-b border-slate-100 px-6 py-4">
              <h2 className="text-sm font-bold uppercase tracking-[0.16em] text-slate-900">Client Details</h2>
            </div>
            <div className="grid gap-4 px-6 py-5 md:grid-cols-3">
              <div>
                <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Client Name</p>
                <p className="mt-1 text-sm font-medium text-slate-900">{invitation.client_name}</p>
              </div>
              <div>
                <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Service</p>
                <p className="mt-1 text-sm font-medium text-slate-900">{invitation.service_name}</p>
              </div>
              <div>
                <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Date of Evaluation</p>
                <p className="mt-1 text-sm font-medium text-slate-900">{today}</p>
              </div>
            </div>
          </div>

          {/* Survey Questions */}
          <form onSubmit={handleSubmit}>
            <div className="rounded-xl border border-slate-200 bg-white shadow-sm">
              <div className="border-b border-slate-100 px-6 py-4">
                <h2 className="text-sm font-bold uppercase tracking-[0.16em] text-slate-900">
                  Questions ({questions.length})
                </h2>
              </div>

              {questions.map((question, index) => (
                <QuestionField
                  key={question.id}
                  question={question}
                  answer={data.answers[index]}
                  onAnswerChange={(changes) => updateAnswer(index, changes)}
                  error={errors[`answers.${index}.answer`] || errors[`answers.${index}.selected_options`]}
                />
              ))}
            </div>

            <InputError message={errors.answers} className="mt-2" />

            {/* Submit */}
            <div className="mt-6 flex justify-end">
              <button
                type="submit"
                disabled={processing}
                className="inline-flex h-11 items-center rounded-md bg-blue-900 px-6 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50"
              >
                {processing ? 'Submitting...' : 'Submit Survey'}
              </button>
            </div>
          </form>

          {/* Footer */}
          <p className="text-center text-xs text-slate-500">
            Your response is confidential and helps us improve our services.
          </p>
        </div>
      </div>
    </>
  );
}
