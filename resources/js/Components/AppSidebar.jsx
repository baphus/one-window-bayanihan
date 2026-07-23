import { Link, usePage, router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
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
      { name: 'My Drafts', href: '/cases/drafts', icon: 'drafts' },
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
      { name: 'My Drafts', href: '/cases/drafts', icon: 'drafts' },
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
      { name: 'Active Sessions', href: '/admin/system/active-sessions', icon: 'phonelink' },
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
  const [collapsed, setCollapsed] = useState(() => {
    try { return localStorage.getItem('owb-sidebar-collapsed') === 'true'; }
    catch { return false; }
  });
  const [unreadCount, setUnreadCount] = useState(() => {
    try { return parseInt(localStorage.getItem('owb-unread-count') || '0', 10) || 0; }
    catch { return 0; }
  });
  const [hoveredItem, setHoveredItem] = useState(null);
  const sidebarNavRef = useRef(null);

  // Restore sidebar scroll position after mount
  useEffect(() => {
    const nav = sidebarNavRef.current;
    if (!nav) return;
    const saved = sessionStorage.getItem('owb-sidebar-scroll');
    if (saved) nav.scrollTop = parseInt(saved, 10);
  }, []);

  const handleSidebarScroll = useCallback((e) => {
    sessionStorage.setItem('owb-sidebar-scroll', e.target.scrollTop);
  }, []);

  // Fetch unread notification count
  useEffect(() => {
    let timer;
    const fetchCount = () => {
      fetch('/notifications/unread-count', {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      })
        .then((res) => res.ok ? res.json() : null)
        .then((data) => {
          if (data?.count != null) {
            setUnreadCount(data.count);
            try { localStorage.setItem('owb-unread-count', String(data.count)); }
            catch { /* noop */ }
          }
        })
        .catch(() => {});
    };
    fetchCount();
    timer = setInterval(fetchCount, 60000);
    return () => clearInterval(timer);
  }, []);

  const toggleCollapsed = useCallback(() => {
    setCollapsed((prev) => {
      const next = !prev;
      try { localStorage.setItem('owb-sidebar-collapsed', String(next)); }
      catch { /* noop */ }
      return next;
    });
  }, []);

  const navigation = useMemo(() => {
    return navByRole[user?.role] || [];
  }, [user]);

  const isActive = (href) => {
    if (href === '/dashboard') return url === '/dashboard';
    if (href === '/cases') return url.startsWith('/cases') && !url.startsWith('/cases/drafts');
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
    <aside className={`${collapsed ? 'w-16' : 'w-72'} transition-[width] duration-200 ease-in-out bg-white border-r border-slate-200 hidden md:flex shrink-0 h-screen font-body flex-col`}>
      {/* Logo */}
      <div className={`h-24 flex items-center border-b border-transparent shrink-0 ${collapsed ? 'flex-col justify-center px-2 gap-2' : 'px-6'}`}>
        <Link href="/" className={`flex items-center gap-2 min-w-0 ${collapsed ? 'justify-center' : 'flex-1'}`}>
          <div className="w-10 h-10 flex items-center justify-center shrink-0">
            <img src="/logo.png" alt="One Window Bayanihan Logo" className="w-full h-full object-contain" />
          </div>
          {!collapsed && (
            <div className="flex flex-col min-w-0 flex-1">
              <span className="text-[13px] font-bold tracking-tight text-blue-950 leading-tight" style={{ fontFamily: "'Outfit', sans-serif" }}>One Window Bayanihan</span>
              <span className="text-[9px] font-semibold uppercase tracking-[0.1em] text-slate-500">Assistance Program</span>
            </div>
          )}
        </Link>
        {!collapsed && (
          <button
            type="button"
            onClick={toggleCollapsed}
            className="shrink-0 ml-1 p-1 rounded-md text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
            title="Collapse sidebar"
          >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="w-5 h-5">
              <rect x="3" y="3" width="18" height="18" rx="2" />
              <line x1="10.2" y1="3" x2="10.2" y2="21" />
            </svg>
          </button>
        )}
      </div>
      {collapsed && (
        <button
          type="button"
          onClick={toggleCollapsed}
          className="flex items-center justify-center py-2 border-b border-slate-200 text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
          title="Expand sidebar"
        >
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="w-5 h-5">
            <rect x="3" y="3" width="18" height="18" rx="2" />
            <line x1="10.2" y1="3" x2="10.2" y2="21" />
          </svg>
        </button>
      )}

      {/* Nav */}
      <nav ref={sidebarNavRef} onScroll={handleSidebarScroll} data-tour="sidebar-nav" className="flex-1 min-h-0 overflow-y-auto pt-3 pb-4 owb-scroll">
        {navigation.map((group) => (
          <div key={group.label} className="mb-3">
            {!collapsed && (
              <p className="px-8 pb-2 text-[10px] font-bold font-label uppercase tracking-[0.09em] text-slate-500">
                {group.label}
              </p>
            )}
            <div className={`space-y-1 ${collapsed ? 'pl-0' : 'pl-2'}`}>
              {group.items.map((item) => {
                const active = isActive(item.href);
                const isNotification = item.name === 'Notifications';

                const linkClass = collapsed
                  ? `group relative flex items-center justify-center py-3.5 text-[14px] font-label transition-all duration-200 ${
                      active
                        ? 'bg-slate-100/60 text-blue-900 font-bold pl-[18px] pr-[14px] before:content-[""] before:absolute before:left-0 before:inset-y-2 before:w-1 before:bg-blue-900 before:rounded-r'
                        : 'text-slate-600 font-medium hover:bg-slate-100/40 hover:text-slate-900 px-[18px]'
                    }`
                  : `group relative flex items-center gap-4 py-3.5 text-[14px] font-label transition-all duration-200 ${
                      active
                        ? 'bg-slate-100/60 text-blue-900 font-bold pl-10 pr-8 before:content-[""] before:absolute before:left-0 before:inset-y-2 before:w-1 before:bg-blue-900 before:rounded-r'
                        : 'text-slate-600 font-medium hover:bg-slate-100/40 hover:text-slate-900 px-8'
                    }`;

                const icon = (
                  <span
                    key={`icon-${item.name}-${active}`}
                    className={`material-symbols-outlined text-[22px] shrink-0 transition-transform duration-150 group-hover:scale-110 ${
                      active ? 'text-blue-900 owb-icon-active-pop' : 'text-slate-600'
                    }`}
                    style={{ fontVariationSettings: `'FILL' ${active ? '1' : '0'}, 'wght' ${active ? '700' : '400'}` }}
                  >
                    {item.icon}
                  </span>
                );

                const badge = isNotification && unreadCount > 0 && (
                  collapsed ? (
                    <span className="absolute -right-0.5 -top-0.5 flex items-center justify-center min-w-[16px] h-[16px] px-0.5 text-[9px] font-bold text-white bg-red-500 rounded-full leading-none">
                      {unreadCount > 99 ? '99+' : unreadCount}
                    </span>
                  ) : (
                    <span className="ml-auto flex items-center justify-center min-w-[20px] h-[18px] px-1 text-[10px] font-bold text-white bg-red-500 rounded-full leading-none shrink-0">
                      {unreadCount > 99 ? '99+' : unreadCount}
                    </span>
                  )
                );

                const content = collapsed ? (
                  <>
                    <span className="relative">
                      {icon}
                      {badge}
                    </span>
                  </>
                ) : (
                  <>
                    <span className="relative">
                      {icon}
                    </span>
                    <span className="flex-1 min-w-0 truncate">{item.name}</span>
                    {badge}
                  </>
                );

                const sharedAttrs = {
                  className: linkClass,
                  ...(item.name === 'Help Center' ? { 'data-tour': 'sidebar-help' } : {}),
                  ...(collapsed ? {
                    onMouseEnter: (e) => {
                      const rect = e.currentTarget.getBoundingClientRect();
                      setHoveredItem({ name: item.name, top: rect.top + rect.height / 2 });
                    },
                    onMouseLeave: () => setHoveredItem(null),
                  } : {}),
                };

                if (item.external) {
                  return (
                    <a
                      key={item.name}
                      href={item.href}
                      target="_blank"
                      rel="noopener noreferrer"
                      {...sharedAttrs}
                    >
                      {content}
                    </a>
                  );
                }

                return (
                  <Link
                    key={item.name}
                    href={item.href}
                    {...sharedAttrs}
                  >
                    {content}
                  </Link>
                );
              })}
            </div>
          </div>
        ))}
      </nav>

      {/* User section */}
      <div className="flex flex-col shrink-0">
        <div className={`bg-white border-t border-slate-200 ${collapsed ? 'px-2 py-4' : 'px-5 py-5'}`}>
          <div className={`flex items-center ${collapsed ? 'justify-center gap-0' : 'gap-3'}`}>
            <UserAvatar user={user} size={collapsed ? 'md' : 'lg'} onClick={() => setPeerProfileUser(user)} />
            {!collapsed && (
            <div className="flex flex-col min-w-0">
                <span className="text-[13px] text-blue-950 font-bold leading-none truncate">{user?.name}</span>
                <span className="text-[10px] font-bold tracking-[0.06em] text-slate-500 mt-1 uppercase truncate">
                  {roleLabels[user?.role] || user?.role}
                </span>
              </div>
            )}
            {!collapsed && <PageGuideButton />}
            {!collapsed && <NotificationPanel />}
          </div>

          {!collapsed && user?.onboarding_completed_at && (
            <button
              data-tour="sidebar-tour-replay"
              onClick={handleReplayTour}
              className="flex items-center gap-2 px-4 py-2 mt-3 text-[12px] font-bold text-slate-500 hover:text-indigo-600 hover:bg-slate-50 transition-colors w-full rounded-md"
            >
              <span className="material-symbols-outlined text-[18px]" style={{ fontVariationSettings: "'FILL' 0, 'wght' 400" }}>travel_explore</span>
              Take a Tour
            </button>
          )}

          <div className={`mt-4 flex items-center ${collapsed ? 'flex-col gap-2' : 'gap-2'}`}>
            <Link
              href={route('profile.edit')}
              className={`flex items-center justify-center rounded-md border border-slate-200 text-[11px] font-bold text-slate-600 hover:bg-slate-50 hover:text-blue-900 transition-all ${
                collapsed ? 'w-10 h-8' : 'flex-1 gap-2 px-3 py-1.5'
              }`}
              title={collapsed ? 'Edit Profile' : undefined}
            >
              <span className="material-symbols-outlined text-[14px]">edit</span>
              {!collapsed && <span>EDIT PROFILE</span>}
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

      {/* Collapsed nav tooltip */}
      {collapsed && hoveredItem && (
        <div
          className="fixed z-50 px-2.5 py-1 rounded-md bg-white text-slate-700 text-[13px] font-medium whitespace-nowrap shadow-sm pointer-events-none"
          style={{ top: `${hoveredItem.top}px`, left: '4.5rem', transform: 'translateY(-50%)' }}
        >
          {hoveredItem.name}
        </div>
      )}
    </aside>
  );
}
