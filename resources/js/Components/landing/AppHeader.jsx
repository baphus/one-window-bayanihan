import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppButton from './AppButton';

const roleLabels = {
  CASE_MANAGER: 'Case Manager',
  AGENCY: 'Agency Focal',
  ADMIN: 'System Admin',
};

const navLinks = [
  { name: 'Home', href: '/', exact: true },
  { name: 'Help Center', href: '/help', exact: false },
  { name: 'Track Your Case', href: '/track', exact: false },
  { name: 'Partners', href: '/partners', exact: false },
  { name: 'Contact', href: '/contact', exact: false },
];

function isActive(currentPath, link) {
  if (link.exact) return currentPath === link.href;
  return currentPath.startsWith(link.href);
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
        <Link href="/" className="flex min-w-0 items-center gap-3">
          <div className="flex h-[44px] w-[44px] items-center justify-center overflow-hidden bg-white">
            <img
              src="/logo.png"
              alt="One Window Bayanihan Logo"
              className="h-full w-full object-contain"
            />
          </div>

          <div className="flex flex-col">
            <span className="text-[18px] font-bold tracking-tight text-blue-950 leading-tight" style={{ fontFamily: "'Outfit', sans-serif" }}>One Window Bayanihan</span>
            <span className="text-[11px] font-semibold uppercase tracking-[0.1em] text-slate-500">Assistance Program</span>
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
                        ? 'bg-primary/10 font-bold text-primary'
                        : 'text-slate-600 hover:bg-slate-100 hover:text-primary'
                    }`}
                  >
                    {link.name}
                  </Link>
                );
              })}
            </div>

            <div className="hidden items-center gap-3 md:flex">
              {!user && (
                onTrackCaseClick ? (
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
                )
              )}

              {user ? (
                <div className="flex items-center gap-3">
                  <AppButton
                    href={route('dashboard')}
                    variant="primary"
                  >
                    Dashboard
                  </AppButton>

                  <div className="flex items-center gap-4 border-l border-gray-200 pl-5">
                    <span className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-circle bg-primary">
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="w-3/5 h-3/5 text-white/50">
                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                      </svg>
                    </span>

                    <div className="hidden flex-col md:flex">
                      <span className="text-sm font-semibold text-slate-800">
                        {user.name}
                      </span>

                      <span className="text-xs text-slate-500">
                        {roleLabels[user?.role] || user?.role || 'User'}
                      </span>
                    </div>

                    <button
                      type="button"
                      onClick={() => router.post(route('logout'))}
                      aria-label="Log out"
                      title="Log out"
                      className="flex-shrink-0 text-slate-700 transition hover:text-red-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                    >
                      <span className="material-symbols-outlined text-[18px]" aria-hidden="true">
                        logout
                      </span>
                    </button>
                  </div>
                </div>
              ) : (
                <Link
                  href={route('login')}
                  className="text-sm font-semibold text-slate-700 transition-colors hover:text-primary"
                >
                  Login
                </Link>
              )}
            </div>

            <button
              type="button"
              className="inline-flex items-center justify-center rounded-md border border-gray-200 p-2 text-slate-600 transition hover:bg-slate-50 hover:text-primary md:hidden"
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
                className="inline-flex h-10 w-10 items-center justify-center rounded-circle border border-gray-200 text-slate-600 transition hover:bg-slate-50 hover:text-primary"
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
                          ? 'bg-primary/10 font-bold text-primary'
                          : 'text-slate-700 hover:bg-slate-50 hover:text-primary'
                      }`}
                    >
                      {link.name}
                    </Link>
                  );
                })}
              </div>

              <div className="mt-4 grid gap-2 border-t border-gray-100 pt-3">
                {!user && (
                  onTrackCaseClick ? (
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
                  )
                )}

                {user ? (
                  <AppButton
                    href={route('dashboard')}
                    variant="primary"
                    className="w-full"
                    onClick={() => setIsMobileMenuOpen(false)}
                  >
                    Dashboard
                  </AppButton>
                ) : (
                  <Link
                    href={route('login')}
                    onClick={() => setIsMobileMenuOpen(false)}
                  >
                    <AppButton
                      variant="outline"
                      className="w-full"
                    >
                      Login
                    </AppButton>
                  </Link>
                )}

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
