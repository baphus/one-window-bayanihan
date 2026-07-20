import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import StatusBadge from '@/Components/ui/StatusBadge';

function formatNotificationLabel(key) {
  return key
    .replace(/^(email_on_|sms_on_)/i, '')
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (c) => c.toUpperCase());
}

function ToggleBadge({ enabled }) {
  return (
    <span
      className={`inline-flex items-center gap-1 rounded-md border px-2 py-[3px] text-[10px] font-bold uppercase tracking-wide ${
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
      className={`inline-flex items-center gap-1 rounded-md border px-2 py-[3px] text-[10px] font-bold uppercase tracking-wide ${
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
  const { auth } = usePage().props;

  return (
    <AppLayout title={`User Details — ${user.name}`}>
      <Head title={`User Details — ${user.name}`} />

      {/* Breadcrumb */}
      <div className="mb-4 text-xs font-bold uppercase tracking-widest text-slate-500">
        <Link href={route('admin.users.index')} className="transition hover:text-blue-900">
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
          className="px-4 py-2 text-sm font-medium text-white bg-blue-900 rounded-md hover:bg-blue-800 transition-colors shrink-0"
        >
          &larr; Back to Users
        </Link>
      </div>

      <div className="space-y-6">
        {/* 1. Profile */}
        <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
          <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-3">Profile</p>
          <div className="flex items-start gap-5">
            <span className="inline-flex shrink-0 overflow-hidden rounded-circle">
              {user.avatar_url ? (
                <img
                  src={user.avatar_url}
                  alt={`${user.name} avatar`}
                  className="h-16 w-16 rounded-circle border border-slate-200 object-cover"
                  onError={(e) => { e.target.style.display = 'none'; e.target.parentElement.querySelector('.avatar-fallback').classList.remove('hidden'); }}
                />
              ) : null}
              <span className={`${user.avatar_url ? 'avatar-fallback hidden' : ''} h-16 w-16 rounded-circle bg-blue-100 text-lg font-bold text-blue-900 flex items-center justify-center relative overflow-hidden`}>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="absolute w-3/5 h-3/5 text-blue-300">
                  <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                </svg>
                <span className="relative z-10">{(user.name || '').split(' ').map((n) => n[0]).join('').toUpperCase().slice(0, 2)}</span>
              </span>
            </span>
            <div className="space-y-2 pt-1">
              <p className="text-base font-bold text-slate-900">{user.name}</p>
              <p className="text-sm text-slate-500">{user.email}</p>
              <div className="flex items-center gap-2">
                <span className="inline-flex items-center rounded-md border border-blue-200 bg-blue-50 px-2 py-[3px] text-[10px] font-bold uppercase tracking-wide text-blue-700">
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
                        className="inline-flex items-center rounded-md border border-indigo-200 bg-indigo-50 px-2 py-[3px] text-[10px] font-semibold text-indigo-700"
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
              <p className="text-sm font-semibold text-slate-900 mt-1">{user.position || '\u2014'}</p>
            </div>
            <div>
              <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Department</p>
              <p className="text-sm font-semibold text-slate-900 mt-1">{user.department || '\u2014'}</p>
            </div>
            <div>
              <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Office Location</p>
              <p className="text-sm font-semibold text-slate-900 mt-1">{user.office_location || '\u2014'}</p>
            </div>
            <div>
              <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Timezone</p>
              <p className="text-sm font-semibold text-slate-900 mt-1">{user.timezone || '\u2014'}</p>
            </div>
            <div>
              <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Contact Number</p>
              <p className="text-sm font-semibold text-slate-900 mt-1">{user.contact_number || '\u2014'}</p>
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
                <p className="text-sm font-semibold text-slate-900 mt-1">{emergency.relation || '\u2014'}</p>
              </div>
              <div>
                <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Phone</p>
                <p className="text-sm font-semibold text-slate-900 mt-1">{emergency.phone || '\u2014'}</p>
              </div>
            </div>
          ) : (
            <p className="text-sm text-slate-500">&mdash;</p>
          )}
        </div>

        {/* 5. Security */}
        <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
          <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-3">Security</p>
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
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
              <div>
                <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Email Verified</p>
                <div className="mt-1">
                  {auth && user.id !== auth.user.id ? (
                    <button
                      type="button"
                      role="switch"
                      aria-checked={!!user.email_verified_at}
                      title={user.email_verified_at ? 'Verified' : 'Unverified'}
                      onClick={() => {
                        if (user.email_verified_at && !confirm('Unverifying this user will lock them out of the system until they re-verify via email. Continue?')) return;
                        router.patch(route('admin.users.verify', user.id), {}, { preserveScroll: true });
                      }}
                      className={`relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors focus:outline-none focus:ring-2 focus:ring-blue-900 focus:ring-offset-1 ${user.email_verified_at ? 'bg-emerald-500' : 'bg-slate-300'}`}
                    >
                      <span className={`inline-block h-4 w-4 transform rounded-full bg-white shadow transition ${user.email_verified_at ? 'translate-x-4' : 'translate-x-0'}`} />
                    </button>
                  ) : (
                    <StatusPill label={user.email_verified_at ? 'Verified' : 'Unverified'} enabled={!!user.email_verified_at} />
                  )}
                  <p className="text-[10px] text-slate-400 mt-1">Users invited via email are auto-verified upon registration.</p>
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
                  className="flex items-center justify-between rounded-md border border-slate-200 bg-white px-3 py-2"
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
