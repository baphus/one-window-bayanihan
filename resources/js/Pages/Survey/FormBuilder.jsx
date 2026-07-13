import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useRef } from 'react';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import InputError from '@/Components/InputError';

const QUESTION_TYPE_LABELS = {
  likert: 'Likert Scale (Strongly Disagree → Strongly Agree)',
  text: 'Text (Open-ended)',
  radio: 'Radio (Single Choice)',
  checkbox: 'Checkbox (Multiple Choice)',
  rating: 'Rating (1-5 Stars)',
};

const TYPE_DESCRIPTIONS = {
  likert: '5-point scale: Strongly Disagree, Disagree, Neutral, Agree, Strongly Agree',
  text: 'Free text response area',
  radio: 'Client selects one option from your custom list',
  checkbox: 'Client can select multiple options from your custom list',
  rating: '1–5 star rating',
};

function QuestionCard({ index, question, errors, onChange, onMoveUp, onMoveDown, onRemove, canMoveUp, canMoveDown, questionTypes }) {
  const needsOptions = question.type === 'radio' || question.type === 'checkbox';

  const addOption = () => {
    const current = question.options || [];
    onChange('options', [...current, '']);
  };

  const updateOption = (optIndex, value) => {
    const updated = [...(question.options || [])];
    updated[optIndex] = value;
    onChange('options', updated);
  };

  const removeOption = (optIndex) => {
    const updated = (question.options || []).filter((_, i) => i !== optIndex);
    onChange('options', updated);
  };

  return (
    <article className="rounded-xl border border-slate-200 bg-white shadow-sm">
      <div className="h-1 rounded-t-xl bg-blue-900/80" />
      <div className="p-5 sm:p-6">
        <div className="flex items-start gap-4">
          <div className="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-blue-900 text-sm font-semibold text-white">
            {index + 1}
          </div>

          <div className="min-w-0 flex-1 space-y-4">
            <div className="grid gap-4 lg:grid-cols-[200px,1fr]">
              <div>
                <label className="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">
                  Question Type
                </label>
                <select
                  value={question.type}
                  onChange={(e) => onChange('type', e.target.value)}
                  className={`h-10 w-full rounded-md border px-3 py-2 text-sm text-slate-700 outline-none focus:ring-1 ${
                    errors[`questions.${index}.type`]
                      ? 'border-red-400 focus:border-red-500 focus:ring-red-500'
                      : 'border-slate-300 focus:border-blue-900 focus:ring-blue-900'
                  }`}
                >
                  {questionTypes.map((type) => (
                    <option key={type} value={type}>
                      {QUESTION_TYPE_LABELS[type] || type}
                    </option>
                  ))}
                </select>
                <p className="mt-1.5 text-xs text-slate-500">{TYPE_DESCRIPTIONS[question.type]}</p>
                <InputError message={errors[`questions.${index}.type`]} className="mt-1.5" />
              </div>

              <div>
                <label className="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">
                  Question Text
                </label>
                <input
                  type="text"
                  value={question.label}
                  onChange={(e) => onChange('label', e.target.value)}
                  placeholder="e.g. How satisfied are you with the service?"
                  maxLength={500}
                  className={`h-10 w-full rounded-md border px-3 text-sm text-slate-700 outline-none focus:ring-1 ${
                    errors[`questions.${index}.label`]
                      ? 'border-red-400 focus:border-red-500 focus:ring-red-500'
                      : 'border-slate-300 focus:border-blue-900 focus:ring-blue-900'
                  }`}
                />
                <InputError message={errors[`questions.${index}.label`]} className="mt-1.5" />
              </div>
            </div>

            {needsOptions && (
              <div className="rounded-lg border border-slate-200 bg-slate-50 p-4">
                <label className="mb-2 block text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">
                  Options
                </label>
                <div className="space-y-2">
                  {(question.options || []).map((opt, optIndex) => (
                    <div key={optIndex} className="flex items-center gap-2">
                      <input
                        type="text"
                        value={opt}
                        onChange={(e) => updateOption(optIndex, e.target.value)}
                        placeholder={`Option ${optIndex + 1}`}
                        maxLength={255}
                        className="h-9 flex-1 rounded-md border border-slate-300 px-3 text-sm text-slate-700 outline-none focus:border-blue-900 focus:ring-1 focus:ring-blue-900"
                      />
                      <button
                        type="button"
                        onClick={() => removeOption(optIndex)}
                        className="inline-flex h-9 w-9 items-center justify-center rounded-md border border-red-200 text-red-600 hover:bg-red-50"
                        title="Remove option"
                      >
                        ×
                      </button>
                    </div>
                  ))}
                </div>
                <button
                  type="button"
                  onClick={addOption}
                  className="mt-2 inline-flex h-8 items-center rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                >
                  + Add option
                </button>
                <InputError message={errors[`questions.${index}.options`]} className="mt-1.5" />
              </div>
            )}

            <div className="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-4">
              <label className="flex items-center gap-2 text-sm text-slate-600">
                <input
                  type="checkbox"
                  checked={question.is_required !== false}
                  onChange={(e) => onChange('is_required', e.target.checked)}
                  className="h-4 w-4 rounded border-slate-300 text-blue-900 focus:ring-blue-900"
                />
                Required
              </label>

              <div className="ml-auto flex items-center gap-2">
                <button
                  type="button"
                  onClick={onMoveUp}
                  disabled={!canMoveUp}
                  className="inline-flex h-9 items-center rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-30"
                >
                  ↑ Move up
                </button>
                <button
                  type="button"
                  onClick={onMoveDown}
                  disabled={!canMoveDown}
                  className="inline-flex h-9 items-center rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-30"
                >
                  ↓ Move down
                </button>
                <button
                  type="button"
                  onClick={onRemove}
                  className="inline-flex h-9 items-center rounded-md border border-red-200 bg-white px-3 text-xs font-semibold text-red-700 hover:bg-red-50"
                >
                  Remove
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </article>
  );
}

export default function FormBuilder({ form, questionTypes, likertLabels }) {
  const isEditing = !!form;

  const buildInitialQuestions = () => {
    if (isEditing && form.questions?.length > 0) {
      return form.questions.map((q) => ({
        type: q.type,
        label: q.label,
        options: q.options || [],
        is_required: q.is_required ?? true,
        order: q.order ?? 0,
      }));
    }
    return [];
  };

  const initialQuestions = buildInitialQuestions();

  const { data, setData, post, patch, processing, errors } = useForm({
    title: form?.title ?? '',
    description: form?.description ?? '',
    questions: initialQuestions,
  });

  const initialRef = useRef({
    title: form?.title ?? '',
    description: form?.description ?? '',
    questions: initialQuestions,
  });

  const questionsDirty = JSON.stringify(data.questions) !== JSON.stringify(initialRef.current.questions);
  const dirty =
    data.title !== initialRef.current.title ||
    data.description !== initialRef.current.description ||
    questionsDirty;

  const { UnsavedModal } = useUnsavedChanges(dirty);

  const updateQuestion = (index, field, value) => {
    const updated = [...data.questions];
    updated[index] = { ...updated[index], [field]: value };
    setData('questions', updated);
  };

  const addQuestion = (type = 'likert') => {
    const newQuestion = {
      type,
      label: '',
      options: (type === 'radio' || type === 'checkbox') ? [''] : [],
      is_required: true,
      order: data.questions.length,
    };
    setData('questions', [...data.questions, newQuestion]);
  };

  const removeQuestion = (index) => {
    setData('questions', data.questions.filter((_, i) => i !== index));
  };

  const moveQuestion = (index, direction) => {
    const target = index + direction;
    if (target < 0 || target >= data.questions.length) return;
    const updated = [...data.questions];
    const temp = updated[index];
    updated[index] = updated[target];
    updated[target] = temp;
    setData('questions', updated);
  };

  const handleSubmit = (e) => {
    e.preventDefault();

    // Assign order numbers
    const questionsWithOrder = data.questions.map((q, i) => ({ ...q, order: i }));

    if (isEditing) {
      patch(route('survey.forms.update', form.id), {
        data: { ...data, questions: questionsWithOrder },
      });
    } else {
      post(route('survey.forms.store'), {
        data: { ...data, questions: questionsWithOrder },
      });
    }
  };

  return (
    <AppLayout title={isEditing ? 'Edit Survey Form' : 'Create Survey Form'}>
      <Head title={isEditing ? 'Edit Survey Form' : 'Create Survey Form'} />

      <div className="space-y-6">
        <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
          <div className="flex flex-col gap-3 px-5 py-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <p className="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Surveys</p>
              <h1 className="mt-2 text-2xl font-bold tracking-tight text-slate-900">
                {isEditing ? 'Edit Survey Form' : 'Create Survey Form'}
              </h1>
              <p className="mt-1 text-sm text-slate-500">
                Build your survey questionnaire. Add questions of different types that will be sent to clients after service completion.
              </p>
            </div>
            <Link
              href={route('survey.forms.index')}
              className="inline-flex h-10 items-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50"
            >
              Back to forms
            </Link>
          </div>
        </section>

        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Form Header */}
          <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div className="border-b border-slate-100 px-5 py-4">
              <h2 className="text-sm font-bold uppercase tracking-[0.16em] text-slate-900">Form details</h2>
              <p className="mt-1 text-sm text-slate-500">
                Give your form a title and optional description that will appear at the top of the survey.
              </p>
            </div>

            <div className="grid gap-5 px-5 py-5 lg:grid-cols-2">
              <div>
                <label htmlFor="title" className="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">
                  Form Title
                </label>
                <input
                  id="title"
                  type="text"
                  value={data.title}
                  onChange={(e) => setData('title', e.target.value)}
                  placeholder="e.g. Client Satisfaction Survey"
                  maxLength={255}
                  className={`h-10 w-full rounded-md border px-3 text-sm text-slate-700 outline-none focus:ring-1 ${
                    errors.title ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : 'border-slate-300 focus:border-blue-900 focus:ring-blue-900'
                  }`}
                />
                <InputError message={errors.title} className="mt-1.5" />
              </div>

              <div>
                <label htmlFor="description" className="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">
                  Description (optional)
                </label>
                <textarea
                  id="description"
                  value={data.description}
                  onChange={(e) => setData('description', e.target.value)}
                  placeholder="Brief instructions or description shown to the client"
                  maxLength={1000}
                  rows={3}
                  className={`w-full rounded-md border px-3 py-2 text-sm text-slate-700 outline-none focus:ring-1 ${
                    errors.description ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : 'border-slate-300 focus:border-blue-900 focus:ring-blue-900'
                  }`}
                />
                <InputError message={errors.description} className="mt-1.5" />
              </div>
            </div>
          </section>

          {/* Questions */}
          <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div className="border-b border-slate-100 px-5 py-4">
              <h2 className="text-sm font-bold uppercase tracking-[0.16em] text-slate-900">Questions</h2>
              <p className="mt-1 text-sm text-slate-500">
                Add questions to your survey. Each question can be a different type.
              </p>
            </div>

            <div className="space-y-4 px-5 py-5">
              {data.questions.length === 0 ? (
                <div className="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-sm text-slate-500">
                  No questions yet. Add one to begin building your survey.
                </div>
              ) : (
                data.questions.map((question, index) => (
                  <QuestionCard
                    key={index}
                    index={index}
                    question={question}
                    errors={errors}
                    onChange={(field, value) => updateQuestion(index, field, value)}
                    onMoveUp={() => moveQuestion(index, -1)}
                    onMoveDown={() => moveQuestion(index, 1)}
                    onRemove={() => removeQuestion(index)}
                    canMoveUp={index > 0}
                    canMoveDown={index < data.questions.length - 1}
                    questionTypes={questionTypes}
                  />
                ))
              )}

              <InputError message={errors.questions} className="mt-1.5" />

              <div className="flex flex-wrap gap-2 pt-2">
                {questionTypes.map((type) => (
                  <button
                    key={type}
                    type="button"
                    onClick={() => addQuestion(type)}
                    className="inline-flex h-9 items-center rounded-md border border-blue-900 bg-white px-3 text-xs font-semibold text-blue-900 hover:bg-blue-50"
                  >
                    + {QUESTION_TYPE_LABELS[type]?.split(' (')[0] || type}
                  </button>
                ))}
              </div>
            </div>
          </section>

          {/* Preview Info */}
          {data.questions.length > 0 && (
            <section className="rounded-xl border border-blue-100 bg-blue-50 shadow-sm">
              <div className="px-5 py-4">
                <h3 className="text-sm font-semibold text-blue-900">Preview Info</h3>
                <p className="mt-1 text-xs text-blue-700">
                  When sent to clients, the form will automatically include: <strong>Client Name</strong>, <strong>Service Name</strong>, and <strong>Date of Evaluation</strong> at the top.
                  Below that, your {data.questions.length} question{data.questions.length !== 1 ? 's' : ''} will appear.
                </p>
              </div>
            </section>
          )}

          {/* Submit */}
          <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div className="flex items-center justify-end gap-2 px-5 py-4">
              <Link
                href={route('survey.forms.index')}
                className="inline-flex h-10 items-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50"
              >
                Cancel
              </Link>
              <button
                type="submit"
                disabled={processing}
                className="inline-flex h-10 items-center rounded-md bg-blue-900 px-4 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50"
              >
                {processing ? 'Saving...' : isEditing ? 'Update Form' : 'Save Form'}
              </button>
            </div>
          </section>
        </form>

        {UnsavedModal}
      </div>
    </AppLayout>
  );
}
