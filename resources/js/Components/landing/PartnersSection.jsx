import { useState, useEffect, useCallback } from 'react';
import { Link } from '@inertiajs/react';
import AgencyMapView from '@/Components/AgencyMapView';
import useInView from '@/Hooks/useInView';

function AgencyLogo({ agency }) {
  const [hasError, setHasError] = useState(false);

  if (hasError || !agency.logo_url) {
    return (
      <div className="flex h-14 w-14 items-center justify-center rounded-circle bg-primary/10">
        <span className="text-lg font-bold text-primary">{agency.short?.charAt(0)}</span>
      </div>
    );
  }

  return (
    <img
      src={agency.logo_url}
      alt={`${agency.short} Logo`}
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

function AgencyCard({ agency }) {
  return (
    <div className="h-full border border-outline-variant/30 bg-surface-container-lowest p-4 text-left shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-md">
      <div className="mb-3 flex items-center gap-3">
        <div className="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-circle bg-white shadow-sm">
          <AgencyLogo agency={agency} />
        </div>
        <div className="min-w-0">
          <p className="text-xs font-extrabold uppercase tracking-widest text-primary">{agency.short}</p>
          <p className="text-sm font-semibold text-on-surface line-clamp-2">{agency.name}</p>
        </div>
      </div>
      <div className="h-[110px] overflow-hidden border border-outline-variant/30 bg-white">
        <AgencyMapView
          mapLink={agency.map_link}
          latitude={agency.latitude}
          longitude={agency.longitude}
          locationQuery={agency.location_query}
          agencyName={agency.name}
          embedHeight="110px"
        />
      </div>
      <div className="mt-3 flex items-center justify-between">
        <p className="text-[11px] font-semibold uppercase tracking-[0.12em] text-secondary">View Agency Location</p>
        <Link href={route('partners.show', getAgencyRouteParam(agency))} className="text-[11px] font-bold uppercase tracking-[0.12em] text-primary border border-primary/30 px-3 py-1 hover:bg-primary/5 transition-colors">
          View Agency
        </Link>
      </div>
    </div>
  );
}

export default function PartnersSection({ agencies }) {
  const [headingRef, headingVisible] = useInView();
  const [carouselRef, carouselVisible] = useInView();
  const [activePage, setActivePage] = useState(0);
  const total = agencies.length;

  const mobileVisible = 1;
  const desktopVisible = 3;
  const mobilePages = Math.ceil(total / mobileVisible);
  const desktopPages = Math.ceil(total / desktopVisible);

  const goNext = useCallback(() => {
    setActivePage((p) => (p + 1) % desktopPages);
  }, [desktopPages]);

  const goPrev = useCallback(() => {
    setActivePage((p) => (p - 1 + desktopPages) % desktopPages);
  }, [desktopPages]);

  useEffect(() => {
    if (total <= 1) return;
    const id = setInterval(goNext, 6000);
    return () => clearInterval(id);
  }, [goNext, total]);

  const mobileOffset = activePage * mobileVisible;
  const desktopOffset = activePage * desktopVisible;

  const mobileStart = mobileOffset + 1;
  const mobileEnd = Math.min(mobileOffset + mobileVisible, total);
  const desktopStart = desktopOffset + 1;
  const desktopEnd = Math.min(desktopOffset + desktopVisible, total);

  return (
    <section id="partners" className="overflow-hidden bg-white px-8 py-20">
      <div className="mx-auto max-w-7xl text-center">
        <div ref={headingRef} className={`owb-reveal ${headingVisible ? 'is-visible' : ''}`}>
          <span className="mb-8 block text-sm font-bold uppercase tracking-widest text-slate-500">Network of Care</span>
          <h2 className="mb-12 font-headline text-2xl font-extrabold text-primary">Our Partner Agencies (Region VII)</h2>
        </div>

        {/* ── Desktop carousel (sm+) ── */}
        <div ref={carouselRef} className={`hidden sm:block owb-reveal ${carouselVisible ? 'is-visible' : ''}`}>
          <div className="relative overflow-hidden">
            <div className="flex gap-5 transition-transform duration-500 ease-out" style={{ transform: `translateX(-${desktopOffset * (100 / desktopVisible)}%)` }}>
              {agencies.map((agency) => (
                <div key={agency.id} className="w-full shrink-0 sm:w-[calc((100%-2*1.25rem)/2)] lg:w-[calc((100%-2*1.25rem)/3)]">
                  <AgencyCard agency={agency} />
                </div>
              ))}
            </div>
            {desktopPages > 1 && (
              <>
                <button type="button" onClick={goPrev} className="absolute left-3 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-circle bg-black/55 text-white transition-colors hover:bg-black/70" aria-label="Previous agencies">
                  <span className="material-symbols-outlined" aria-hidden="true">chevron_left</span>
                </button>
                <button type="button" onClick={goNext} className="absolute right-3 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-circle bg-black/55 text-white transition-colors hover:bg-black/70" aria-label="Next agencies">
                  <span className="material-symbols-outlined" aria-hidden="true">chevron_right</span>
                </button>
              </>
            )}
          </div>
          {desktopPages > 1 && (
            <div className="mt-6 flex items-center justify-center gap-4">
              <div className="flex items-center gap-2">
                {Array.from({ length: desktopPages }, (_, i) => (
                  <button key={i} type="button" onClick={() => setActivePage(i)}
                    className={`h-2.5 rounded-full transition-all ${i === activePage ? 'w-8 bg-primary' : 'w-2.5 bg-outline-variant/70 hover:bg-primary/60'}`}
                    aria-label={`Go to page ${i + 1}`} aria-current={i === activePage ? 'true' : undefined}
                  />
                ))}
              </div>
              <span className="text-xs uppercase tracking-[0.1em] text-on-surface-variant">{desktopStart}–{desktopEnd} of {total}</span>
            </div>
          )}
        </div>

        {/* ── Mobile carousel (<sm) ── */}
        <div className={`sm:hidden owb-reveal ${carouselVisible ? 'is-visible' : ''}`}>
          <div className="relative overflow-hidden border border-outline-variant/30 bg-surface-container-lowest shadow-md">
            <div className="p-4">
              <div className="h-[380px]">
                {agencies[mobileOffset] && (
                  <AgencyCard agency={agencies[mobileOffset]} />
                )}
              </div>
            </div>
            {total > 1 && (
              <>
                <button type="button" onClick={() => setActivePage((p) => (p - 1 + mobilePages) % mobilePages)} className="absolute left-3 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-circle bg-black/55 text-white transition-colors hover:bg-black/70" aria-label="Previous agency">
                  <span className="material-symbols-outlined" aria-hidden="true">chevron_left</span>
                </button>
                <button type="button" onClick={() => setActivePage((p) => (p + 1) % mobilePages)} className="absolute right-3 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-circle bg-black/55 text-white transition-colors hover:bg-black/70" aria-label="Next agency">
                  <span className="material-symbols-outlined" aria-hidden="true">chevron_right</span>
                </button>
              </>
            )}
          </div>
          {mobilePages > 1 && (
            <div className="mt-4 flex items-center justify-center gap-4">
              <div className="flex items-center gap-2">
                {Array.from({ length: mobilePages }, (_, i) => (
                  <button key={i} type="button" onClick={() => setActivePage(i)}
                    className={`h-2.5 rounded-full transition-all ${i === activePage ? 'w-8 bg-primary' : 'w-2.5 bg-outline-variant/70 hover:bg-primary/60'}`}
                    aria-label={`Go to agency ${i + 1}`} aria-current={i === activePage ? 'true' : undefined}
                  />
                ))}
              </div>
              <span className="text-xs uppercase tracking-[0.1em] text-on-surface-variant">{mobileStart} / {total}</span>
            </div>
          )}
        </div>
      </div>
    </section>
  );
}
