import { describe, expect, it } from 'vitest';
import { isGoogleMapsUrl, parseGoogleMapsUrl } from '../maps';

describe('Google Maps URLs', () => {
  it.each([
    'https://www.google.com/maps/place/Office',
    'https://maps.google.com/maps?q=London',
    'https://www.google.com.ph/maps?q=Manila',
    'https://maps.google.com.ph/maps?q=Manila',
    'https://goo.gl/maps/abc123',
    'https://maps.app.goo.gl/abc123',
  ])('accepts supported maps URL %s', (url) => {
    expect(isGoogleMapsUrl(url)).toBe(true);
  });

  it('keeps valid shortened map URLs unparseable without rejecting arbitrary goo.gl paths', () => {
    expect(parseGoogleMapsUrl('https://goo.gl/maps/abc123').isParseable).toBe(false);
    expect(isGoogleMapsUrl('https://goo.gl/not-a-map')).toBe(false);
    expect(isGoogleMapsUrl('https://maps.app.goo.gl/')).toBe(false);
  });

  it('rejects lookalike hosts and unapproved regional domains', () => {
    expect(isGoogleMapsUrl('https://google.com.evil.example/maps')).toBe(false);
    expect(isGoogleMapsUrl('https://evilgoogle.com/maps')).toBe(false);
    expect(isGoogleMapsUrl('https://maps.google.co.uk/maps?q=London')).toBe(false);
  });
});
