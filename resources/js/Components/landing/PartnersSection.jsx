import { useState } from 'react';
import AppButton from './AppButton';

function getMapsUrl(agency) {
  if (agency.latitude && agency.longitude) {
    return `https://www.google.com/maps?q=${agency.latitude},${agency.longitude}&output=embed`;
  }
  return `https://www.google.com/maps?q=${encodeURIComponent(agency.location_query || '')}&output=embed`;
}

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
                <iframe title={`${agency.name} map`} src={getMapsUrl(agency)} className="h-full w-full" loading="lazy" referrerPolicy="no-referrer-when-downgrade" />
              </div>
              <p className="mt-3 text-[11px] font-semibold uppercase tracking-[0.12em] text-secondary">View Agency Location</p>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
