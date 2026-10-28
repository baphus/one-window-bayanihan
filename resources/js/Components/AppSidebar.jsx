import { Link, usePage, router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import UserAvatar from '@/Components/ui/UserAvatar';
import PeerProfileModal from '@/Components/PeerProfileModal';
import safeRoute from '@/utils/safeRoute';
import { clearAllDraftBackups } from '@/Hooks/useLocalStorageDraft';


export const navByRole = {
  CASE_MANAGER: [
    { label: 'Overview', items: [
      { name: 'Dashboard', href: '/dashboard', route: 'dashboard', icon: 'dashboard' },
    ]},
    { label: 'Notifications', items: [
      { name: 'Notifications', href: '/notifications/page', route: 'notifications.page', icon: 'notifications' },
    ]},
    { label: 'Management', items: [
      { name: 'Cases', href: '/cases', route: 'cases.index', icon: 'folder' },
      { name: 'Pending Reviews', href: '/cases/review-pending', route: 'cases.review-pending', icon: 'pending_actions', badge: 'pendingReviewCount' },
      { name: 'Case Trash', href: '/cases/trash', route: 'cases.trash', icon: 'delete_outline' },
      { name: 'Clients', href: '/clients', route: 'clients.index', icon: 'people' },
      { name: 'Referrals', href: '/referrals', route: 'referrals.index', icon: 'send' },
      { name: 'Overdue Referrals', href: '/overdue-referrals', route: 'overdue-referrals.index', icon: 'warning' },
      { name: 'Stakeholders', href: '/stakeholders', route: 'stakeholders.index', icon: 'account_balance' },
    ]},
    { label: 'Reports', items: [
      { name: 'Reports', href: '/reports', route: 'reports.index', icon: 'summarize' },
      { name: 'Activity', href: '/activity', route: 'activity.index', icon: 'history' },
    ]},
    { label: 'Resources', items: [
      { name: 'Help Center', href: '/help', route: 'helpdesk.index', icon: 'help', external: true },
    ]},
  ],
  AGENCY: [
    { label: 'Overview', items: [
      { name: 'Dashboard', href: '/dashboard', route: 'dashboard', icon: 'dashboard' },
    ]},
    { label: 'Notifications', items: [
      { name: 'Notifications', href: '/notifications/page', route: 'notifications.page', icon: 'notifications' },
    ]},
    { label: 'Operations', items: [
      { name: 'Referred Cases', href: '/referrals', route: 'referrals.index', icon: 'assignment' },
      { name: 'Overdue Referrals', href: '/overdue-referrals', route: 'overdue-referrals.index', icon: 'warning' },
      { name: 'Services', href: '/services', route: 'agency.services.index', icon: 'medical_services' },
    ]},
    { label: 'Feedback', items: [
      { name: 'Survey Forms', href: '/survey-forms', route: 'survey.forms.index', icon: 'assignment' },
      { name: 'Survey Responses', href: '/surveys', route: 'survey.responses.index', icon: 'poll' },
    ]},
    { label: 'Reports', items: [
      { name: 'Reports', href: '/reports', route: 'reports.index', icon: 'summarize' },
      { name: 'Activity', href: '/activity', route: 'activity.index', icon: 'history' },
    ]},
    { label: 'Resources', items: [
      { name: 'Help Center', href: '/help', route: 'helpdesk.index', icon: 'help', external: true },
    ]},
  ],
  ADMIN: [
    { label: 'Overview', items: [
      { name: 'Dashboard', href: '/dashboard', route: 'dashboard', icon: 'dashboard' },
    ]},
    { label: 'Notifications', items: [
      { name: 'Notifications', href: '/notifications/page', route: 'notifications.page', icon: 'notifications' },
    ]},
    { label: 'Reports', items: [
      { name: 'Reports', href: '/reports', route: 'reports.index', icon: 'summarize' },
      { name: 'Survey Responses', href: '/surveys', route: 'survey.responses.index', icon: 'poll' },
    ]},
    { label: 'Case Operations', items: [
      { name: 'Cases', href: '/cases', route: 'cases.index', icon: 'folder' },
      { name: 'Pending Reviews', href: '/cases/review-pending', route: 'cases.review-pending', icon: 'pending_actions', badge: 'pendingReviewCount' },
      { name: 'Case Trash', href: '/cases/trash', route: 'cases.trash', icon: 'delete_outline' },
      { name: 'Clients', href: '/clients', route: 'clients.index', icon: 'people' },
      { name: 'Referrals', href: '/referrals', route: 'referrals.index', icon: 'send' },
      { name: 'Overdue Referrals', href: '/overdue-referrals', route: 'overdue-referrals.index', icon: 'warning' },
      { name: 'Stakeholders', href: '/stakeholders', route: 'stakeholders.index', icon: 'account_balance' },
    ]},
    { label: 'Agency Management', items: [
      { name: 'Agencies', href: '/admin/agencies', route: 'admin.agencies.index', icon: 'account_balance' },
      { name: 'Services', href: '/admin/services', route: 'admin.services.index', icon: 'medical_services' },
      { name: 'Users', href: '/admin/users', route: 'admin.users.index', icon: 'manage_accounts' },
    ]},
    { label: 'Administration', items: [
      { name: 'Audit Logs', href: '/audit-logs', route: 'audit-logs.index', icon: 'history' },
      { name: 'Case Statuses', href: '/admin/case-statuses', route: 'admin.case-statuses.index', icon: 'label' },
      { name: 'Case Categories', href: '/admin/case-categories', route: 'admin.case-categories.index', icon: 'topic' },
      { name: 'Case Issues', href: '/admin/case-issues', route: 'admin.case-issues.index', icon: 'feedback' },
      { name: 'Maintenance Mode', href: '/admin/system/maintenance', route: 'admin.system.maintenance', icon: 'construction' },
    ]},
    { label: 'Settings', items: [
      { name: 'System Settings', href: '/admin/system-settings', route: 'admin.system-settings.index', icon: 'settings' },
      { name: 'Security & Auth', href: '/admin/system/security', route: 'admin.system.security', icon: 'security' },
    ]},
    { label: 'Resources', items: [
      { name: 'Help Center', href: '/help', route: 'helpdesk.index', icon: 'help', external: true },
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
  const pendingReviewCount = usePage().props.pending_review_count ?? usePage().props.intake_queue_count ?? 0;
  const [peerProfileUser, setPeerProfileUser] = useState(null);
  const [collapsed, setCollapsed] = useState(() => {
    try { return localStorage.getItem('owb-sidebar-collapsed') === 'true'; }
    catch { return false; }
  });
  const unreadStorageKey = `owb-unread-count-${user?.id ?? 'anonymous'}`;
  const [unreadCount, setUnreadCount] = useState(() => {
    try { return parseInt(localStorage.getItem(unreadStorageKey) || '0', 10) || 0; }
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

  // Re-seed from the namespaced key on account switch; drop the legacy
  // cross-user key so a stale count is never shown to the next user.
  useEffect(() => {
    try {
      setUnreadCount(parseInt(localStorage.getItem(unreadStorageKey) || '0', 10) || 0);
      localStorage.removeItem('owb-unread-count');
    }
    catch { /* noop */ }
  }, [unreadStorageKey]);

  // Fetch unread notification count
  useEffect(() => {
    let timer;
    const fetchCount = () => {
      fetch(safeRoute('notifications.unread-count', undefined, '/notifications/unread-count'), {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      })
        .then((res) => res.ok ? res.json() : null)
        .then((data) => {
          if (data?.count != null) {
            setUnreadCount(data.count);
            try { localStorage.setItem(unreadStorageKey, String(data.count)); }
            catch { /* noop */ }
          }
        })
        .catch(() => {});
    };
    fetchCount();
    timer = setInterval(fetchCount, 60000);

    return () => {
      clearInterval(timer);
    };
  }, [unreadStorageKey]);

  const handleLogout = useCallback(() => {
    clearAllDraftBackups();
    router.post(route('logout'));
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

  // Resolve a nav item through its Ziggy route name, falling back to the
  // plain href. Active-state matching still uses the plain href (URL paths).
  const resolveNavHref = (item) => (item.route ? safeRoute(item.route, undefined, item.href) : item.href);

  const isActive = (href) => {
    if (href === '/dashboard') return url === '/dashboard';
    if (href === '/cases') return url.startsWith('/cases') && !url.startsWith('/cases/trash') && !url.startsWith('/cases/review-pending');
    if (href === '/cases/review-pending') return url.startsWith('/cases/review-pending');
    return url.startsWith(href);
  };

  return (
    <aside className={`${collapsed ? 'w-16' : 'w-[216px]'} transition-[width] duration-200 ease-in-out bg-white border-r border-slate-200 hidden md:flex shrink-0 h-screen font-body flex-col`}>
      {/* Logo */}
      <div className={`flex items-center border-b border-slate-100 shrink-0 ${collapsed ? 'flex-col justify-center px-2 py-4 gap-2' : 'px-4 py-5'}`}>
        <Link href={safeRoute('home', undefined, '/')} className={`flex items-center gap-2.5 min-w-0 ${collapsed ? 'justify-center' : 'flex-1'}`}>
          <img src="/logo.png" alt="One Window Bayanihan logo" className="h-9 w-9 shrink-0 object-contain" />
          {!collapsed && (
            <div className="flex flex-col min-w-0 flex-1 leading-tight">
              <span className="truncate font-headline text-[13px] font-extrabold tracking-tight text-blue-950">One Window Bayanihan</span>
              <span className="text-[8px] font-bold uppercase tracking-[0.14em] text-slate-500">Assistance Program</span>
            </div>
          )}
        </Link>
        {!collapsed && (
          <button
            type="button"
            onClick={toggleCollapsed}
            className="shrink-0 ml-1 p-1 rounded-md text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
            title="Collapse sidebar"
            aria-label="Collapse sidebar"
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
          className="flex items-center justify-center py-2 border-b border-slate-100 text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
          title="Expand sidebar"
          aria-label="Expand sidebar"
        >
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="w-5 h-5">
            <rect x="3" y="3" width="18" height="18" rx="2" />
            <line x1="10.2" y1="3" x2="10.2" y2="21" />
          </svg>
        </button>
      )}

      {/* Nav */}
      <nav ref={sidebarNavRef} onScroll={handleSidebarScroll} data-tour="sidebar-nav" className="flex-1 min-h-0 overflow-y-auto py-3 owb-scroll">
        {navigation.map((group) => (
          <div key={group.label} className="mb-3">
            {!collapsed && (
              <p className="px-4 pb-1.5 text-[9px] font-bold font-label uppercase tracking-[0.14em] text-slate-400">
                {group.label}
              </p>
            )}
            <div className="space-y-0.5 px-2">
              {group.items.map((item) => {
                const active = isActive(item.href);
                const isNotification = item.name === 'Notifications';

                const linkClass = collapsed
                  ? `group relative flex items-center justify-center rounded-md py-2.5 text-[13px] font-label transition-colors ${
                      active
                        ? 'bg-surface-container-low text-primary font-bold'
                        : 'text-slate-600 font-medium hover:bg-slate-50 hover:text-slate-900'
                    }`
                  : `group relative flex items-center gap-2.5 rounded-r-md py-2.5 pl-3 pr-2 text-[13px] font-label transition-colors ${
                      active
                        ? 'border-l-[3px] border-primary bg-surface-container-low text-primary font-bold'
                        : 'border-l-[3px] border-transparent text-slate-600 font-medium hover:bg-slate-50 hover:text-slate-900'
                    }`;

                const icon = (
                  <span
                    aria-hidden="true"
                    className={`material-symbols-outlined text-[18px] leading-none shrink-0 transition-transform duration-150 group-hover:scale-105 ${
                      active ? 'text-primary' : 'text-slate-400'
                    }`}
                    style={{ fontVariationSettings: `'FILL' ${active ? '1' : '0'}, 'wght' ${active ? '600' : '400'}` }}
                  >
                    {item.icon}
                  </span>
                );

                const countBadge = (count, tone) => {
                  if (!(count > 0)) return null;
                  const label = count > 99 ? '99+' : count;
                  const toneClass = tone === 'amber' ? 'bg-amber-500' : 'bg-primary';

                  return collapsed ? (
                    <span className={`absolute -right-0.5 -top-0.5 grid h-4 min-w-4 place-items-center rounded-full px-0.5 text-[9px] font-bold leading-none text-white ${toneClass}`}>
                      {label}
                    </span>
                  ) : (
                    <span className={`ml-auto grid h-5 min-w-5 shrink-0 place-items-center rounded-full px-1.5 text-[10px] font-bold leading-none text-white ${toneClass}`}>
                      {label}
                    </span>
                  );
                };

                const badge = isNotification ? countBadge(unreadCount, 'primary') : null;
                const pendingBadge = item.badge === 'pendingReviewCount' ? countBadge(pendingReviewCount, 'amber') : null;

                const content = collapsed ? (
                  <span className="relative">
                    {icon}
                    {badge}
                    {pendingBadge}
                  </span>
                ) : (
                  <>
                    <span className="relative">{icon}</span>
                    <span className="flex-1 min-w-0 truncate">{item.name}</span>
                    {badge}
                    {pendingBadge}
                  </>
                );

                const sharedAttrs = {
                  className: linkClass,
                  'aria-current': active ? 'page' : undefined,
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
                      href={resolveNavHref(item)}
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
                    href={resolveNavHref(item)}
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
      <div className="flex flex-col shrink-0 border-t border-slate-200">
        <div className={`${collapsed ? 'px-2 py-4' : 'px-4 py-4'}`}>
          <div className={`flex items-center ${collapsed ? 'justify-center gap-0' : 'gap-2.5'}`}>
            <UserAvatar user={user} size={collapsed ? 'md' : 'md'} onClick={() => setPeerProfileUser(user)} />
            {!collapsed && (
              <div className="flex flex-col min-w-0 leading-tight">
                <span className="truncate text-[12px] font-bold text-blue-950">{user?.name}</span>
                <span className="mt-0.5 truncate text-[9px] font-bold uppercase tracking-[0.12em] text-slate-500">
                  {roleLabels[user?.role] || user?.role}
                </span>
              </div>
            )}
          </div>

          <div className={`mt-3 flex items-center ${collapsed ? 'flex-col gap-2' : 'gap-2'}`}>
            <Link
              href={route('profile.edit')}
              className={`flex items-center justify-center gap-1.5 rounded-lg border border-slate-200 text-[11px] font-bold text-slate-600 transition-colors hover:bg-slate-50 hover:text-primary ${
                collapsed ? 'h-8 w-10' : 'flex-1 px-3 py-2'
              }`}
              title={collapsed ? 'Edit Profile' : undefined}
              aria-label="Edit profile"
            >
              <span className="material-symbols-outlined text-[15px] leading-none" aria-hidden="true">person</span>
              {!collapsed && <span>My Profile</span>}
            </Link>
            <button
              type="button"
              onClick={handleLogout}
              className="flex h-8 w-10 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition-colors hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600"
              title="Log out"
              aria-label="Log out"
            >
              <span className="material-symbols-outlined text-[15px] leading-none" aria-hidden="true">logout</span>
            </button>
          </div>
        </div>
      </div>

      <PeerProfileModal user={peerProfileUser} show={!!peerProfileUser} onClose={() => setPeerProfileUser(null)} />

      {/* Collapsed nav tooltip */}
      {collapsed && hoveredItem && (
        <div
          className="fixed z-50 whitespace-nowrap rounded-md border border-slate-200 bg-white px-2.5 py-1 text-[12px] font-medium text-slate-700 shadow-sm pointer-events-none"
          style={{ top: `${hoveredItem.top}px`, left: '4.5rem', transform: 'translateY(-50%)' }}
        >
          {hoveredItem.name}
        </div>
      )}
    </aside>
  );
}
