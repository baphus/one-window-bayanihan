import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useRef } from 'react';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';
import InputError from '@/Components/InputError';
import useClientValidation from '@/Hooks/useClientValidation';
import { z } from 'zod';

const DIMENSIONS = ['Tangibles', 'Reliability', 'Responsiveness', 'Assurance', 'Empathy'];

function normalizeQuestions(value) {
  if (Array.isArray(value)) return value;
  if (typeof value === 'string' && value.trim() !== '') {
    try {
      const parsed = JSON.parse(value);
      return Array.isArray(parsed) ? parsed : [];
    } catch {
      return [];
    }
  }
  return [];
}

function QuestionCard({ index, question, errors, onChange, onMoveUp, onMoveDown, onRemove, canMoveUp, canMoveDown }) {
  return (
    <article className="rounded-xl border border-slate-200 bg-white shadow-sm">
      <div className="h-1 rounded-t-xl bg-blue-900/80" />
      <div className="p-5 sm:p-6">
        <div className="flex items-start gap-4">
          <div className="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-blue-900 text-sm font-semibold text-white">
            {index + 1}
          </div>

          <div className="min-w-0 flex-1 space-y-4">
            <div className="grid gap-4 lg:grid-cols-[180px,1fr]">
              <div>
                <label className="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">
                  Dimension
                </label>
                <select
                  value={question.dimension}
                  onChange={(e) => onChange('dimension', e.target.value)}
                  className={`h-10 w-full rounded-md border px-3 text-sm text-slate-700 outline-none focus:ring-1 ${
                    errors[`questions.${index}.dimension`]
                      ? 'border-red-400 focus:border-red-500 focus:ring-red-500'
                      : 'border-slate-300 focus:border-blue-900 focus:ring-blue-900'
                  }`}
                >
                  {DIMENSIONS.map((dim) => (
                    <option key={dim} value={dim}>
                      {dim}
                    </option>
                  ))}
                </select>
                <InputError message={errors[`questions.${index}.dimension`]} className="mt-1.5" />
              </div>

              <div>
                <label className="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">
                  Question text
                </label>
                <input
                  type="text"
                  value={question.question}
                  onChange={(e) => onChange('question', e.target.value)}
                  placeholder="Enter question text"
                  maxLength={255}
                  className={`h-10 w-full rounded-md border px-3 text-sm text-slate-700 outline-none focus:ring-1 ${
                    errors[`questions.${index}.question`]
                      ? 'border-red-400 focus:border-red-500 focus:ring-red-500'
                      : 'border-slate-300 focus:border-blue-900 focus:ring-blue-900'
                  }`}
                />
                <InputError message={errors[`questions.${index}.question`]} className="mt-1.5" />
              </div>
            </div>

            <div className="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
              <span className="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Question controls</span>
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

export default function ServqualConfigForm({ config, defaultQuestions, services = [] }) {
  const isEditing = !!config;

  const buildInitialQuestions = () => {
    const configQuestions = normalizeQuestions(config?.questions);
    if (isEditing && configQuestions.length > 0) {
      return configQuestions.map((q) => ({
        id: q.id ?? null,
        dimension: q.dimension,
        question: q.question,
        order: q.order ?? 0,
      }));
    }

    return normalizeQuestions(defaultQuestions).map((dq, i) => ({
      dimension: dq.dimension,
      question: dq.question,
      order: dq.order ?? i + 1,
    }));
  };

  const initialQuestions = buildInitialQuestions();
  const initialServiceId = config?.service_id ? String(config.service_id) : '';

  const { data, setData, post, patch, processing, errors, setError, clearErrors } = useForm({
    name: config?.name ?? '',
    service_id: initialServiceId,
    service_name: config?.service_name ?? '',
    questions: initialQuestions,
  });

  const initialRef = useRef({
    name: config?.name ?? '',
    service_id: initialServiceId,
    service_name: config?.service_name ?? '',
    questions: initialQuestions,
  });

  const selectedService = services.find((service) => String(service.id) === String(data.service_id));
  const questions = normalizeQuestions(data.questions);

  const questionsDirty = JSON.stringify(questions) !== JSON.stringify(initialRef.current.questions);
  const dirty =
    data.name !== initialRef.current.name ||
    data.service_id !== initialRef.current.service_id ||
    data.service_name !== initialRef.current.service_name ||
    questionsDirty;

  const { showModal, confirmNavigation, cancelNavigation } = useUnsavedChanges(dirty);

  const questionsSchema = z.object({
    dimension: z.string().min(1, 'Dimension is required'),
    question: z.string().min(1, 'Question text is required').max(255, 'Maximum 255 characters'),
  });

  const localSchema = z.object({
    name: z.string().min(1, 'Form name is required').max(255, 'Maximum 255 characters'),
    service_id: z.string().optional(),
    service_name: z.string().min(1, 'Display service name is required').max(255, 'Maximum 255 characters'),
    questions: z.array(questionsSchema).min(1, 'At least one question is required'),
  });

  const { validate } = useClientValidation(localSchema, data, setError);

  const updateQuestion = (index, field, value) => {
    const updated = [...questions];
    updated[index] = { ...updated[index], [field]: value };
    setData('questions', updated);
  };

  const addQuestion = () => {
    setData('questions', [...questions, { dimension: DIMENSIONS[0], question: '', order: questions.length + 1 }]);
  };

  const removeQuestion = (index) => {
    setData('questions', questions.filter((_, i) => i !== index));
  };

  const moveQuestion = (index, direction) => {
    const target = index + direction;
    if (target < 0 || target >= questions.length) return;
    const updated = [...questions];
    const temp = updated[index];
    updated[index] = updated[target];
    updated[target] = temp;
    setData('questions', updated);
  };

  const handleServiceChange = (value) => {
    setData('service_id', value);
    if (value) {
      const service = services.find((item) => String(item.id) === String(value));
      setData('service_name', service?.name ?? '');
    }
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    clearErrors();
    if (!validate()) return;

    setData('questions', normalizeQuestions(questions));

    if (isEditing) {
      patch(route('servqual-configs.update', config.id));
    } else {
      post(route('servqual-configs.store'));
    }
  };

  return (
    <AppLayout title={isEditing ? 'Edit SERVQUAL Configuration' : 'Create SERVQUAL Configuration'}>
      <Head title={isEditing ? 'Edit SERVQUAL Configuration' : 'Create SERVQUAL Configuration'} />

      <div className="space-y-6">
        <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
          <div className="flex flex-col gap-3 px-5 py-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <p className="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Feedback</p>
              <h1 className="mt-2 text-2xl font-bold tracking-tight text-slate-900">
                {isEditing ? 'Edit Configuration' : 'New Configuration'}
              </h1>
              <p className="mt-1 text-sm text-slate-500">
                Set the form name, assign a service, and manage the question list.
              </p>
            </div>
            <Link
              href={route('servqual-configs.index')}
              className="inline-flex h-10 items-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50"
            >
              Back to configurations
            </Link>
          </div>
        </section>

        <form onSubmit={handleSubmit} className="space-y-6">
          <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div className="border-b border-slate-100 px-5 py-4">
              <h2 className="text-sm font-bold uppercase tracking-[0.16em] text-slate-900">Form header</h2>
              <p className="mt-1 text-sm text-slate-500">
                The default form applies to all services. Assigning a service makes this an override.
              </p>
            </div>

            <div className="grid gap-5 px-5 py-5 lg:grid-cols-3">
              <div>
                <label htmlFor="name" className="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">
                  Form Name
                </label>
                <input
                  id="name"
                  type="text"
                  value={data.name}
                  onChange={(e) => setData('name', e.target.value)}
                  placeholder="Default Client Satisfaction Form"
                  maxLength={255}
                  className={`h-10 w-full rounded-md border px-3 text-sm text-slate-700 outline-none focus:ring-1 ${
                    errors.name ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : 'border-slate-300 focus:border-blue-900 focus:ring-blue-900'
                  }`}
                />
                <InputError message={errors.name} className="mt-1.5" />
              </div>

              <div>
                <label htmlFor="service_id" className="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">
                  Service Assignment
                </label>
                <select
                  id="service_id"
                  value={data.service_id}
                  onChange={(e) => handleServiceChange(e.target.value)}
                  className={`h-10 w-full rounded-md border px-3 text-sm text-slate-700 outline-none focus:ring-1 ${
                    errors.service_id ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : 'border-slate-300 focus:border-blue-900 focus:ring-blue-900'
                  }`}
                >
                  <option value="">All Services (Default)</option>
                  {services.map((service) => (
                    <option key={service.id} value={service.id}>
                      {service.name}
                    </option>
                  ))}
                </select>
                <p className="mt-1.5 text-xs text-slate-500">Choose a specific service only when this form should override it.</p>
                <InputError message={errors.service_id} className="mt-1.5" />
              </div>

              <div>
                <label htmlFor="service_name" className="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">
                  Display Service Name
                </label>
                <input
                  id="service_name"
                  type="text"
                  value={data.service_name}
                  onChange={(e) => setData('service_name', e.target.value)}
                  readOnly={!!data.service_id}
                  placeholder={data.service_id ? 'Auto-filled from selected service' : 'OFW Assistance Desk'}
                  required={!data.service_id}
                  maxLength={255}
                  className={`h-10 w-full rounded-md border px-3 text-sm text-slate-700 outline-none focus:ring-1 ${
                    errors.service_name ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : 'border-slate-300 focus:border-blue-900 focus:ring-blue-900'
                  } ${data.service_id ? 'cursor-not-allowed bg-slate-100 text-slate-500' : 'bg-white'}`}
                />
                <p className="mt-1.5 text-xs text-slate-500">
                  {data.service_id ? `Locked to ${selectedService?.name || 'the selected service'}.` : 'This is the label clients will see.'}
                </p>
                <InputError message={errors.service_name} className="mt-1.5" />
              </div>
            </div>
          </section>

          <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div className="border-b border-slate-100 px-5 py-4">
              <h2 className="text-sm font-bold uppercase tracking-[0.16em] text-slate-900">Questions</h2>
              <p className="mt-1 text-sm text-slate-500">Each question is a separate card, like a familiar form builder.</p>
            </div>

            <div className="space-y-4 px-5 py-5">
              {questions.length === 0 ? (
                <div className="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-sm text-slate-500">
                  No questions yet. Add one to begin.
                </div>
              ) : (
                questions.map((question, index) => (
                  <QuestionCard
                    key={question.id ?? index}
                    index={index}
                    question={question}
                    errors={errors}
                    onChange={(field, value) => updateQuestion(index, field, value)}
                    onMoveUp={() => moveQuestion(index, -1)}
                    onMoveDown={() => moveQuestion(index, 1)}
                    onRemove={() => removeQuestion(index)}
                    canMoveUp={index > 0}
                    canMoveDown={index < questions.length - 1}
                  />
                ))
              )}

              <div className="pt-2">
                <button
                  type="button"
                  onClick={addQuestion}
                  className="inline-flex h-10 items-center rounded-md border border-blue-900 bg-white px-4 text-sm font-semibold text-blue-900 hover:bg-blue-50"
                >
                  + Add question
                </button>
              </div>
            </div>
          </section>

          <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div className="flex items-center justify-end gap-2 px-5 py-4">
              <Link
                href={route('servqual-configs.index')}
                className="inline-flex h-10 items-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50"
              >
                Cancel
              </Link>
              <button
                type="submit"
                disabled={processing}
                className="inline-flex h-10 items-center rounded-md bg-blue-900 px-4 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50"
              >
                {processing ? 'Saving...' : isEditing ? 'Update Configuration' : 'Save Configuration'}
              </button>
            </div>
          </section>
        </form>

        <UnsavedChangesModal show={showModal} onConfirm={confirmNavigation} onCancel={cancelNavigation} />
      </div>
    </AppLayout>
  );
}
