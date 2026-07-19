import { useState, useMemo } from 'react';
import { Head, useForm } from '@inertiajs/react';
import InputError from '@/Components/InputError';

const LIKERT_LABELS = {
  1: 'Strongly Disagree',
  2: 'Disagree',
  3: 'Neutral',
  4: 'Agree',
  5: 'Strongly Agree',
};

const LIKERT_FACES = {
  1: '😠',
  2: '🙁',
  3: '😐',
  4: '🙂',
  5: '😄',
};

const LIKERT_TEXT_COLORS = {
  1: 'text-red-600',
  2: 'text-orange-600',
  3: 'text-amber-600',
  4: 'text-emerald-600',
  5: 'text-green-600',
};

const LIKERT_HALO_COLORS = {
  1: 'bg-red-50',
  2: 'bg-orange-50',
  3: 'bg-amber-50',
  4: 'bg-emerald-50',
  5: 'bg-green-50',
};

function LikertInput({ value, onChange }) {
  return (
    <div className="grid grid-cols-3 sm:grid-cols-5 gap-1">
      {Object.entries(LIKERT_LABELS).map(([val, label]) => {
        const numVal = parseInt(val);
        const isActive = parseInt(value) === numVal;
        return (
          <button
            key={val}
            type="button"
            onClick={() => onChange(val)}
            className="flex flex-col items-center gap-1.5 rounded-lg px-1 py-2 text-center transition-transform hover:scale-105"
          >
            <span className={`flex h-11 w-11 items-center justify-center rounded-full text-2xl leading-none transition-colors duration-150 ${
              isActive ? LIKERT_HALO_COLORS[numVal] : ''
            }`}>
              {LIKERT_FACES[numVal]}
            </span>
            <span className={`text-[11px] leading-tight ${
              isActive ? `${LIKERT_TEXT_COLORS[numVal]} font-semibold` : 'text-slate-500 font-medium'
            }`}>
              {label}
            </span>
          </button>
        );
      })}
    </div>
  );
}

function RatingInput({ value, onChange }) {
  const [hovered, setHovered] = useState(null);
  const currentVal = parseInt(value) || 0;
  const displayVal = hovered ?? currentVal;

  const ratingLabels = {
    0: 'Tap a star to rate',
    1: 'Poor',
    2: 'Fair',
    3: 'Good',
    4: 'Very Good',
    5: 'Excellent',
  };

  return (
    // flex-col on narrow screens keeps the label from squeezing next to the
    // stars and wrapping; it drops to its own line instead.
    <div className="flex flex-col items-start gap-1 sm:flex-row sm:items-center sm:gap-3">
      <div className="flex items-center gap-1">
        {[1, 2, 3, 4, 5].map((star) => {
          const active = star <= displayVal;
          return (
            <button
              key={star}
              type="button"
              onClick={() => onChange(String(star))}
              onMouseEnter={() => setHovered(star)}
              onMouseLeave={() => setHovered(null)}
              className="flex-shrink-0 p-1 transition-transform hover:scale-110 active:scale-95 focus:outline-none"
              aria-label={`${star} star${star > 1 ? 's' : ''}`}
            >
              <span
                className={`material-symbols-outlined text-4xl leading-none transition-colors duration-150 ${
                  active ? 'text-amber-500' : 'text-slate-300'
                }`}
                style={{ fontVariationSettings: active ? "'FILL' 1" : "'FILL' 0" }}
              >
                star
              </span>
            </button>
          );
        })}
      </div>
      <span className={`text-sm ${value ? 'font-medium text-slate-600' : 'text-slate-400'}`}>
        {ratingLabels[currentVal]}
      </span>
    </div>
  );
}

function RadioInput({ options, value, onChange, name }) {
  return (
    <div className="space-y-2">
      {(options || []).map((option, i) => (
        <label key={i} className="flex items-center gap-3 cursor-pointer rounded-lg border border-slate-200 px-4 py-3 transition-colors hover:bg-slate-50 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
          <input
            type="radio"
            name={name}
            checked={value === option}
            onChange={() => onChange(option)}
            className="h-4 w-4 flex-shrink-0 border-slate-300 text-primary focus:ring-primary"
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
        <label key={i} className="flex items-center gap-3 cursor-pointer rounded-lg border border-slate-200 px-4 py-3 transition-colors hover:bg-slate-50 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
          <input
            type="checkbox"
            checked={selected.includes(option)}
            onChange={() => toggleOption(option)}
            className="h-4 w-4 flex-shrink-0 rounded border-slate-300 text-primary focus:ring-primary"
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
      className="w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 outline-none transition-colors placeholder:text-slate-400 focus:border-primary focus:bg-white focus:ring-2 focus:ring-primary/20"
    />
  );
}

function QuestionField({ question, answer, onAnswerChange, error, questionNumber }) {
  const renderInput = () => {
    switch (question.type) {
      case 'likert':
        return <LikertInput value={answer.answer} onChange={(v) => onAnswerChange({ answer: v })} />;
      case 'rating':
        return <RatingInput value={answer.answer} onChange={(v) => onAnswerChange({ answer: v })} />;
      case 'radio':
        return <RadioInput options={question.options} value={answer.answer} name={`radio-${question.id}`} onChange={(v) => onAnswerChange({ answer: v })} />;
      case 'checkbox':
        return <CheckboxInput options={question.options} value={answer.selected_options} onChange={(v) => onAnswerChange({ selected_options: v })} />;
      case 'text':
        return <TextInput value={answer.answer} onChange={(v) => onAnswerChange({ answer: v })} />;
      default:
        return <TextInput value={answer.answer} onChange={(v) => onAnswerChange({ answer: v })} />;
    }
  };

  return (
    <div className="border-b border-slate-100 px-5 py-6 last:border-b-0 sm:px-6">
      <div className="mb-4 flex items-start gap-3">
        <span className="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-white">
          {questionNumber}
        </span>
        <p className="text-sm font-medium text-slate-900 leading-relaxed">
          {question.label}
          {question.is_required && <span className="ml-1 text-red-500">*</span>}
        </p>
      </div>
      <div className="ml-0 sm:ml-10">
        {renderInput()}
      </div>
      {error && <InputError message={error} className="mt-2 ml-0 sm:ml-10" />}
    </div>
  );
}

export default function PublicForm({ invitation, surveyForm, questions }) {
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

  const allRequiredAnswered = useMemo(() => {
    return questions.every((q, i) => {
      if (!q.is_required) return true;
      const a = data.answers[i];
      if (q.type === 'checkbox') {
        return a.selected_options && a.selected_options.length > 0;
      }
      return a.answer !== null && a.answer !== undefined && String(a.answer).trim() !== '';
    });
  }, [questions, data.answers]);

  const handleSubmit = (e) => {
    e.preventDefault();
    post(window.location.pathname);
  };

  return (
    <>
      <Head title={surveyForm?.title || 'Survey'} />

      <div className="min-h-screen bg-slate-50">
        {/* Header */}
        <div className="bg-primary">
          <div className="mx-auto max-w-2xl px-4 py-8 sm:px-6 sm:py-10">
            <div className="flex items-center gap-3">
              <div className="flex h-11 w-11 items-center justify-center rounded-lg bg-white/15">
                <span className="material-symbols-outlined text-xl text-white">assignment</span>
              </div>
              <div>
                <h1 className="text-xl font-bold text-white sm:text-2xl font-serif">{surveyForm?.title || 'Client Survey'}</h1>
              </div>
            </div>
            {surveyForm?.description && (
              <p className="mt-3 text-sm text-white/80 leading-relaxed">{surveyForm.description}</p>
            )}
          </div>
        </div>

        <div className="mx-auto max-w-2xl space-y-5 px-4 py-6 sm:px-6 sm:py-8">
          {/* Client Details */}
          <div className="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div className="flex items-center gap-2 border-b border-slate-100 px-5 py-3 sm:px-6">
              <span className="material-symbols-outlined text-lg text-slate-400">person</span>
              <h2 className="text-xs font-bold uppercase tracking-[0.16em] text-slate-900">Client Details</h2>
            </div>
            <div className="grid gap-4 px-5 py-4 sm:px-6 sm:grid-cols-3">
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
            <div className="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
              <div className="flex items-center justify-between border-b border-slate-100 px-5 py-3 sm:px-6">
                <div className="flex items-center gap-2">
                  <span className="material-symbols-outlined text-lg text-slate-400">quiz</span>
                  <h2 className="text-xs font-bold uppercase tracking-[0.16em] text-slate-900">
                    Questions
                  </h2>
                </div>
                <span className="text-xs text-slate-500">{questions.length} item{questions.length !== 1 ? 's' : ''}</span>
              </div>

              {questions.map((question, index) => (
                <QuestionField
                  key={question.id}
                  question={question}
                  answer={data.answers[index]}
                  onAnswerChange={(changes) => updateAnswer(index, changes)}
                  error={errors[`answers.${index}.answer`] || errors[`answers.${index}.selected_options`]}
                  questionNumber={index + 1}
                />
              ))}
            </div>

            <InputError message={errors.answers} className="mt-2" />

            {/* Submit */}
            <div className="mt-5">
              <button
                type="submit"
                disabled={processing || !allRequiredAnswered}
                className="inline-flex w-full sm:w-auto items-center justify-center gap-2 rounded-lg bg-primary px-6 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-container disabled:cursor-not-allowed disabled:opacity-50"
              >
                {processing ? (
                  <>
                    <span className="material-symbols-outlined animate-spin text-lg">progress_activity</span>
                    Submitting...
                  </>
                ) : (
                  <>
                    <span className="material-symbols-outlined text-lg">send</span>
                    Submit Survey
                  </>
                )}
              </button>
            </div>
          </form>

          {/* Footer */}
          <div className="rounded-lg bg-slate-100 px-5 py-4 text-center">
            <p className="text-xs text-slate-500 leading-relaxed">
              Your response is confidential and helps us improve our services.
            </p>
          </div>
        </div>
      </div>
    </>
  );
}