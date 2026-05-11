import { Link, usePage, router } from '@inertiajs/react';
import { useMemo } from 'react';

const navByRole = {
  CASE_MANAGER: [
    { label: 'Overview', items: [
      { name: 'Dashboard', href: '/dashboard', icon: 'dashboard' },
    ]},
    { label: 'Management', items: [
      { name: 'Cases', href: '/cases', icon: 'folder' },
      { name: 'Clients', href: '/clients', icon: 'people' },
      { name: 'Referrals', href: '/referrals', icon: 'send' },
      { name: 'Stakeholders', href: '/stakeholders', icon: 'account_balance' },
    ]},
    { label: 'Reports', items: [
      { name: 'Reports', href: '/reports', icon: 'summarize' },
      { name: 'Analytics', href: '/analytics', icon: 'bar_chart' },
      { name: 'Audit Logs', href: '/audit-logs', icon: 'history' },
    ]},
  ],
  AGENCY: [
    { label: 'Overview', items: [
      { name: 'Dashboard', href: '/dashboard', icon: 'dashboard' },
    ]},
    { label: 'Operations', items: [
      { name: 'Referred Cases', href: '/referrals', icon: 'assignment' },
      { name: 'Services', href: '/services', icon: 'medical_services' },
    ]},
    { label: 'Feedback', items: [
      { name: 'Feedbacks', href: '/feedbacks', icon: 'feedback' },
    ]},
    { label: 'Reports', items: [
      { name: 'Analytics', href: '/analytics', icon: 'bar_chart' },
      { name: 'Activity Logs', href: '/activity-logs', icon: 'history' },
    ]},
  ],
  ADMIN: [
    { label: 'Overview', items: [
      { name: 'Dashboard', href: '/dashboard', icon: 'dashboard' },
    ]},
    { label: 'Management', items: [
      { name: 'Cases', href: '/cases', icon: 'folder' },
      { name: 'Clients', href: '/clients', icon: 'people' },
      { name: 'Agencies', href: '/agencies', icon: 'account_balance' },
      { name: 'Services', href: '/services', icon: 'medical_services' },
      { name: 'Referrals', href: '/referrals', icon: 'send' },
      { name: 'Users', href: '/users', icon: 'manage_accounts' },
    ]},
    { label: 'Settings', items: [
      { name: 'Activity Logs', href: '/activity-logs', icon: 'history' },
      { name: 'System Settings', href: '/admin/system-settings', icon: 'settings' },
    ]},
  ],
};

const roleLabels = {
  CASE_MANAGER: 'Case Manager',
  AGENCY: 'Agency Focal',
  ADMIN: 'System Admin',
};

export default function AppSidebar() {
  const { url } = usePage();
  const user = usePage().props.auth.user;

  const navigation = useMemo(() => {
    return navByRole[user?.role] || [];
  }, [user]);

  const initials = user?.name
    ? user.name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
    : '??';

  const isActive = (href) => {
    if (href === '/dashboard') return url === '/dashboard';
    return url.startsWith(href);
  };

  return (
    <aside className="w-64 bg-[#f8f9fa] border-r border-slate-200 hidden md:flex shrink-0 h-screen font-body flex-col">
      <div className="flex-1 min-h-0">
        <div className="h-24 flex items-center px-8 border-b border-transparent">
          <Link href="/" className="flex items-center gap-3 w-full">
            <div className="w-10 h-10 flex items-center justify-center shrink-0">
              <img src="/logo.png" alt="Bayanihan Logo" className="w-full h-full object-contain" />
            </div>
            <div className="flex flex-col">
              <span className="text-lg font-extrabold font-headline tracking-tight text-blue-950">Bayanihan</span>
              <span className="text-[10px] font-bold font-label uppercase tracking-[0.08em] text-slate-500">Region VII</span>
            </div>
          </Link>
        </div>

        <nav className="h-[calc(100%-6rem)] overflow-y-auto pt-3 pb-4">
          {navigation.map((group) => (
            <div key={group.label} className="mb-3">
              <p className="px-8 pb-2 text-[10px] font-bold font-label uppercase tracking-[0.09em] text-slate-500">
                {group.label}
              </p>
              <div className="space-y-1">
                {group.items.map((item) => {
                  const active = isActive(item.href);
                  return (
                    <Link
                      key={item.name}
                      href={item.href}
                      className={`flex items-center gap-4 px-8 py-3.5 text-[14px] font-label transition-colors border-l-4 ${
                        active
                          ? 'bg-slate-100/60 text-blue-900 font-bold border-blue-900'
                          : 'text-slate-600 font-medium hover:bg-slate-100/40 hover:text-slate-900 border-transparent'
                      }`}
                    >
                      <span className={`material-symbols-outlined text-[22px] ${active ? 'text-blue-900' : 'text-slate-600'}`} style={{ fontVariationSettings: `'FILL' ${active ? '1' : '0'}, 'wght' ${active ? '700' : '400'}` }}>
                        {item.icon}
                      </span>
                      {item.name}
                    </Link>
                  );
                })}
              </div>
            </div>
          ))}
        </nav>
      </div>

      <div className="flex flex-col">
        <div className="px-5 py-5 bg-white border-t border-slate-200">
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-900 border border-blue-200">
              {initials}
            </div>
            <div className="flex flex-col min-w-0 flex-1">
              <span className="text-[13px] text-blue-950 font-bold leading-none truncate">{user?.name}</span>
              <span className="text-[10px] font-bold tracking-[0.06em] text-slate-500 mt-1 uppercase truncate">
                {roleLabels[user?.role] || user?.role}
              </span>
            </div>
          </div>

          <div className="mt-4 flex items-center gap-2">
            <Link
              href={route('profile.edit')}
              className="flex flex-1 items-center justify-center gap-2 px-3 py-1.5 rounded-md border border-slate-200 text-[11px] font-bold text-slate-600 hover:bg-slate-50 hover:text-blue-900 transition-all"
            >
              <span className="material-symbols-outlined text-[14px]">edit</span>
              <span>EDIT PROFILE</span>
            </Link>
            <button
              onClick={() => router.post(route('logout'))}
              className="flex items-center justify-center w-10 h-8 rounded-md border border-red-100 text-red-600 hover:bg-red-50 transition-all"
              title="Log Out"
            >
              <span className="material-symbols-outlined text-[14px]">logout</span>
            </button>
          </div>
        </div>
      </div>
    </aside>
  );
}
