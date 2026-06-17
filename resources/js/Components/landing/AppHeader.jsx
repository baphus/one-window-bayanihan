import { Link, router, usePage } from '@inertiajs/react';
import AppButton from './AppButton';

const navLinks = [
  { name: 'Home', href: '/', exact: true },
  { name: 'Help Center', href: '/helpdesk', exact: false },
  { name: 'Track Your Case', href: '/track', exact: false },
  { name: 'Partners', href: '/partners', exact: false },
  { name: 'Contact', href: '/contact', exact: false },
];

function isActive(currentPath, link) {
  if (link.exact) return currentPath === link.href;
  return currentPath.startsWith(link.href);
}

function getInitials(name) {
  if (!name) return '?';
  return name.split(' ').map((n) => n[0]).filter(Boolean).slice(0, 2).join('').toUpperCase();
}

export default function AppHeader({ onTrackCaseClick, minimal }) {
  const { url, props } = usePage();
  const user = props.auth?.user ?? null;

  return (
    <nav className={`fixed top-0 z-50 w-full border-b border-outline-variant ${minimal ? 'bg-surface-bright' : 'bg-white border-gray-200'}`}>
      <div className="mx-auto flex w-full max-w-7xl items-center justify-between px-4 py-4 md:px-8">
        <Link href="/" className="flex items-center gap-3">
          <div className="flex h-[44px] w-[44px] items-center justify-center overflow-hidden rounded-full border border-gray-200 bg-white">
            <img src="/logo.png" alt="Bayanihan Logo" className="w-full h-full object-contain" />
          </div>
          <div className="flex flex-col">
            <span className="font-headline text-[18px] font-bold text-primary">Bayanihan One Window</span>
            <span className="font-label text-[12px] font-medium uppercase tracking-wide text-on-surface-variant">DMW Region VII</span>
          </div>
        </Link>

        {!minimal && (
          <>
            <div className="hidden items-center gap-1 md:flex">
              {navLinks.map((link) => {
                const active = isActive(url, link);
                return (
                  <Link
                    key={link.name}
                    href={link.href}
                    className={`px-4 py-2 rounded text-[14px] font-label font-medium transition-colors duration-200 ${
                      active
                        ? 'bg-[#005288]/10 text-[#005288] font-bold'
                        : 'text-slate-600 hover:bg-slate-100 hover:text-[#005288]'
                    }`}
                  >
                    {link.name}
                  </Link>
                );
              })}
            </div>

            <div className="flex items-center gap-4">
              {onTrackCaseClick ? (
                <AppButton variant="primary" onClick={onTrackCaseClick}>Track Case</AppButton>
              ) : (
                <AppButton href="#tracker" variant="primary">Track Case</AppButton>
              )}

              {user ? (
                <div className="flex items-center gap-3">
                  <Link href={route('dashboard')}>
                    <AppButton variant="primary">Dashboard</AppButton>
                  </Link>
                  <div className="flex items-center gap-2 border-l border-gray-200 pl-3">
                    <span className="flex h-8 w-8 items-center justify-center rounded-full bg-[#005288] text-xs font-bold text-white">
                      {getInitials(user.name)}
                    </span>
                    <span className="hidden text-sm font-medium text-slate-700 md:inline">{user.name}</span>
                    <button
                      onClick={() => router.post(route('logout'))}
                      className="ml-1 text-xs font-medium text-slate-400 hover:text-red-500 transition-colors"
                      title="Logout"
                    >
                      <span className="material-symbols-outlined text-[18px]">logout</span>
                    </button>
                  </div>
                </div>
              ) : (
                <Link href={route('login')}>
                  <AppButton variant="outline">Login</AppButton>
                </Link>
              )}
            </div>
          </>
        )}
      </div>
    </nav>
  );
}
