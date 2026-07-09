import { useState } from 'react';
import { Link } from '@inertiajs/react';
import AgencyMapView from '@/Components/AgencyMapView';

function AgencyLogo({ agency }) {
  const [hasError, setHasError] = useState(false);

  if (hasError || !agency.logo_url) {
    return (
      <div className="flex h-14 w-14 items-center justify-center rounded-full bg-primary/10">
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

export default function PartnersSection({ agencies }) {
  return (
    <section id="partners" className="overflow-hidden bg-white px-8 py-20">
      <div className="mx-auto max-w-7xl text-center">
        <span className="mb-8 block text-sm font-bold uppercase tracking-widest text-slate-500">Network of Care</span>
        <h2 className="mb-12 font-headline text-2xl font-extrabold text-primary">Our Partner Agencies (Region VII)</h2>
        <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {agencies.map((agency) => (
            <div key={agency.id} className="border border-outline-variant/30 bg-surface-container-lowest p-4 text-left shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-md">
              <div className="mb-3 flex items-center gap-3">
                <div className="flex h-14 w-14 items-center justify-center overflow-hidden rounded-full bg-white shadow-sm">
                  <AgencyLogo agency={agency} />
                </div>
                <div>
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
          ))}
        </div>
      </div>
    </section>
  );
}
