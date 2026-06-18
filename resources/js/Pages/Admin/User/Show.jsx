import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import Section from '@/Components/Section';
import StatusBadge from '@/Components/ui/StatusBadge';

function formatNotificationLabel(key) {
  return key
    .replace(/^(email_on_|sms_on_)/i, '')
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (c) => c.toUpperCase());
}

function Field({ label, value }) {
  return (
    <div className="rounded-[3px] border border-[#e2e8f0] bg-[#f8fafc] px-3 py-2">
      <p className="text-[9px] font-extrabold uppercase tracking-[0.1em] text-slate-500">{label}</p>
      <p className="mt-1 text-[12px] font-semibold text-slate-700">{value ?? '—'}</p>
    </div>
  );
}

function InitialsAvatar({ name }) {
  const initials = (name || '')
    .split(' ')
    .map((n) => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2);
  return (
    <div className="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-blue-100 text-lg font-bold text-blue-900">
      {initials}
    </div>
  );
}

function ToggleBadge({ enabled }) {
  return (
    <span
      className={`inline-flex items-center gap-1 rounded-[2px] border px-2 py-[3px] text-[10px] font-extrabold uppercase tracking-wide ${
        enabled
          ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
          : 'border-slate-200 bg-slate-100 text-slate-500'
      }`}
    >
      {enabled ? 'On' : 'Off'}
    </span>
  );
}

function StatusPill({ label, enabled }) {
  return (
    <span
      className={`inline-flex items-center gap-1 rounded-[2px] border px-2 py-[3px] text-[10px] font-extrabold uppercase tracking-wide ${
        enabled
          ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
          : 'border-slate-200 bg-slate-100 text-slate-500'
      }`}
    >
      {label}
    </span>
  );
}

export default function AdminUserShow({ user }) {
  const roleLabels = {
    CASE_MANAGER: 'Case Manager',
    AGENCY: 'Agency Focal',
    ADMIN: 'System Admin',
  };

  const agency = user.agency ?? null;
  const emergency = user.emergency_contact ?? {};
  const notifications = user.notifications_config ?? {};

  return (
    <AppLayout title={`User Details — ${user.name}`}>
      <Head title={`User Details — ${user.name}`} />

      {/* Breadcrumb */}
      <div className="mb-4 text-[10px] font-bold uppercase tracking-[0.14em] text-slate-500">
        <Link href={route('admin.users.index')} className="transition hover:text-indigo-600">
          Users
        </Link>
        <span className="mx-2">&gt;</span>
        <span>{user.name}</span>
      </div>

      {/* Header with back button */}
      <div className="mb-6 flex items-start justify-between gap-4">
        <h1 className="text-2xl font-bold text-slate-900">User Details</h1>
        <Link
          href={route('admin.users.index')}
          className="inline-flex items-center gap-1 rounded-md border border-[#cbd5e1] bg-white px-3 py-2 text-[11px] font-bold text-slate-700 transition hover:bg-slate-50"
        >
          &larr; Back to Users
        </Link>
      </div>

      <div className="space-y-6">
        {/* 1. Profile Photo */}
        <Section title="Profile" description="User avatar, name, role, and status">
          <div className="flex items-start gap-5">
            {user.avatar_url ? (
              <img
                src={user.avatar_url}
                alt={`${user.name} avatar`}
                className="h-16 w-16 shrink-0 rounded-full border border-slate-200 object-cover"
              />
            ) : (
              <InitialsAvatar name={user.name} />
            )}
            <div className="space-y-2 pt-1">
              <p className="text-base font-bold text-slate-900">{user.name}</p>
              <p className="text-sm text-slate-500">{user.email}</p>
              <div className="flex items-center gap-2">
                <span className="inline-flex items-center rounded-[2px] border border-blue-200 bg-blue-50 px-2 py-[3px] text-[10px] font-extrabold uppercase tracking-wide text-blue-700">
                  {roleLabels[user.role] || user.role}
                </span>
                <StatusBadge status={user.is_active ? 'ACTIVE' : 'INACTIVE'} />
              </div>
            </div>
          </div>
        </Section>

        {/* 2. Agency Information */}
        <Section title="Agency Information" description="The agency this user is affiliated with">
          {agency ? (
            <div className="space-y-4">
              <div className="flex items-start gap-4">
                {agency.logo_url ? (
                  <img
                    src={agency.logo_url}
                    alt={`${agency.name} logo`}
                    className="h-12 w-12 shrink-0 rounded-lg border border-slate-200 bg-white object-contain p-1"
                  />
                ) : null}
                <div>
                  <p className="text-sm font-semibold text-slate-800">{agency.name}</p>
                  {agency.description && (
                    <p className="mt-1 text-xs leading-5 text-slate-500">{agency.description}</p>
                  )}
                </div>
              </div>
              {agency.services && agency.services.length > 0 && (
                <div>
                  <p className="mb-2 text-[9px] font-extrabold uppercase tracking-[0.1em] text-slate-500">
                    Services
                  </p>
                  <div className="flex flex-wrap gap-2">
                    {agency.services.map((s) => (
                      <span
                        key={s.id}
                        className="inline-flex items-center rounded-[2px] border border-indigo-200 bg-indigo-50 px-2 py-[3px] text-[10px] font-semibold text-indigo-700"
                      >
                        {s.name}
                      </span>
                    ))}
                  </div>
                </div>
              )}
            </div>
          ) : (
            <div className="rounded-[3px] border border-dashed border-[#cbd5e1] p-4 text-[12px] text-slate-500">
              No agency assigned
            </div>
          )}
        </Section>

        {/* 3. Personal Information */}
        <Section title="Personal Information" description="Basic personal and contact details">
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <Field label="Name" value={user.name} />
            <Field label="Email" value={user.email} />
            <Field label="Position" value={user.position} />
            <Field label="Department" value={user.department} />
            <Field label="Office Location" value={user.office_location} />
            <Field label="Timezone" value={user.timezone} />
            <Field label="Contact Number" value={user.contact_number} />
            {user.bio && (
              <div className="sm:col-span-2">
                <Field label="Bio" value={user.bio} />
              </div>
            )}
          </div>
        </Section>

        {/* 4. Emergency Contact */}
        <Section title="Emergency Contact" description="Person to contact in case of emergency">
          {emergency.name ? (
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
              <Field label="Name" value={emergency.name} />
              <Field label="Relation" value={emergency.relation} />
              <Field label="Phone" value={emergency.phone} />
            </div>
          ) : (
            <div className="rounded-[3px] border border-dashed border-[#cbd5e1] p-4 text-[12px] text-slate-500">
              No emergency contact on file
            </div>
          )}
        </Section>

        {/* 5. Security */}
        <Section title="Security" description="Authentication and access control details">
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <Field label="Role" value={roleLabels[user.role] || user.role} />
            <div className="rounded-[3px] border border-[#e2e8f0] bg-[#f8fafc] px-3 py-2">
              <p className="text-[9px] font-extrabold uppercase tracking-[0.1em] text-slate-500">
                MFA Status
              </p>
              <p className="mt-1 text-[12px] font-semibold text-slate-700">
                <StatusPill label={user.mfa_enabled_at ? 'Enabled' : 'Disabled'} enabled={!!user.mfa_enabled_at} />
              </p>
            </div>
          </div>
        </Section>

        {/* 6. Notification Preferences */}
        <Section title="Notification Preferences" description="Which notifications the user receives">
          {Object.keys(notifications).length > 0 ? (
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {Object.entries(notifications).map(([key, value]) => (
                <div
                  key={key}
                  className="flex items-center justify-between rounded-[3px] border border-[#e2e8f0] bg-[#f8fafc] px-3 py-2"
                >
                  <p className="text-[9px] font-extrabold uppercase tracking-[0.1em] text-slate-500">
                    {formatNotificationLabel(key)}
                  </p>
                  <ToggleBadge enabled={!!value} />
                </div>
              ))}
            </div>
          ) : (
            <div className="rounded-[3px] border border-dashed border-[#cbd5e1] p-4 text-[12px] text-slate-500">
              No notification preferences configured
            </div>
          )}
        </Section>
      </div>
    </AppLayout>
  );
}
