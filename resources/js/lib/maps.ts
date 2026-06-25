/**
 * Google Maps URL parsing utilities.
 *
 * Supports:
 *   - /place/Name/@lat,lng,zoom  (place links with coordinates)
 *   - ?q=lat,lng                  (query parameter with coordinates)
 *   - ?q=place+name               (query parameter with text query)
 *   - maps.app.goo.gl/*           (shortened links — not parseable)
 *
 * Does NOT follow redirects or make network requests.
 */

export interface ParsedGoogleMapsUrl {
  lat: number | null;
  lng: number | null;
  query: string | null;
  isParseable: boolean;
}

const GOOGLE_MAPS_DOMAINS = /^https?:\/\/(www\.)?google\.(com|ph)\/maps\//;
const GOOGLE_SHORT_DOMAIN = /^https?:\/\/maps\.app\.goo\.gl\//;

/**
 * Try to parse a coordinate pair from a `@lat,lng[,zoom]` token.
 */
function tryParseAtCoords(url: string): { lat: number; lng: number } | null {
  const match = url.match(/@(-?\d+\.?\d*),(-?\d+\.?\d*)/);
  if (!match) return null;
  const lat = parseFloat(match[1]);
  const lng = parseFloat(match[2]);
  if (isNaN(lat) || isNaN(lng)) return null;
  return { lat, lng };
}

/**
 * Extract a place name from the URL path between /place/ and the @ sign.
 * Example: /place/OWWA/@10.3,123.8 → "OWWA"
 */
function extractPlaceName(url: string): string | null {
  const match = url.match(/\/place\/([^@]+?)(?:\/@|\/data=|\/?(?:$|\?))/);
  if (!match) return null;
  return decodeURIComponent(match[1].replace(/\/$/, '')).trim() || null;
}

/**
 * Try to parse a `?q=` query parameter value as coordinates (lat,lng).
 */
function tryParseQueryAsCoords(q: string): { lat: number; lng: number } | null {
  const match = q.match(/^(-?\d+\.?\d*)\s*,\s*(-?\d+\.?\d*)$/);
  if (!match) return null;
  const lat = parseFloat(match[1]);
  const lng = parseFloat(match[2]);
  if (isNaN(lat) || isNaN(lng)) return null;
  return { lat, lng };
}

/**
 * Parse a Google Maps URL into extracted coordinates and/or place query.
 *
 * Returns `isParseable: false` for goo.gl shortened links, empty strings,
 * or unrecognised URLs — in which case lat/lng/query are all null.
 */
export function parseGoogleMapsUrl(url: string): ParsedGoogleMapsUrl {
  if (!url || typeof url !== 'string' || !url.trim()) {
    return { lat: null, lng: null, query: null, isParseable: false };
  }

  const trimmed = url.trim();

  // Shortened goo.gl — cannot parse
  if (GOOGLE_SHORT_DOMAIN.test(trimmed)) {
    return { lat: null, lng: null, query: null, isParseable: false };
  }

  // Not a Google Maps URL at all
  if (!GOOGLE_MAPS_DOMAINS.test(trimmed)) {
    return { lat: null, lng: null, query: null, isParseable: false };
  }

  // Try @lat,lng from path-based place URLs
  const atCoords = tryParseAtCoords(trimmed);
  const placeName = extractPlaceName(trimmed);

  // Try ?q= from query string
  let queryStr = '';
  const qIndex = trimmed.indexOf('?');
  if (qIndex !== -1) {
    const searchParams = new URLSearchParams(trimmed.slice(qIndex));
    queryStr = searchParams.get('q') ?? '';
  }

  // Case 1: @lat,lng found — use it for coordinates, placeName for query
  if (atCoords) {
    return {
      lat: atCoords.lat,
      lng: atCoords.lng,
      query: placeName || (queryStr || null),
      isParseable: true,
    };
  }

  // Case 2: ?q= parameter found
  if (queryStr) {
    const qCoords = tryParseQueryAsCoords(queryStr);
    if (qCoords) {
      return {
        lat: qCoords.lat,
        lng: qCoords.lng,
        query: null, // No meaningful place name — just coordinates
        isParseable: true,
      };
    }
    // Text query (place name)
    return {
      lat: null,
      lng: null,
      query: decodeURIComponent(queryStr.replace(/\+/g, ' ')),
      isParseable: true,
    };
  }

  // Fallback: recognised Google Maps URL but couldn't extract anything
  return { lat: null, lng: null, query: null, isParseable: false };
}

/**
 * Build a Google Maps embed iframe URL.
 *
 * Priority:
 *   1. lat/lng → `https://www.google.com/maps?q=lat,lng&output=embed`
 *   2. locationQuery → `https://www.google.com/maps?q=<encoded>&output=embed`
 *   3. mapLink (non-parseable, e.g. goo.gl) → returns null (use the link directly)
 *   4. Nothing → null
 */
export function getMapEmbedUrl(
  mapLink: string | null,
  lat: number | null,
  lng: number | null,
  locationQuery: string | null,
): string | null {
  if (lat != null && lng != null) {
    return `https://www.google.com/maps?q=${lat},${lng}&output=embed`;
  }
  if (locationQuery) {
    return `https://www.google.com/maps?q=${encodeURIComponent(locationQuery)}&output=embed`;
  }
  // For non-parseable URLs (e.g. goo.gl), return null — consumer shows a link instead
  return null;
}

/**
 * Build a "View on Google Maps" link URL (without `output=embed`).
 * Used when the embed cannot be rendered (e.g. goo.gl links).
 */
export function getMapLinkUrl(
  mapLink: string | null,
  lat: number | null,
  lng: number | null,
  locationQuery: string | null,
): string | null {
  // If there's a map_link stored, use it directly (handles goo.gl, custom URLs)
  if (mapLink) return mapLink;
  if (lat != null && lng != null) {
    return `https://www.google.com/maps?q=${lat},${lng}`;
  }
  if (locationQuery) {
    return `https://www.google.com/maps?q=${encodeURIComponent(locationQuery)}`;
  }
  return null;
}
