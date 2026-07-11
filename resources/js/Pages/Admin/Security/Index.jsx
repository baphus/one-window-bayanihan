import { useMemo, useRef, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';
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
    password_min_length: settings.password_min_length ?? 8,
    password_require_special: !!settings.password_require_special,
    password_require_numbers: !!settings.password_require_numbers,
    password_expiry_days: settings.password_expiry_days ?? 90,
    session_lifetime_minutes: settings.session_lifetime_minutes ?? 120,
    max_login_attempts: settings.max_login_attempts ?? 5,
    lockout_duration_minutes: settings.lockout_duration_minutes ?? 15,
    ip_whitelist_enabled: !!settings.ip_whitelist_enabled,
    ip_whitelist_ips: settings.ip_whitelist_ips ?? '',
    two_factor_required: !!settings.two_factor_required,
  });

  const initialRef = useRef({
    password_min_length: settings.password_min_length ?? 8,
    password_require_special: !!settings.password_require_special,
    password_require_numbers: !!settings.password_require_numbers,
    password_expiry_days: settings.password_expiry_days ?? 90,
    session_lifetime_minutes: settings.session_lifetime_minutes ?? 120,
    max_login_attempts: settings.max_login_attempts ?? 5,
    lockout_duration_minutes: settings.lockout_duration_minutes ?? 15,
    ip_whitelist_enabled: !!settings.ip_whitelist_enabled,
    ip_whitelist_ips: settings.ip_whitelist_ips ?? '',
    two_factor_required: !!settings.two_factor_required,
  });

  const dirty = useMemo(() => JSON.stringify(data) !== JSON.stringify(initialRef.current), [data]);
  const { showModal, confirmNavigation, cancelNavigation, bypassNext } = useUnsavedChanges(dirty);

  const localSchema = z.object({
    password_min_length: z.number().min(6, 'Minimum 6 characters').max(64, 'Maximum 64 characters'),
    password_expiry_days: z.number().min(0, 'Minimum 0 days').max(365, 'Maximum 365 days'),
    session_lifetime_minutes: z.number().min(15, 'Minimum 15 minutes').max(1440, 'Maximum 1440 minutes'),
    max_login_attempts: z.number().min(1, 'Minimum 1 attempt').max(50, 'Maximum 50 attempts'),
    lockout_duration_minutes: z.number().min(1, 'Minimum 1 minute').max(1440, 'Maximum 1440 minutes'),
    ip_whitelist_ips: z.string().optional(),
  });

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
        <h1 className="text-2xl font-bold text-slate-900">Security Settings</h1>
        <p className="mt-1 text-sm text-slate-500">Manage password, session, and access control policies.</p>
      </div>

      <form onSubmit={submit} className="space-y-6 max-w-3xl">
        <Section tour="security-password-policy" title="Password Policy" description="Define password complexity and expiration rules.">
          <div>
            <label className="block text-sm font-medium text-slate-700">Minimum length</label>
            <input type="number" min="6" max="64" value={data.password_min_length} onChange={(e) => setData('password_min_length', parseInt(e.target.value) || 6)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
            <InputError message={errors.password_min_length} className="mt-1" />
          </div>

          <div className="flex items-center justify-between gap-4">
            <div>
              <p className="text-sm font-medium text-slate-700">Require special characters</p>
              <p className="text-sm text-slate-500">Enforce at least one symbol in passwords.</p>
            </div>
            <button type="button" role="switch" aria-checked={data.password_require_special} onClick={() => setData('password_require_special', !data.password_require_special)} className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${data.password_require_special ? 'bg-indigo-600' : 'bg-slate-300'}`}>
              <span className={`inline-block h-5 w-5 transform rounded-full bg-white shadow transition ${data.password_require_special ? 'translate-x-5' : 'translate-x-0'}`} />
            </button>
          </div>

          <div className="flex items-center justify-between gap-4">
            <div>
              <p className="text-sm font-medium text-slate-700">Require numbers</p>
              <p className="text-sm text-slate-500">Enforce at least one digit in passwords.</p>
            </div>
            <button type="button" role="switch" aria-checked={data.password_require_numbers} onClick={() => setData('password_require_numbers', !data.password_require_numbers)} className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${data.password_require_numbers ? 'bg-indigo-600' : 'bg-slate-300'}`}>
              <span className={`inline-block h-5 w-5 transform rounded-full bg-white shadow transition ${data.password_require_numbers ? 'translate-x-5' : 'translate-x-0'}`} />
            </button>
          </div>

          <div>
            <label className="block text-sm font-medium text-slate-700">Password expiry (days)</label>
            <input type="number" min="0" max="365" value={data.password_expiry_days} onChange={(e) => setData('password_expiry_days', parseInt(e.target.value) || 0)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
            <InputError message={errors.password_expiry_days} className="mt-1" />
          </div>
        </Section>

        <Section tour="security-session" title="Session Security" description="Control session duration and account lockout behavior.">
          <div>
            <label className="block text-sm font-medium text-slate-700">Session lifetime (minutes)</label>
            <input type="number" min="15" max="1440" value={data.session_lifetime_minutes} onChange={(e) => setData('session_lifetime_minutes', parseInt(e.target.value) || 15)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
            <InputError message={errors.session_lifetime_minutes} className="mt-1" />
          </div>

          <div>
            <label className="block text-sm font-medium text-slate-700">Max login attempts</label>
            <input type="number" min="1" max="50" value={data.max_login_attempts} onChange={(e) => setData('max_login_attempts', parseInt(e.target.value) || 1)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
            <InputError message={errors.max_login_attempts} className="mt-1" />
          </div>

          <div>
            <label className="block text-sm font-medium text-slate-700">Lockout duration (minutes)</label>
            <input type="number" min="1" max="1440" value={data.lockout_duration_minutes} onChange={(e) => setData('lockout_duration_minutes', parseInt(e.target.value) || 1)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
            <InputError message={errors.lockout_duration_minutes} className="mt-1" />
          </div>
        </Section>

        <Section tour="security-access-control" title="Access Control" description="Restrict access with IP whitelisting and two-factor authentication.">
          <div className="flex items-center justify-between gap-4">
            <div>
              <p className="text-sm font-medium text-slate-700">Enable IP whitelist</p>
              <p className="text-sm text-slate-500">Only allow requests from approved IPs or CIDRs.</p>
            </div>
            <button type="button" role="switch" aria-checked={data.ip_whitelist_enabled} onClick={() => setData('ip_whitelist_enabled', !data.ip_whitelist_enabled)} className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${data.ip_whitelist_enabled ? 'bg-indigo-600' : 'bg-slate-300'}`}>
              <span className={`inline-block h-5 w-5 transform rounded-full bg-white shadow transition ${data.ip_whitelist_enabled ? 'translate-x-5' : 'translate-x-0'}`} />
            </button>
            <InputError message={errors.ip_whitelist_enabled} className="mt-1" />
          </div>

          <div>
            <label className="block text-sm font-medium text-slate-700">Allowed IPs / CIDRs</label>
            <textarea value={data.ip_whitelist_ips} onChange={(e) => setData('ip_whitelist_ips', e.target.value)} rows={5} placeholder="127.0.0.1\n192.168.1.0/24" className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
            <p className="mt-1 text-xs text-slate-500">One IP or CIDR per line.</p>
            <InputError message={errors.ip_whitelist_ips} className="mt-1" />
          </div>

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

      <UnsavedChangesModal show={showModal} onConfirm={confirmNavigation} onCancel={cancelNavigation} />
    </AppLayout>
  );
}
