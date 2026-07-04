import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useRef } from 'react';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';
import InputError from '@/Components/InputError';
import useClientValidation from '@/Hooks/useClientValidation';
import { z } from 'zod';

const DIMENSIONS = ['Tangibles', 'Reliability', 'Responsiveness', 'Assurance', 'Empathy'];

export default function ServqualConfigForm({ config, defaultQuestions }) {
  const isEditing = !!config;

  const buildInitialQuestions = () => {
    if (isEditing && config.questions?.length > 0) {
      return config.questions.map((q) => ({
        id: q.id ?? null,
        dimension: q.dimension,
        question: q.question,
        order: q.order ?? 0,
      }));
    }
    return (defaultQuestions ?? []).map((dq, i) => ({
      dimension: dq.dimension,
      question: dq.question,
      order: dq.order ?? i + 1,
    }));
  };

  const { data, setData, post, patch, processing, errors, setError, clearErrors } = useForm({
    service_name: config?.service_name ?? '',
    questions: buildInitialQuestions(),
  });

  const initialRef = useRef({
    service_name: config?.service_name ?? '',
    questions: buildInitialQuestions(),
  });

  const questionsDirty =
    JSON.stringify(data.questions) !== JSON.stringify(initialRef.current.questions);
  const dirty = data.service_name !== initialRef.current.service_name || questionsDirty;

  const { showModal, confirmNavigation, cancelNavigation } = useUnsavedChanges(dirty);

  const questionsSchema = z.object({
    dimension: z.string().min(1, 'Dimension is required'),
    question: z.string().min(1, 'Question text is required').max(255, 'Maximum 255 characters'),
  });

  const localSchema = z.object({
    service_name: z.string().min(1, 'Service name is required').max(255, 'Maximum 255 characters'),
    questions: z.array(questionsSchema).min(1, 'At least one question is required'),
  });

  const { validate } = useClientValidation(localSchema, data, setError);

  const setQuestionField = (index, field, value) => {
    setData('questions', (prev) => {
      const updated = [...prev];
      updated[index] = { ...updated[index], [field]: value };
      return updated;
    });
  };

  const addQuestion = () => {
    setData('questions', (prev) => [
      ...prev,
      { dimension: DIMENSIONS[0], question: '', order: prev.length + 1 },
    ]);
  };

  const removeQuestion = (index) => {
    setData(
      'questions',
      (prev) => prev.filter((_, i) => i !== index),
    );
  };

  const moveQuestion = (index, direction) => {
    const target = index + direction;
    if (target < 0 || target >= data.questions.length) return;
    setData('questions', (prev) => {
      const updated = [...prev];
      const temp = updated[index];
      updated[index] = updated[target];
      updated[target] = temp;
      return updated;
    });
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    clearErrors();
    if (!validate()) return;

    if (isEditing) {
      patch(route('servqual-configs.update', config.id));
    } else {
      post(route('servqual-configs.store'));
    }
  };

  return (
    <AppLayout
      title={isEditing ? 'Edit SERVQUAL Configuration' : 'Create SERVQUAL Configuration'}
    >
      <Head
        title={
          isEditing ? 'Edit SERVQUAL Configuration' : 'Create SERVQUAL Configuration'
        }
      />

      <div className="mb-8">
        <div className="flex items-center gap-3 mb-1">
          <Link
            href={route('servqual-configs.index')}
            className="px-4 py-2 text-sm font-medium text-white bg-blue-900 rounded-md hover:bg-blue-800 transition-colors shrink-0"
          >
            &larr; Back to Configurations
          </Link>
        </div>
        <h1 className="text-2xl font-bold text-slate-900">
          {isEditing ? 'Edit Configuration' : 'New Configuration'}
        </h1>
        <p className="mt-1 text-sm text-slate-500">
          {isEditing
            ? 'Update the SERVQUAL survey questions for this service.'
            : 'Define a new set of SERVQUAL survey questions for a service.'}
        </p>
      </div>

      <form onSubmit={handleSubmit} className="space-y-8">
        {/* Service Name */}
        <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
          <label
            htmlFor="service_name"
            className="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-500"
          >
            Service Name
          </label>
          <input
            id="service_name"
            type="text"
            value={data.service_name}
            onChange={(e) => setData('service_name', e.target.value)}
            disabled={isEditing}
            placeholder="e.g. OFW Assistance Desk"
            required
            maxLength={255}
            className={`h-10 w-full max-w-lg rounded border px-3 text-sm text-slate-700 outline-none focus:ring-1 ${
              errors.service_name
                ? 'border-red-400 focus:border-red-500 focus:ring-red-500'
                : 'border-slate-300 focus:border-indigo-600 focus:ring-indigo-600'
            } ${isEditing ? 'cursor-not-allowed bg-slate-100 text-slate-500' : 'bg-white'}`}
          />
          <InputError message={errors.service_name} className="mt-1.5" />
        </div>

        {/* Questions */}
        <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
          <div className="mb-1.5 flex items-center justify-between">
            <label className="block text-[11px] font-bold uppercase tracking-wider text-slate-500">
              Survey Questions
            </label>
          </div>

          {data.questions.length === 0 ? (
            <div className="rounded border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
              <p className="text-sm text-slate-500">
                No questions added yet. Click "Add Question" to begin.
              </p>
            </div>
          ) : (
            <div className="space-y-3">
              {data.questions.map((question, index) => (
                <div
                  key={index}
                  className="flex items-start gap-3 rounded border border-slate-200 bg-slate-50 p-4"
                >
                  {/* Question number */}
                  <div className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-700">
                    {index + 1}
                  </div>

                  {/* Dimension + Question fields */}
                  <div className="flex flex-1 flex-col gap-3 sm:flex-row">
                    {/* Dimension dropdown */}
                    <div className="w-full sm:w-44">
                      <select
                        value={question.dimension}
                        onChange={(e) =>
                          setQuestionField(index, 'dimension', e.target.value)
                        }
                        required
                        className={`h-10 w-full rounded border px-3 text-sm text-slate-700 outline-none focus:ring-1 ${
                          errors[`questions.${index}.dimension`]
                            ? 'border-red-400 focus:border-red-500 focus:ring-red-500'
                            : 'border-slate-300 focus:border-indigo-600 focus:ring-indigo-600'
                        }`}
                      >
                        {DIMENSIONS.map((dim) => (
                          <option key={dim} value={dim}>
                            {dim}
                          </option>
                        ))}
                      </select>
                      <InputError message={errors[`questions.${index}.dimension`]} className="mt-1" />
                    </div>

                    {/* Question text */}
                    <div className="flex-1">
                      <input
                        type="text"
                        value={question.question}
                        onChange={(e) =>
                          setQuestionField(index, 'question', e.target.value)
                        }
                        placeholder="Enter question text"
                        required
                        maxLength={255}
                        className={`h-10 w-full rounded border px-3 text-sm text-slate-700 outline-none focus:ring-1 ${
                          errors[`questions.${index}.question`]
                            ? 'border-red-400 focus:border-red-500 focus:ring-red-500'
                            : 'border-slate-300 focus:border-indigo-600 focus:ring-indigo-600'
                        }`}
                      />
                      <InputError message={errors[`questions.${index}.question`]} className="mt-1" />
                    </div>
                  </div>

                  {/* Action buttons */}
                  <div className="flex flex-shrink-0 items-center gap-1">
                    <button
                      type="button"
                      onClick={() => moveQuestion(index, -1)}
                      disabled={index === 0}
                      className="flex h-7 w-7 items-center justify-center rounded border border-slate-300 bg-white text-xs font-bold text-slate-600 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-30"
                      title="Move up"
                    >
                      &uarr;
                    </button>
                    <button
                      type="button"
                      onClick={() => moveQuestion(index, 1)}
                      disabled={index === data.questions.length - 1}
                      className="flex h-7 w-7 items-center justify-center rounded border border-slate-300 bg-white text-xs font-bold text-slate-600 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-30"
                      title="Move down"
                    >
                      &darr;
                    </button>
                    <button
                      type="button"
                      onClick={() => removeQuestion(index)}
                      className="flex h-7 items-center rounded px-2 text-xs font-bold text-red-600 hover:bg-red-50"
                    >
                      Remove
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}

          <button
            type="button"
            onClick={addQuestion}
            className="mt-4 inline-flex h-9 items-center rounded border border-indigo-300 bg-white px-4 text-xs font-bold text-indigo-700 hover:bg-indigo-50"
          >
            + Add Question
          </button>
        </div>

        {/* Submit */}
        <div className="flex items-center justify-end gap-3 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
          <Link
            href={route('servqual-configs.index')}
            className="inline-flex h-10 items-center rounded border border-slate-300 bg-white px-5 text-sm font-bold text-slate-700 hover:bg-slate-50"
          >
            Cancel
          </Link>
          <button
            type="submit"
            disabled={processing}
            className="inline-flex h-10 items-center rounded bg-indigo-600 px-5 text-sm font-bold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
          >
            {processing
              ? 'Saving...'
              : isEditing
                ? 'Update Configuration'
                : 'Save Configuration'}
          </button>
        </div>
      </form>

      <UnsavedChangesModal
        show={showModal}
        onConfirm={confirmNavigation}
        onCancel={cancelNavigation}
      />
    </AppLayout>
  );
}
