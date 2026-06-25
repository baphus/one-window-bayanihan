import { getMapEmbedUrl, getMapLinkUrl } from '@/lib/maps';

/**
 * Shared map rendering component for agency locations.
 *
 * Rendering priority:
 *   1. lat + lng → Google Maps embed iframe
 *   2. locationQuery → embed iframe (backward compat for seeded agencies)
 *   3. mapLink (e.g. goo.gl) → "View on Google Maps" link
 *   4. All null → "No location set" placeholder
 */
export default function AgencyMapView({
  mapLink,
  latitude,
  longitude,
  locationQuery,
  agencyName,
  embedHeight = '200px',
}) {
  const embedUrl = getMapEmbedUrl(mapLink, latitude, longitude, locationQuery);
  const linkUrl = getMapLinkUrl(mapLink, latitude, longitude, locationQuery);

  // Priority 1 & 2: embed iframe
  if (embedUrl) {
    return (
      <div className="overflow-hidden rounded-[3px] border border-[#d8dee8]">
        <iframe
          title={`${agencyName || 'Agency'} location`}
          src={embedUrl}
          className="w-full"
          style={{ height: embedHeight }}
          loading="lazy"
          referrerPolicy="no-referrer-when-downgrade"
        />
      </div>
    );
  }

  // Priority 3: link-only (goo.gl or unparseable URL)
  if (linkUrl) {
    return (
      <a
        href={linkUrl}
        target="_blank"
        rel="noopener noreferrer"
        className="inline-flex items-center gap-1 text-xs font-semibold text-[#0b5c92] hover:underline"
      >
        <span className="material-symbols-outlined text-[14px]">map</span>
        View on Google Maps
      </a>
    );
  }

  // Priority 4: no location data
  return (
    <p className="text-sm text-slate-500">No location set</p>
  );
}

export { getMapLinkUrl };
