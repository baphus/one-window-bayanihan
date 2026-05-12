import { Link, router, usePage } from '@inertiajs/react';
import AppButton from './AppButton';

const navLinks = [
  { name: 'Home', href: '/', exact: true },
  { name: 'Track Your Case', href: '/track', exact: false },
  { name: 'Partners', href: '/partners', exact: false },
  { name: 'Contact', href: '/contact', exact: false },
];

function isActive(currentPath, link) {
  if (link.exact) return currentPath === link.href;
  return currentPath.startsWith(link.href);
}

export default function AppHeader({ onTrackCaseClick, minimal }) {
  const { url } = usePage();

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
                    className={`px-4 py-2 rounded-md text-[14px] font-label font-medium transition-colors duration-200 ${
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
              <Link href={route('login')}>
                <AppButton variant="outline">Login</AppButton>
              </Link>
            </div>
          </>
        )}
      </div>
    </nav>
  );
}
