import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppHeader from '@/Components/landing/AppHeader';
import AppFooter from '@/Components/landing/AppFooter';
import ChatBot from '@/Components/ChatBot';
import AgencyMapView from '@/Components/AgencyMapView';

function AgencyLogo({ agency, className = '' }) {
  const [hasError, setHasError] = useState(false);
  const fallbackLetter = agency?.short?.charAt(0) || agency?.name?.charAt(0) || 'A';

  if (hasError || !agency?.logo_url) {
    return (
      <span className={`font-headline font-extrabold text-[#0b5c92] ${className}`}>
        {fallbackLetter}
      </span>
    );
  }

  return (
    <img
      src={agency.logo_url}
      alt={agency.short || agency.name || 'Agency'}
      className={`h-full w-full object-contain p-3 ${className}`}
      referrerPolicy="no-referrer"
      loading="lazy"
      onError={() => setHasError(true)}
    />
  );
}

function InfoCard({ icon, label, children }) {
  return (
    <div className="border border-outline-variant/70 bg-surface-container-lowest p-4 shadow-sm">
      <div className="flex items-center gap-2 text-on-surface-variant">
        <span className="material-symbols-outlined text-[18px] text-primary">{icon}</span>
        <span className="text-[10px] font-bold uppercase tracking-[0.2em]">
          {label}
        </span>
      </div>
      <div className="mt-3 text-sm leading-6 text-on-surface">{children}</div>
    </div>
  );
}

function formatProcessingDays(days) {
  const numericDays = Number(days);

  if (!Number.isFinite(numericDays)) {
    return null;
  }

  return `${numericDays} day${numericDays === 1 ? '' : 's'}`;
}

function sanitizeText(value) {
  if (typeof value !== 'string') {
    return '';
  }

  return value
    .replace(/\\r\\n|\\n|\\r/g, '\n')
    .replace(/\r\n|\r|\n/g, '\n')
    .trim();
}

export default function PublicAgencyShow({ agency }) {
  const services = Array.isArray(agency?.services) ? agency.services : [];
  const contactInfo = sanitizeText(agency?.contact_info);
  const agencyDescription = sanitizeText(agency?.description);
  const hasContactInfo = contactInfo.length > 0;
  const hasLocationData =
    sanitizeText(agency?.map_link).length > 0 ||
    sanitizeText(agency?.location_query).length > 0 ||
    (agency?.latitude !== null && agency?.latitude !== undefined && agency?.longitude !== null && agency?.longitude !== undefined);

  const handleBackToPartners = (event) => {
    event.preventDefault();
    router.get(route('partners'));
  };

  return (
    <div className="flex min-h-screen flex-col bg-surface font-body text-on-surface">
      <Head title={agency?.name ? `${agency.name} | Partner Agency` : 'Partner Agency'} />
      <AppHeader onTrackCaseClick={() => router.get(route('track.index'))} />

      <main className="flex-1 pt-20">
        <section className="relative overflow-hidden bg-[#0b5c92]">
          <div className="absolute inset-0 bg-gradient-to-br from-[#0b5c92] via-[#0b5c92]/95 to-blue-800/35" />

          <div className="relative mx-auto max-w-7xl px-4 py-10 md:px-8 md:py-14">
            <Link
              href={route('partners')}
              onClick={handleBackToPartners}
              className="mb-8 inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.24em] text-white/75 transition hover:text-white"
            >
              <span className="material-symbols-outlined text-[16px]">arrow_back</span>
              Back to Partner Agencies
            </Link>

            <div className="grid items-center gap-8 md:grid-cols-[auto,1fr] md:gap-10">
              <div className="flex justify-start md:justify-center">
                <div className="flex h-28 w-28 items-center justify-center overflow-hidden rounded-full border border-white/25 bg-white shadow-[0_10px_24px_rgba(0,0,0,0.18)] md:h-36 md:w-36">
                  <AgencyLogo agency={agency} className="text-4xl md:text-5xl" />
                </div>
              </div>

              <div className="max-w-3xl text-left text-white">
                <p className="font-label text-[12px] font-semibold uppercase tracking-[0.24em] text-white/70">
                  {agency?.short || 'Partner Agency'}
                </p>

                <h1 className="mt-3 font-headline text-4xl font-extrabold leading-tight tracking-tight sm:text-5xl">
                  {agency?.name || 'Agency Details'}
                </h1>

                {agencyDescription && (
                  <p className="mt-4 max-w-2xl whitespace-pre-line text-base leading-7 text-white/80 md:text-lg">
                    {agencyDescription}
                  </p>
                )}
              </div>
            </div>
          </div>
        </section>

        <section className="border-b border-outline-variant bg-white">
          <div className="mx-auto grid max-w-7xl gap-4 px-4 py-6 md:grid-cols-3 md:gap-5 md:px-8">
            <InfoCard icon="call" label="Contact info">
              {hasContactInfo ? (
                <p className="whitespace-pre-line text-sm leading-6 text-on-surface-variant">
                  {contactInfo}
                </p>
              ) : (
                <p className="text-sm text-on-surface-variant">
                  Contact info not yet available
                </p>
              )}
            </InfoCard>

            <InfoCard icon="map" label="Location">
              {hasLocationData ? (
                <div className="space-y-3">
                  <AgencyMapView
                    mapLink={agency?.map_link}
                    latitude={agency?.latitude}
                    longitude={agency?.longitude}
                    locationQuery={agency?.location_query}
                    agencyName={agency?.name}
                    embedHeight="120px"
                  />
                </div>
              ) : (
                <p className="text-sm text-on-surface-variant">Location not yet available</p>
              )}
            </InfoCard>

            <InfoCard icon="work" label="Total services">
              <div className="flex items-end gap-3">
                <span className="font-headline text-3xl font-extrabold text-primary">
                  {services.length}
                </span>
                <span className="pb-1 text-sm text-on-surface-variant">
                  {services.length === 1 ? 'service' : 'services'} listed
                </span>
              </div>
            </InfoCard>
          </div>
        </section>

        <section className="mx-auto max-w-7xl px-4 py-14 md:px-8">
          <div className="mb-8 flex items-end justify-between gap-4">
            <div>
              <p className="text-[11px] font-bold uppercase tracking-[0.24em] text-secondary">
                Public service directory
              </p>
              <h2 className="mt-2 font-headline text-2xl font-extrabold tracking-tight text-on-surface md:text-3xl">
                Services &amp; Offerings
              </h2>
            </div>
          </div>

          {services.length > 0 ? (
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
              {services.map((service) => {
                const serviceDescription = sanitizeText(service?.description);
                const requirements = Array.isArray(service?.requirements)
                  ? service.requirements.filter((requirement) => sanitizeText(requirement?.name))
                  : [];
                const processingDays = formatProcessingDays(service?.processing_days);

                return (
                  <article
                    key={service.id}
                    className="relative border border-outline-variant/30 bg-surface-container-lowest p-5 shadow-sm transition duration-200 ease-out hover:-translate-y-0.5 hover:shadow-[0_10px_24px_rgba(11,92,146,0.08)]"
                  >
                    {processingDays && (
                      <span className="absolute right-4 top-4 border border-primary/20 bg-primary/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-primary">
                        {processingDays}
                      </span>
                    )}

                    <div className={processingDays ? 'pr-20' : ''}>
                      <h3 className="text-lg font-bold leading-snug text-primary">
                        {service.name}
                      </h3>

                      {serviceDescription && (
                        <p className="mt-3 text-sm leading-6 text-on-surface-variant">
                          {serviceDescription}
                        </p>
                      )}

                      {requirements.length > 0 && (
                        <div className="mt-4">
                          <p className="text-[10px] font-bold uppercase tracking-[0.24em] text-on-surface-variant">
                            Requirements
                          </p>

                          <ul className="mt-2 list-disc space-y-1 pl-4 text-[12px] leading-5 text-on-surface-variant marker:text-primary">
                            {requirements.map((requirement) => (
                              <li key={requirement.id}>{requirement.name}</li>
                            ))}
                          </ul>
                        </div>
                      )}
                    </div>
                  </article>
                );
              })}
            </div>
          ) : (
            <div className="border border-outline-variant/30 bg-surface-container-lowest p-6 text-sm text-on-surface-variant">
              No services listed yet.
            </div>
          )}
        </section>
      </main>

      <AppFooter />
      <ChatBot />
    </div>
  );
}
