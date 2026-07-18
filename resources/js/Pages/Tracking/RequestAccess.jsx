import { useEffect, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppHeader from '@/Components/landing/AppHeader';
import AppFooter from '@/Components/landing/AppFooter';
import ChatBot from '@/Components/ChatBot';

function UnavailableState() {
  return (
    <section className="border border-outline-variant bg-surface-container-lowest px-5 py-8 text-center">
      <span aria-hidden="true" className="material-symbols-outlined text-4xl text-on-surface-variant/60">link_off</span>
      <h1 className="mt-3 font-headline text-xl font-extrabold text-on-surface">Secure request unavailable</h1>
      <p className="mx-auto mt-2 max-w-md text-sm leading-relaxed text-on-surface-variant">
        This secure request link is unavailable or has expired. Please return to the tracking portal to continue.
      </p>
      <Link
        href={route('track.index')}
        className="mt-5 inline-flex items-center gap-2 bg-blue-900 px-4 py-2.5 text-sm font-bold text-white transition-colors hover:bg-blue-800"
      >
        <span aria-hidden="true" className="material-symbols-outlined text-[17px]">arrow_back</span>
        Back to Tracking
      </Link>
    </section>
  );
}

export default function RequestAccess({ exchangeUrl }) {
  const [unavailable, setUnavailable] = useState(false);

  useEffect(() => {
    const hash = window.location.hash.replace(/^#/, '');
    const token = new URLSearchParams(hash).get('token');

    window.history.replaceState(null, document.title, `${window.location.pathname}${window.location.search}`);

    if (!token) {
      setUnavailable(true);
      return undefined;
    }

    router.post(exchangeUrl, { token }, {
      preserveState: true,
      preserveScroll: true,
      onError: () => setUnavailable(true),
    });

    return undefined;
  }, [exchangeUrl]);

  return (
    <div className="flex min-h-screen flex-col bg-surface font-body text-on-surface">
      <Head title="Secure Request" />
      <AppHeader />

      <main className="flex flex-1 items-center justify-center px-4 pb-16 pt-32 sm:px-6">
        <div className="w-full max-w-xl">
          {unavailable ? (
            <UnavailableState />
          ) : (
            <section role="status" aria-live="polite" className="border border-outline-variant bg-surface-container-lowest px-5 py-10 text-center">
              <span aria-hidden="true" className="material-symbols-outlined motion-safe:animate-pulse text-4xl text-primary">lock</span>
              <h1 className="mt-3 font-headline text-xl font-extrabold text-on-surface">Opening secure request…</h1>
              <p className="mt-2 text-sm leading-relaxed text-on-surface-variant">Please wait while we securely open your request.</p>
            </section>
          )}
        </div>
      </main>

      <AppFooter />
      <ChatBot />
    </div>
  );
}
