import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useRef } from 'react';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';
import InputError from '@/Components/InputError';
import useClientValidation from '@/Hooks/useClientValidation';
import { z } from 'zod';

const DIMENSIONS = ['Tangibles', 'Reliability', 'Responsiveness', 'Assurance', 'Empathy'];

export default function ServqualConfigForm({ config, defaultQuestions, services = [] }) {
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

  const questionsDirty = JSON.stringify(data.questions) !== JSON.stringify(initialRef.current.questions);
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

  const setQuestionField = (index, field, value) => {
    setData('questions', (prev) => {
      const updated = [...prev];
      updated[index] = { ...updated[index], [field]: value };
      return updated;
    });
  };

  const addQuestion = () => {
    setData('questions', (prev) => [...prev, { dimension: DIMENSIONS[0], question: '', order: prev.length + 1 }]);
  };

  const removeQuestion = (index) => {
    setData('questions', (prev) => prev.filter((_, i) => i !== index));
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
              <h1 className="mt-2 text-2xl font-bold tracking-tight text-slate-900">{isEditing ? 'Edit Configuration' : 'New Configuration'}</h1>
              <p className="mt-1 text-sm text-slate-500">Set the form name, assign a service, and manage the survey questions.</p>
            </div>
            <Link href={route('servqual-configs.index')} className="inline-flex h-10 items-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
              Back to configurations
            </Link>
          </div>
        </section>

        <form onSubmit={handleSubmit} className="space-y-6">
          <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div className="border-b border-slate-100 px-5 py-4">
              <h2 className="text-sm font-bold uppercase tracking-[0.16em] text-slate-900">Form details</h2>
              <p className="mt-1 text-sm text-slate-500">Default forms apply to all services. Service assignments create overrides.</p>
            </div>
            <div className="grid gap-5 px-5 py-5 lg:grid-cols-3">
              <div>
                <label htmlFor="name" className="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Form Name</label>
                <input
                  id="name"
                  type="text"
                  value={data.name}
                  onChange={(e) => setData('name', e.target.value)}
                  placeholder="Default Client Satisfaction Form"
                  maxLength={255}
                  className={`h-10 w-full rounded-md border px-3 text-sm text-slate-700 outline-none focus:ring-1 ${errors.name ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : 'border-slate-300 focus:border-blue-900 focus:ring-blue-900'}`}
                />
                <InputError message={errors.name} className="mt-1.5" />
              </div>

              <div>
                <label htmlFor="service_id" className="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Service Assignment</label>
                <select
                  id="service_id"
                  value={data.service_id}
                  onChange={(e) => handleServiceChange(e.target.value)}
                  className={`h-10 w-full rounded-md border px-3 text-sm text-slate-700 outline-none focus:ring-1 ${errors.service_id ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : 'border-slate-300 focus:border-blue-900 focus:ring-blue-900'}`}
                >
                  <option value="">All Services (Default)</option>
                  {services.map((service) => (
                    <option key={service.id} value={service.id}>{service.name}</option>
                  ))}
                </select>
                <p className="mt-1.5 text-xs text-slate-500">Choose a specific service only when this form should override it.</p>
                <InputError message={errors.service_id} className="mt-1.5" />
              </div>

              <div>
                <label htmlFor="service_name" className="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Display Service Name</label>
                <input
                  id="service_name"
                  type="text"
                  value={data.service_name}
                  onChange={(e) => setData('service_name', e.target.value)}
                  readOnly={!!data.service_id}
                  placeholder={data.service_id ? 'Auto-filled from selected service' : 'OFW Assistance Desk'}
                  required={!data.service_id}
                  maxLength={255}
                  className={`h-10 w-full rounded-md border px-3 text-sm text-slate-700 outline-none focus:ring-1 ${errors.service_name ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : 'border-slate-300 focus:border-blue-900 focus:ring-blue-900'} ${data.service_id ? 'cursor-not-allowed bg-slate-100 text-slate-500' : 'bg-white'}`}
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
              <h2 className="text-sm font-bold uppercase tracking-[0.16em] text-slate-900">Survey questions</h2>
              <p className="mt-1 text-sm text-slate-500">Keep the list ordered and concise.</p>
            </div>
            <div className="px-5 py-5">
              {data.questions.length === 0 ? (
                <div className="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm text-slate-500">No questions yet.</div>
              ) : (
                <div className="space-y-3">
                  {data.questions.map((question, index) => (
                    <div key={index} className="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4">
                      <div className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-blue-900 text-xs font-semibold text-white">{index + 1}</div>
                      <div className="flex flex-1 flex-col gap-3 sm:flex-row">
                        <div className="w-full sm:w-44">
                          <select
                            value={question.dimension}
                            onChange={(e) => setQuestionField(index, 'dimension', e.target.value)}
                            className={`h-10 w-full rounded-md border px-3 text-sm text-slate-700 outline-none focus:ring-1 ${errors[`questions.${index}.dimension`] ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : 'border-slate-300 focus:border-blue-900 focus:ring-blue-900'}`}
                          >
                            {DIMENSIONS.map((dim) => (
                              <option key={dim} value={dim}>{dim}</option>
                            ))}
                          </select>
                          <InputError message={errors[`questions.${index}.dimension`]} className="mt-1" />
                        </div>

                        <div className="flex-1">
                          <input
                            type="text"
                            value={question.question}
                            onChange={(e) => setQuestionField(index, 'question', e.target.value)}
                            placeholder="Enter question text"
                            maxLength={255}
                            className={`h-10 w-full rounded-md border px-3 text-sm text-slate-700 outline-none focus:ring-1 ${errors[`questions.${index}.question`] ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : 'border-slate-300 focus:border-blue-900 focus:ring-blue-900'}`}
                          />
                          <InputError message={errors[`questions.${index}.question`]} className="mt-1" />
                        </div>
                      </div>

                      <div className="flex flex-shrink-0 items-center gap-1">
                        <button type="button" onClick={() => moveQuestion(index, -1)} disabled={index === 0} className="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 bg-white text-slate-600 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-30" title="Move up">
                          ↑
                        </button>
                        <button type="button" onClick={() => moveQuestion(index, 1)} disabled={index === data.questions.length - 1} className="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 bg-white text-slate-600 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-30" title="Move down">
                          ↓
                        </button>
                        <button type="button" onClick={() => removeQuestion(index)} className="inline-flex h-8 items-center rounded-md border border-slate-300 bg-white px-2.5 text-xs font-semibold text-red-700 hover:bg-red-50">
                          Remove
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              )}

              <button type="button" onClick={addQuestion} className="mt-4 inline-flex h-10 items-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Add question
              </button>
            </div>
          </section>

          <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div className="flex items-center justify-end gap-2 px-5 py-4">
              <Link href={route('servqual-configs.index')} className="inline-flex h-10 items-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Cancel
              </Link>
              <button type="submit" disabled={processing} className="inline-flex h-10 items-center rounded-md bg-blue-900 px-4 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50">
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
