import { Link, usePage, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import UserAvatar from '@/Components/ui/UserAvatar';
import NotificationPanel from '@/Components/ui/NotificationPanel';
import PageGuideButton from '@/Components/PageGuideButton';
import PeerProfileModal from '@/Components/PeerProfileModal';
import { useOnboarding } from '@/Onboarding/OnboardingProvider';
import { replayOnboarding } from '@/Onboarding/api';
import { getTourConfig } from '@/Onboarding/index';

export const navByRole = {
  CASE_MANAGER: [
    { label: 'Overview', items: [
      { name: 'Dashboard', href: '/dashboard', icon: 'dashboard' },
    ]},
    { label: 'Notifications', items: [
      { name: 'Notifications', href: '/notifications/page', icon: 'notifications' },
    ]},
    { label: 'Management', items: [
      { name: 'Cases', href: '/cases', icon: 'folder' },
      { name: 'Clients', href: '/clients', icon: 'people' },
      { name: 'Referrals', href: '/referrals', icon: 'send' },
      { name: 'Overdue Referrals', href: '/overdue-referrals', icon: 'warning' },
      { name: 'Stakeholders', href: '/stakeholders', icon: 'account_balance' },
    ]},
    { label: 'Reports', items: [
      { name: 'Reports', href: '/reports', icon: 'summarize' },
      { name: 'Audit Logs', href: '/audit-logs', icon: 'history' },
    ]},
    { label: 'Resources', items: [
      { name: 'Help Center', href: '/help', icon: 'help', external: true },
    ]},
  ],
  AGENCY: [
    { label: 'Overview', items: [
      { name: 'Dashboard', href: '/dashboard', icon: 'dashboard' },
    ]},
    { label: 'Notifications', items: [
      { name: 'Notifications', href: '/notifications/page', icon: 'notifications' },
    ]},
    { label: 'Operations', items: [
      { name: 'Referred Cases', href: '/referrals', icon: 'assignment' },
      { name: 'Overdue Referrals', href: '/overdue-referrals', icon: 'warning' },
      { name: 'Services', href: '/services', icon: 'medical_services' },
    ]},
    { label: 'Feedback', items: [
      { name: 'Survey Forms', href: '/survey-forms', icon: 'assignment' },
      { name: 'Survey Responses', href: '/surveys', icon: 'poll' },
    ]},
    { label: 'Reports', items: [
      { name: 'Reports', href: '/reports', icon: 'summarize' },
      { name: 'Audit Logs', href: '/audit-logs', icon: 'history' },
    ]},
    { label: 'Resources', items: [
      { name: 'Help Center', href: '/help', icon: 'help', external: true },
    ]},
  ],
  ADMIN: [
    { label: 'Overview', items: [
      { name: 'Dashboard', href: '/dashboard', icon: 'dashboard' },
    ]},
    { label: 'Notifications', items: [
      { name: 'Notifications', href: '/notifications/page', icon: 'notifications' },
    ]},
    { label: 'Reports', items: [
      { name: 'Reports', href: '/reports', icon: 'summarize' },
      { name: 'Survey Responses', href: '/surveys', icon: 'poll' },
    ]},
    { label: 'Case Operations', items: [
      { name: 'Cases', href: '/cases', icon: 'folder' },
      { name: 'Clients', href: '/clients', icon: 'people' },
      { name: 'Referrals', href: '/referrals', icon: 'send' },
      { name: 'Overdue Referrals', href: '/overdue-referrals', icon: 'warning' },
      { name: 'Stakeholders', href: '/stakeholders', icon: 'account_balance' },
    ]},
    { label: 'Agency Management', items: [
      { name: 'Agencies', href: '/admin/agencies', icon: 'account_balance' },
      { name: 'Services', href: '/admin/services', icon: 'medical_services' },
      { name: 'Users', href: '/admin/users', icon: 'manage_accounts' },
    ]},
    { label: 'System Health', items: [
      { name: 'System Logs', href: '/admin/system/logs', icon: 'list_alt' },
      { name: 'Email Logs', href: '/admin/system/email-logs', icon: 'mail' },
    ]},
    { label: 'Administration', items: [
      { name: 'Audit Logs', href: '/audit-logs', icon: 'history' },
      { name: 'Case Statuses', href: '/admin/case-statuses', icon: 'label' },
      { name: 'Case Categories', href: '/admin/case-categories', icon: 'topic' },
      { name: 'Case Issues', href: '/admin/case-issues', icon: 'feedback' },
      { name: 'Data Export', href: '/admin/data-export', icon: 'file_download' },
      { name: 'Maintenance Mode', href: '/admin/system/maintenance', icon: 'construction' },
    ]},
    { label: 'Settings', items: [
      { name: 'System Settings', href: '/admin/system-settings', icon: 'settings' },
      { name: 'Security & Auth', href: '/admin/system/security', icon: 'security' },
    ]},
    { label: 'Resources', items: [
      { name: 'Help Center', href: '/help', icon: 'help', external: true },
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
  const { startTour } = useOnboarding();
  const [peerProfileUser, setPeerProfileUser] = useState(null);

  const navigation = useMemo(() => {
    return navByRole[user?.role] || [];
  }, [user]);

  const isActive = (href) => {
    if (href === '/dashboard') return url === '/dashboard';
    if (href === '/cases') return url.startsWith('/cases');
    return url.startsWith(href);
  };

  const handleReplayTour = async () => {
    try {
      await replayOnboarding();
      const config = getTourConfig(user?.role);
      if (!config) return;

      // The tour only overlays pages in its config — navigate to its first
      // page before starting so replay works from anywhere in the app.
      const firstPath = new URL(route(config.pages[0].route), window.location.origin).pathname;
      if (window.location.pathname === firstPath) {
        startTour(config);
      } else {
        router.visit(firstPath, { onSuccess: () => startTour(config) });
      }
    } catch {
      // silently fail — API error shouldn't break the UI
    }
  };

  return (
    <aside className="w-64 bg-[#f8f9fa] border-r border-slate-200 hidden md:flex shrink-0 h-screen font-body flex-col">
      <div className="h-24 flex items-center px-8 border-b border-transparent shrink-0">
        <Link href="/" className="flex items-center gap-3 w-full">
          <div className="w-10 h-10 flex items-center justify-center shrink-0">
            <img src="/logo.png" alt="One Window Bayanihan Logo" className="w-full h-full object-contain" />
          </div>
          <div className="flex flex-col">
            <span className="text-[13px] font-bold tracking-tight text-blue-950 leading-tight" style={{ fontFamily: "'Outfit', sans-serif" }}>One Window Bayanihan</span>
            <span className="text-[9px] font-semibold uppercase tracking-[0.1em] text-slate-500">Assistance Program</span>
          </div>
        </Link>
      </div>

      <nav data-tour="sidebar-nav" className="flex-1 min-h-0 overflow-y-auto pt-3 pb-4">
          {navigation.map((group) => (
            <div key={group.label} className="mb-3">
              <p className="px-8 pb-2 text-[10px] font-bold font-label uppercase tracking-[0.09em] text-slate-500">
                {group.label}
              </p>
              <div className="space-y-1">
                {group.items.map((item) => {
                  const active = isActive(item.href);
                  const linkClass = `flex items-center gap-4 px-8 py-3.5 text-[14px] font-label transition-colors border-l-4 ${
                    active
                      ? 'bg-slate-100/60 text-blue-900 font-bold border-blue-900'
                      : 'text-slate-600 font-medium hover:bg-slate-100/40 hover:text-slate-900 border-transparent'
                  }`;

                  const content = (
                    <>
                      <span className={`material-symbols-outlined text-[22px] ${active ? 'text-blue-900' : 'text-slate-600'}`} style={{ fontVariationSettings: `'FILL' ${active ? '1' : '0'}, 'wght' ${active ? '700' : '400'}` }}>
                        {item.icon}
                      </span>
                      {item.name}
                    </>
                  );

                  if (item.external) {
                    return (
                      <a
                        key={item.name}
                        href={item.href}
                        target="_blank"
                        rel="noopener noreferrer"
                        className={linkClass}
                        {...(item.name === 'Help Center' ? { 'data-tour': 'sidebar-help' } : {})}
                      >
                        {content}
                      </a>
                    );
                  }

                  return (
                    <Link
                      key={item.name}
                      href={item.href}
                      className={linkClass}
                    >
                      {content}
                    </Link>
                  );
                })}
              </div>
            </div>
          ))}
        </nav>

      <div className="flex flex-col shrink-0">
        <div className="px-5 py-5 bg-white border-t border-slate-200">
          <div className="flex items-center gap-3">
            <UserAvatar user={user} size="lg" onClick={() => setPeerProfileUser(user)} />
            <div className="flex flex-col min-w-0 flex-1">
              <span className="text-[13px] text-blue-950 font-bold leading-none truncate">{user?.name}</span>
              <span className="text-[10px] font-bold tracking-[0.06em] text-slate-500 mt-1 uppercase truncate">
                {roleLabels[user?.role] || user?.role}
              </span>
            </div>
            <PageGuideButton />
            <NotificationPanel />
          </div>

          {user?.onboarding_completed_at && (
            <button
              data-tour="sidebar-tour-replay"
              onClick={handleReplayTour}
              className="flex items-center gap-2 px-4 py-2 mt-3 text-[12px] font-bold text-slate-500 hover:text-indigo-600 hover:bg-slate-50 transition-colors w-full rounded-md"
            >
              <span className="material-symbols-outlined text-[18px]" style={{ fontVariationSettings: "'FILL' 0, 'wght' 400" }}>travel_explore</span>
              Take a Tour
            </button>
          )}

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

      <PeerProfileModal user={peerProfileUser} show={!!peerProfileUser} onClose={() => setPeerProfileUser(null)} />
    </aside>
  );
}
