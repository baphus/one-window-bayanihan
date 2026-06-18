import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import StatusBadge from '@/Components/ui/StatusBadge';

function formatNotificationLabel(key) {
  return key
    .replace(/^(email_on_|sms_on_)/i, '')
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (c) => c.toUpperCase());
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
      className={`inline-flex items-center gap-1 rounded-[3px] border px-2 py-[3px] text-[10px] font-bold uppercase tracking-wide ${
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
      className={`inline-flex items-center gap-1 rounded-[3px] border px-2 py-[3px] text-[10px] font-bold uppercase tracking-wide ${
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
      <div className="mb-4 text-xs font-bold uppercase tracking-widest text-slate-500">
        <Link href={route('admin.users.index')} className="transition hover:text-[#0b5384]">
          Users
        </Link>
        <span className="mx-2">/</span>
        <span>{user.name}</span>
      </div>

      {/* Header */}
      <div className="mb-6 flex items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl md:text-3xl font-extrabold font-headline tracking-tight text-slate-900">User Details</h1>
          <p className="text-sm text-slate-500 mt-1">View and manage user information.</p>
        </div>
        <Link
          href={route('admin.users.index')}
          className="px-4 py-2 text-sm font-medium text-white bg-[#0b5384] rounded-[3px] hover:bg-[#09416a] transition-colors shrink-0"
        >
          &larr; Back to Users
        </Link>
      </div>

      <div className="space-y-6">
        {/* 1. Profile */}
        <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
          <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-3">Profile</p>
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
                <span className="inline-flex items-center rounded-[3px] border border-blue-200 bg-blue-50 px-2 py-[3px] text-[10px] font-bold uppercase tracking-wide text-blue-700">
                  {roleLabels[user.role] || user.role}
                </span>
                <StatusBadge status={user.is_active ? 'ACTIVE' : 'INACTIVE'} />
              </div>
            </div>
          </div>
        </div>

        {/* 2. Agency Information */}
        <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
          <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-3">Agency Information</p>
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
                  <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2">Services</p>
                  <div className="flex flex-wrap gap-2">
                    {agency.services.map((s) => (
                      <span
                        key={s.id}
                        className="inline-flex items-center rounded-[3px] border border-indigo-200 bg-indigo-50 px-2 py-[3px] text-[10px] font-semibold text-indigo-700"
                      >
                        {s.name}
                      </span>
                    ))}
                  </div>
                </div>
              )}
            </div>
          ) : (
            <p className="text-sm text-slate-500">&mdash;</p>
          )}
        </div>

        {/* 3. Personal Information */}
        <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
          <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-3">Personal Information</p>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
              <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Name</p>
              <p className="text-sm font-semibold text-slate-900 mt-1">{user.name}</p>
            </div>
            <div>
              <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Email</p>
              <p className="text-sm font-semibold text-slate-900 mt-1">{user.email}</p>
            </div>
            <div>
              <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Position</p>
              <p className="text-sm font-semibold text-slate-900 mt-1">{user.position || '&mdash;'}</p>
            </div>
            <div>
              <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Department</p>
              <p className="text-sm font-semibold text-slate-900 mt-1">{user.department || '&mdash;'}</p>
            </div>
            <div>
              <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Office Location</p>
              <p className="text-sm font-semibold text-slate-900 mt-1">{user.office_location || '&mdash;'}</p>
            </div>
            <div>
              <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Timezone</p>
              <p className="text-sm font-semibold text-slate-900 mt-1">{user.timezone || '&mdash;'}</p>
            </div>
            <div>
              <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Contact Number</p>
              <p className="text-sm font-semibold text-slate-900 mt-1">{user.contact_number || '&mdash;'}</p>
            </div>
            {user.bio && (
              <div>
                <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Bio</p>
                <p className="text-sm font-semibold text-slate-900 mt-1">{user.bio}</p>
              </div>
            )}
          </div>
        </div>

        {/* 4. Emergency Contact */}
        <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
          <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-3">Emergency Contact</p>
          {emergency.name ? (
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div>
                <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Name</p>
                <p className="text-sm font-semibold text-slate-900 mt-1">{emergency.name}</p>
              </div>
              <div>
                <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Relation</p>
                <p className="text-sm font-semibold text-slate-900 mt-1">{emergency.relation || '&mdash;'}</p>
              </div>
              <div>
                <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Phone</p>
                <p className="text-sm font-semibold text-slate-900 mt-1">{emergency.phone || '&mdash;'}</p>
              </div>
            </div>
          ) : (
            <p className="text-sm text-slate-500">&mdash;</p>
          )}
        </div>

        {/* 5. Security */}
        <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
          <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-3">Security</p>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Role</p>
              <p className="text-sm font-semibold text-slate-900 mt-1">{roleLabels[user.role] || user.role}</p>
            </div>
            <div>
              <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">MFA Status</p>
              <div className="mt-1">
                <StatusPill label={user.mfa_enabled_at ? 'Enabled' : 'Disabled'} enabled={!!user.mfa_enabled_at} />
              </div>
            </div>
          </div>
        </div>

        {/* 6. Notification Preferences */}
        <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
          <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-3">Notification Preferences</p>
          {Object.keys(notifications).length > 0 ? (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
              {Object.entries(notifications).map(([key, value]) => (
                <div
                  key={key}
                  className="flex items-center justify-between rounded-[3px] border border-slate-200 bg-white px-3 py-2"
                >
                  <p className="text-[10px] font-bold uppercase tracking-widest text-slate-500">
                    {formatNotificationLabel(key)}
                  </p>
                  <ToggleBadge enabled={!!value} />
                </div>
              ))}
            </div>
          ) : (
            <p className="text-sm text-slate-500">&mdash;</p>
          )}
        </div>
      </div>
    </AppLayout>
  );
}
