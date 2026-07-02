import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
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

  return name
    .split(' ')
    .map((n) => n[0])
    .filter(Boolean)
    .slice(0, 2)
    .join('')
    .toUpperCase();
}

export default function AppHeader({ onTrackCaseClick, minimal }) {
  const { url, props } = usePage();
  const user = props.auth?.user ?? null;

  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

  useEffect(() => {
    setIsMobileMenuOpen(false);
  }, [url]);

  const mobileActionHref = user ? route('dashboard') : route('login');
  const mobileActionLabel = user ? 'Dashboard' : 'Login';

  return (
    <nav
      className={`fixed top-0 z-50 w-full border-b border-outline-variant ${
        minimal ? 'bg-surface-bright' : 'bg-white border-gray-200'
      }`}
    >
      <div className="mx-auto flex w-full max-w-7xl items-center justify-between px-4 py-4 md:px-8">
        <Link href="/" className="flex items-center gap-3">
          <div className="flex h-[44px] w-[44px] items-center justify-center overflow-hidden rounded-full bg-white">
            <img
              src="/logo.png"
              alt="Bayanihan Logo"
              className="h-full w-full object-contain"
            />
          </div>

          <div className="flex flex-col">
            <span className="font-headline text-[18px] font-bold text-primary">
              Bayanihan One Window
            </span>

            <span className="font-label text-[12px] font-medium uppercase tracking-wide text-on-surface-variant">
              DMW Region VII
            </span>
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
                    className={`rounded px-4 py-2 text-[14px] font-label font-medium transition-colors duration-200 ${
                      active
                        ? 'bg-[#005288]/10 font-bold text-[#005288]'
                        : 'text-slate-600 hover:bg-slate-100 hover:text-[#005288]'
                    }`}
                  >
                    {link.name}
                  </Link>
                );
              })}
            </div>

            <div className="hidden items-center gap-6 md:flex">
              {onTrackCaseClick ? (
                <AppButton
                  variant="primary"
                  onClick={onTrackCaseClick}
                >
                  Track Case
                </AppButton>
              ) : (
                <AppButton
                  href="#tracker"
                  variant="primary"
                >
                  Track Case
                </AppButton>
              )}

              {user ? (
                <div className="flex items-center gap-3">
                  <Link
                      href={route('dashboard')}
                      className="text-sm font-semibold text-slate-700 hover:text-[#005288]"
                    >
                      Dashboard
                    </Link>

                  <div className="flex items-center gap-4 border-l border-gray-200 pl-5">
                    <span className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-[#005288] text-sm font-semibold text-white">
                      {getInitials(user.name)}
                    </span>

                    <div className="hidden flex-col md:flex">
                      <span className="text-sm font-semibold text-slate-800">
                        {user.name}
                      </span>

                      <span className="text-xs text-slate-500">
                        Administrator
                      </span>
                    </div>

                    <button
                      onClick={() => router.post(route('logout'))}
                      className="text-slate-400 transition hover:text-red-500"
                      title="Logout"
                    >
                      <span className="material-symbols-outlined text-[18px]">
                        logout
                      </span>
                    </button>
                  </div>
                </div>
              ) : (
                <Link
                  href={route('login')}
                  className="text-sm font-semibold text-slate-700 transition-colors hover:text-[#005288]"
                >
                  Login
                </Link>
              )}
            </div>

            <button
              type="button"
              className="inline-flex items-center justify-center rounded-md border border-gray-200 p-2 text-slate-600 transition hover:bg-slate-50 hover:text-[#005288] md:hidden"
              aria-label={isMobileMenuOpen ? 'Close menu' : 'Open menu'}
              aria-expanded={isMobileMenuOpen}
              aria-controls="landing-mobile-navigation"
              onClick={() =>
                setIsMobileMenuOpen((previousState) => !previousState)
              }
            >
              {isMobileMenuOpen ? (
                <svg
                  className="h-6 w-6"
                  viewBox="0 0 24 24"
                  fill="none"
                  aria-hidden="true"
                >
                  <path
                    d="M6 6l12 12"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                  />
                  <path
                    d="M18 6L6 18"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                  />
                </svg>
              ) : (
                <svg
                  className="h-6 w-6"
                  viewBox="0 0 24 24"
                  fill="none"
                  aria-hidden="true"
                >
                  <path
                    d="M4 6h16"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                  />
                  <path
                    d="M4 12h16"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                  />
                  <path
                    d="M4 18h16"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                  />
                </svg>
              )}
            </button>
          </>
        )}
      </div>

      {!minimal && isMobileMenuOpen && (
        <>
          <button
            type="button"
            aria-label="Close mobile menu"
            className="fixed inset-0 z-40 bg-slate-950/40 md:hidden"
            onClick={() => setIsMobileMenuOpen(false)}
          />

          <aside
            id="landing-mobile-navigation"
            className="fixed right-0 top-0 z-50 flex h-full w-[min(19rem,85vw)] flex-col bg-white shadow-2xl ring-1 ring-black/5 md:hidden"
            aria-label="Mobile navigation"
          >
            <div className="flex items-center justify-between border-b border-gray-100 px-4 py-4">
              <div>
                <p className="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500">
                  Navigation
                </p>

                <p className="mt-1 text-sm font-semibold text-slate-900">
                  Quick links
                </p>
              </div>

              <button
                type="button"
                aria-label="Close menu"
                className="inline-flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 text-slate-600 transition hover:bg-slate-50 hover:text-[#005288]"
                onClick={() => setIsMobileMenuOpen(false)}
              >
                <svg
                  className="h-5 w-5"
                  viewBox="0 0 24 24"
                  fill="none"
                  aria-hidden="true"
                >
                  <path
                    d="M6 6l12 12"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                  />
                  <path
                    d="M18 6L6 18"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                  />
                </svg>
              </button>
            </div>

            <div className="flex-1 overflow-y-auto px-4 py-4">
              <div className="grid gap-2">
                {navLinks.map((link) => {
                  const active = isActive(url, link);

                  return (
                    <Link
                      key={link.name}
                      href={link.href}
                      onClick={() => setIsMobileMenuOpen(false)}
                      className={`rounded-md px-4 py-3 text-sm font-medium transition-colors ${
                        active
                          ? 'bg-[#005288]/10 font-bold text-[#005288]'
                          : 'text-slate-700 hover:bg-slate-50 hover:text-[#005288]'
                      }`}
                    >
                      {link.name}
                    </Link>
                  );
                })}
              </div>

              <div className="mt-4 grid gap-2 border-t border-gray-100 pt-3">
                {onTrackCaseClick ? (
                  <AppButton
                    variant="primary"
                    onClick={() => {
                      setIsMobileMenuOpen(false);
                      onTrackCaseClick();
                    }}
                  >
                    Track Case
                  </AppButton>
                ) : (
                  <AppButton
                    href="#tracker"
                    variant="primary"
                    onClick={() => setIsMobileMenuOpen(false)}
                  >
                    Track Case
                  </AppButton>
                )}

                <Link
                  href={mobileActionHref}
                  onClick={() => setIsMobileMenuOpen(false)}
                >
                  <AppButton
                    variant="outline"
                    className="w-full"
                  >
                    {mobileActionLabel}
                  </AppButton>
                </Link>

                {user && (
                  <button
                    type="button"
                    onClick={() => {
                      setIsMobileMenuOpen(false);
                      router.post(route('logout'));
                    }}
                    className="rounded-md border border-gray-200 px-4 py-3 text-left text-sm font-medium text-slate-600 transition hover:bg-slate-50 hover:text-red-600"
                  >
                    Logout
                  </button>
                )}
              </div>
            </div>
          </aside>
        </>
      )}
    </nav>
  );
}