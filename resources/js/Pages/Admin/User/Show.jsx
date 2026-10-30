import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import StatusBadge from '@/Components/ui/StatusBadge';
import ConfirmDialog from '@/Components/ui/ConfirmDialog';
import UserFormModal from '@/Components/Admin/UserFormModal';
import { CardHeader, InfoField } from '@/Components/ui/CardSection';

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

export default function AdminUserShow({ user, agencies = [] }) {
  const [confirmResetMfa, setConfirmResetMfa] = useState(false);
  const [showEdit, setShowEdit] = useState(false);
  const roleLabels = {
    CASE_MANAGER: 'Case Manager',
    AGENCY: 'Agency Focal',
    ADMIN: 'System Admin',
    OFW: 'Citizen',
  };

  const client = user.client ?? null;
  const relatedOfw = client?.ofw_client ?? null;
  const clientTypeLabel = client?.type === 'NEXT_OF_KIN' ? 'Next of Kin' : client?.type === 'OFW' ? 'OFW' : null;
  const clientName = client
    ? [client.first_name, client.last_name].filter(Boolean).join(' ') || 'Unnamed client'
    : null;

  const agency = user.agency ?? null;
  const emergency = user.emergency_contact ?? {};
  const { auth } = usePage().props;

  const cardSectionClass = 'rounded-xl border border-[#dce3eb] bg-white p-5 shadow-[0_1px_3px_rgba(23,35,51,0.04)]';

  return (
    <AppLayout title={`User Details — ${user.name}`}>
      <Head title={`User Details — ${user.name}`} />

      <div className="min-h-full bg-slate-50">
        <div className="mx-auto max-w-[1440px] p-6">
          <div className="mb-5">
            <Link href={route('admin.users.index')} className="inline-flex items-center gap-1.5 text-[12px] font-semibold text-slate-500 transition hover:text-blue-900">
              <span className="material-symbols-outlined text-[16px]">arrow_back</span>
              Back to Users
            </Link>
          </div>

          <div className="mb-5 flex flex-wrap items-end justify-between gap-4">
            <div>
              <h1 className="text-[28px] font-extrabold leading-tight tracking-[-0.02em] text-[#172333]">User Details</h1>
              <p className="mt-2 text-[11px] leading-5 text-[#607080]">View and manage user information</p>
            </div>
            <button type="button" onClick={() => setShowEdit(true)} className="inline-flex min-h-[34px] items-center gap-1.5 rounded-md bg-blue-900 px-3.5 text-[12px] font-bold text-white transition hover:bg-blue-800">
              <span className="material-symbols-outlined text-[15px]">edit</span>
              Edit User
            </button>
          </div>

          <div className="grid grid-cols-1 gap-4 xl:grid-cols-12">
            <main className="space-y-4 xl:col-span-8">
        {/* 1. Profile */}
        <section className={cardSectionClass.replace(' p-5', '')}>
          <CardHeader title="Profile" />
          <div className="px-5 pb-5">
          <div className="flex items-start gap-5">
            <span className="inline-flex shrink-0 overflow-hidden rounded-full">
              {user.avatar_url ? (
                <img
                  src={user.avatar_url}
                  alt={`${user.name} avatar`}
                  className="h-16 w-16 rounded-full border border-slate-200 object-cover"
                  onError={(e) => { e.target.style.display = 'none'; e.target.parentElement.querySelector('.avatar-fallback').classList.remove('hidden'); }}
                />
              ) : null}
              <span className={`${user.avatar_url ? 'avatar-fallback hidden' : ''} h-16 w-16 rounded-full bg-blue-100 text-lg font-bold text-blue-900 flex items-center justify-center relative overflow-hidden`}>
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
        </section>

        {/* 2. Agency Information, or the citizen's client record.
            OFW and next-of-kin accounts share users.role = OFW, so the client's
            type is the only thing that says which one this is. */}
        {user.role === 'OFW' ? (
          <div className="rounded-xl border border-[#dce3eb] bg-white p-5 shadow-[0_1px_3px_rgba(23,35,51,0.04)]">
            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-3">Linked Client</p>
            {client ? (
              <div className="space-y-4">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Client Record</p>
                    <Link
                      href={route('clients.show', client.id)}
                      className="text-sm font-semibold text-blue-900 hover:underline mt-1 inline-block"
                    >
                      {clientName}
                    </Link>
                  </div>
                  <div>
                    <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Client Type</p>
                    <p className="text-sm font-semibold text-slate-900 mt-1">{clientTypeLabel ?? '\u2014'}</p>
                  </div>
                </div>
                {client.type === 'NEXT_OF_KIN' && (
                  <div>
                    <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Related OFW</p>
                    {relatedOfw ? (
                      <Link
                        href={route('clients.show', relatedOfw.id)}
                        className="text-sm font-semibold text-blue-900 hover:underline mt-1 inline-block"
                      >
                        {[relatedOfw.first_name, relatedOfw.last_name].filter(Boolean).join(' ') || 'Unnamed client'}
                      </Link>
                    ) : (
                      <p className="text-sm text-slate-500 mt-1">Not linked to an OFW record.</p>
                    )}
                  </div>
                )}
              </div>
            ) : (
              <p className="text-sm text-slate-500">This account has no linked client record.</p>
            )}
          </div>
        ) : (
        <div className="rounded-xl border border-[#dce3eb] bg-white p-5 shadow-[0_1px_3px_rgba(23,35,51,0.04)]">
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
        )}

        {/* 3. Personal Information */}
        <div className="rounded-xl border border-[#dce3eb] bg-white p-5 shadow-[0_1px_3px_rgba(23,35,51,0.04)]">
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
              <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Occupation</p>
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
        <div className="rounded-xl border border-[#dce3eb] bg-white p-5 shadow-[0_1px_3px_rgba(23,35,51,0.04)]">
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
        <div className="rounded-xl border border-[#dce3eb] bg-white p-5 shadow-[0_1px_3px_rgba(23,35,51,0.04)]">
          <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-3">Security</p>
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Role</p>
              <p className="text-sm font-semibold text-slate-900 mt-1">{roleLabels[user.role] || user.role}</p>
            </div>
            <div>
              <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">MFA Status</p>
              <div className="mt-1 flex items-center gap-2">
                <StatusPill label={user.mfa_enabled_at ? 'Enabled' : 'Disabled'} enabled={!!user.mfa_enabled_at} />
                {user.mfa_enabled_at && auth && user.id !== auth.user.id && (
                  <button
                    type="button"
                    onClick={() => setConfirmResetMfa(true)}
                    className="text-[10px] font-bold text-red-600 hover:text-red-800 underline transition-colors"
                  >
                    Reset MFA
                  </button>
                )}
              </div>
            </div>
              <div>
                <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Email Verified</p>
                <div className="mt-1">
                  <span
                    title={user.email_verified_at ? 'Verified' : 'Unverified'}
                    aria-label={user.email_verified_at ? 'Verified' : 'Unverified'}
                    className={`inline-flex h-6 w-6 items-center justify-center rounded-full text-sm font-bold ${user.email_verified_at ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-500'}`}
                  >
                    {user.email_verified_at ? '✓' : '×'}
                  </span>
                  <p className="text-[10px] text-slate-400 mt-1">Users invited via email are auto-verified upon registration.</p>
                </div>
              </div>
          </div>
        </div>

            </main>

            <aside className="space-y-4 xl:col-span-4">
              <section className={cardSectionClass.replace(' p-5', '')}>
                <CardHeader title="Account Summary" />
                <div className="space-y-4 px-5 pb-5">
                  <InfoField label="Name" value={user.name} />
                  <InfoField label="Email" value={user.email} />
                  <InfoField label="Role" value={roleLabels[user.role] || user.role} />
                  <InfoField label="Status" value={<StatusBadge status={user.is_active ? 'ACTIVE' : 'INACTIVE'} size="sm" />} />
                </div>
              </section>
            </aside>
          </div>
        </div>
      </div>
      {showEdit && (
        <UserFormModal
          user={user}
          agencies={agencies}
          onClose={() => setShowEdit(false)}
        />
      )}

      <ConfirmDialog
        open={confirmResetMfa}
        title="Reset MFA"
        message="This will disable two-factor authentication for this user and invalidate any existing recovery codes. Continue?"
        confirmLabel="Reset"
        tone="danger"
        onConfirm={() => {
          setConfirmResetMfa(false);
          const password = window.prompt('Enter your password to confirm MFA reset:');
          if (password) {
            router.post(route('admin.users.reset-mfa', user.id), { password }, { preserveScroll: true });
          }
        }}
        onCancel={() => setConfirmResetMfa(false)}
      />
    </AppLayout>
  );
}
