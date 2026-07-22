import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppHeader from '@/Components/landing/AppHeader';
import AppFooter from '@/Components/landing/AppFooter';
import ChatBot from '@/Components/ChatBot';
import AgencyMapView from '@/Components/AgencyMapView';
import useInView from '@/Hooks/useInView';

function AgencyLogo({ agency }) {
  const [hasError, setHasError] = useState(false);

  if (hasError || !agency.logo_url) {
    return (
      <span className="text-2xl font-bold text-[#0b5c92]">{agency.short?.charAt(0)}</span>
    );
  }

  return (
    <img
      src={agency.logo_url}
      alt={agency.short}
      className="h-full w-full object-contain p-2"
      referrerPolicy="no-referrer"
      loading="lazy"
      onError={() => setHasError(true)}
    />
  );
}

function getAgencyRouteParam(agency) {
  return agency?.slug || agency?.id;
}

export default function PublicAgencies({ agencies }) {
  const [gridRef, gridVisible] = useInView();

  return (
    <div className="flex min-h-screen flex-col bg-surface font-body text-on-surface">
      <Head title="Partner Agencies" />
      <AppHeader onTrackCaseClick={() => router.get(route('track.index'))} />

      <main className="flex-1 pt-20">
        <section className="relative flex min-h-[300px] w-full items-center justify-center overflow-hidden bg-[#0b5c92]">
          <div className="absolute inset-0 bg-gradient-to-br from-[#0b5c92] via-[#0b5c92]/90 to-blue-800/30"></div>
          <div className="relative z-10 mx-auto w-full max-w-7xl px-4 text-center md:px-8">
            <h1 className="mb-4 font-headline text-4xl font-extrabold leading-tight tracking-tight text-white sm:text-5xl">
              Partner Agencies
            </h1>
            <p className="mx-auto max-w-2xl text-lg leading-relaxed text-white/80">
              Our network of partner government agencies working together to provide
              seamless support for migrant workers and their families.
            </p>
          </div>
        </section>

        <section className="py-16 px-4">
          <div className="mx-auto max-w-7xl">
            <div ref={gridRef} className={`grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 owb-stagger`}>
              {agencies.map((agency, index) => (
                <div key={agency.id} className={`rounded-lg border border-slate-200 bg-white p-6 shadow-sm hover:shadow-md transition-shadow ${index < 3 ? 'owb-reveal is-visible' : `owb-reveal ${gridVisible ? 'is-visible' : ''}`}`}>
                  <div className="flex items-center gap-4 mb-4">
                    <div className="flex h-16 w-16 items-center justify-center overflow-hidden rounded-full bg-white border border-slate-100">
                      <AgencyLogo agency={agency} />
                    </div>
                    <div>
                      <h3 className="text-base font-bold text-slate-900">{agency.short}</h3>
                      <p className="text-sm text-slate-500">{agency.name}</p>
                    </div>
                  </div>

                  {agency.description && (
                    <p className="text-sm text-slate-600 mb-4 line-clamp-3">{agency.description}</p>
                  )}

                  <AgencyMapView
                    mapLink={agency.map_link}
                    latitude={agency.latitude}
                    longitude={agency.longitude}
                    locationQuery={agency.location_query}
                    agencyName={agency.name}
                    embedHeight="100px"
                  />

                  <div className="mt-4">
                    <Link
                      href={route('partners.show', getAgencyRouteParam(agency))}
                      className="inline-flex items-center justify-center gap-1.5 w-full border border-primary/30 px-4 py-2 text-xs font-bold uppercase tracking-wider text-primary transition-colors hover:bg-primary/5"
                    >
                      <span className="material-symbols-outlined text-[16px]">open_in_new</span>
                      View Agency
                    </Link>
                  </div>

                  {agency.services?.length > 0 && (
                    <div className="mt-4 pt-4 border-t border-slate-100">
                      <p className="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Services</p>
                      <div className="flex flex-wrap gap-1">
                        {agency.services.map((svc) => (
                          <span key={svc.id} className="inline-flex rounded-full bg-blue-50 px-2 py-0.5 text-xs text-blue-700 border border-blue-100">
                            {svc.name}
                          </span>
                        ))}
                      </div>
                    </div>
                  )}
                </div>
              ))}
            </div>
          </div>
        </section>
      </main>

      <AppFooter />
      <ChatBot />
    </div>
  );
}
