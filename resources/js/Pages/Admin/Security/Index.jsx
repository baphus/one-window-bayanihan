import { useMemo, useRef, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';

import InputError from '@/Components/InputError';
import useClientValidation from '@/Hooks/useClientValidation';
import { z } from 'zod';

function Section({ title, description, children, tour }) {
  return (
    <div data-tour={tour} className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
      <div className="mb-6">
        <h2 className="text-base font-semibold text-slate-900">{title}</h2>
        {description && <p className="mt-1 text-sm text-slate-500">{description}</p>}
      </div>
      <div className="space-y-4">{children}</div>
    </div>
  );
}

export default function Index({ settings }) {
  const { data, setData, post, processing, errors, setError, clearErrors } = useForm({
    two_factor_required: !!settings.two_factor_required,
  });

  const initialRef = useRef({
    two_factor_required: !!settings.two_factor_required,
  });

  const dirty = useMemo(() => JSON.stringify(data) !== JSON.stringify(initialRef.current), [data]);
  const { UnsavedModal, bypassNext } = useUnsavedChanges(dirty);

  const localSchema = z.object({});

  const { validate } = useClientValidation(localSchema, data, setError);

  const submit = (e) => {
    e.preventDefault();
    clearErrors();
    if (!validate()) return;
    bypassNext();
    post(route('admin.system.security.update'), {
      preserveScroll: true,
      onSuccess: () => {
        initialRef.current = { ...data };
      },
    });
  };

  return (
    <AppLayout title="Security Settings">
      <Head title="Security Settings" />

      <div data-tour="security-header" className="mb-8">
        <h1 className="text-2xl md:text-3xl font-extrabold font-headline tracking-tight text-slate-900">Security Settings</h1>
        <p className="text-sm text-slate-400 font-body mt-0.5">Manage access control policies.</p>
      </div>

      <form onSubmit={submit} className="space-y-6 max-w-3xl">
        <Section tour="security-access-control" title="Access Control" description="Manage two-factor authentication enforcement.">
          <div className="flex items-center justify-between gap-4">
            <div>
              <p className="text-sm font-medium text-slate-700">Require two-factor authentication</p>
              <p className="text-sm text-slate-500">Force 2FA for all users.</p>
            </div>
            <button type="button" role="switch" aria-checked={data.two_factor_required} onClick={() => setData('two_factor_required', !data.two_factor_required)} className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${data.two_factor_required ? 'bg-indigo-600' : 'bg-slate-300'}`}>
              <span className={`inline-block h-5 w-5 transform rounded-full bg-white shadow transition ${data.two_factor_required ? 'translate-x-5' : 'translate-x-0'}`} />
            </button>
            <InputError message={errors.two_factor_required} className="mt-1" />
          </div>
        </Section>

        <div className="flex justify-end">
          <button data-tour="security-save" type="submit" disabled={processing} className="rounded-md bg-blue-900 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 disabled:opacity-50">Save Changes</button>
        </div>
      </form>

      {UnsavedModal}
    </AppLayout>
  );
}
