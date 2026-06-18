import { useEffect, useRef, useState } from 'react';
import { MapPin } from 'lucide-react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// Fix Leaflet default marker icons for Vite bundler (use CDN URLs)
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
  iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
  iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
  shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
});

export default function MapPicker({ latitude, longitude, onChange }) {
  const mapRef = useRef(null);
  const mapInstanceRef = useRef(null);
  const markerRef = useRef(null);
  const searchTimeoutRef = useRef(null);

  const [searchQuery, setSearchQuery] = useState('');
  const [searchResults, setSearchResults] = useState([]);
  const [searching, setSearching] = useState(false);
  const [showDropdown, setShowDropdown] = useState(false);
  const [highlightedIndex, setHighlightedIndex] = useState(-1);
  const [error, setError] = useState(null);
  const lastNominatimTimeRef = useRef(0);

  /** Pan map to coords and place/update marker */
  function flyToLocation(lat, lng, query) {
    if (!mapInstanceRef.current) return;
    const map = mapInstanceRef.current;

    map.flyTo([lat, lng], 15, { duration: 1 });

    if (markerRef.current) {
      markerRef.current.setLatLng([lat, lng]);
    } else {
      markerRef.current = L.marker([lat, lng], { draggable: true }).addTo(map);
      markerRef.current.on('dragend', (ev) => {
        const pos = ev.target.getLatLng();
        onChange?.({ latitude: pos.lat, longitude: pos.lng });
      });
    }

    onChange?.({ latitude: lat, longitude: lng, location_query: query });
  }

  /** Format display_name: preserve specific place name + city + province */
  function formatDisplayName(displayName) {
    if (!displayName) return '';
    const parts = displayName.split(',').map((p) => p.trim()).filter(Boolean);
    if (parts.length <= 3) return parts.join(', ');
    // Keep first part (specific place name) + city + province, drop region/country
    const city = parts.length >= 4 ? parts[parts.length - 3] : null;
    const province = parts.length >= 3 ? parts[parts.length - 2] : null;
    return [parts[0], city, province].filter(Boolean).join(', ');
  }

  /** Rate-limited Nominatim search with 1s cooldown */
  function performSearch(query) {
    setSearching(true);
    setError(null);
    (async () => {
      try {
        // Enforce minimum 1s between requests (Nominatim ToS)
        const now = Date.now();
        const elapsed = now - lastNominatimTimeRef.current;
        if (elapsed < 1000) {
          await new Promise((r) => setTimeout(r, 1000 - elapsed));
        }
        const res = await fetch(
          `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(query)}&format=json&limit=10&countrycodes=PH`,
          {
            headers: {
              'Accept-Language': 'en',
              'User-Agent': 'OneWindowBayanihan/1.0',
            },
          },
        );
        if (!res.ok) throw new Error('Nominatim request failed');
        const data = await res.json();
        lastNominatimTimeRef.current = Date.now();
        setSearchResults(data);
        setShowDropdown(true);
      } catch {
        setSearchResults([]);
        setError('Could not load locations. Please try again.');
        setShowDropdown(true);
      } finally {
        setSearching(false);
      }
    })();
  }

  function handleSearchInput(e) {
    const value = e.target.value;
    setSearchQuery(value);
    setHighlightedIndex(-1);

    if (searchTimeoutRef.current) {
      clearTimeout(searchTimeoutRef.current);
    }

    if (!value.trim()) {
      setSearchResults([]);
      setShowDropdown(false);
      setError(null);
      return;
    }

    searchTimeoutRef.current = setTimeout(() => performSearch(value), 1000);
  }

  /** Keyboard navigation: arrows, enter, escape */
  function handleKeyDown(e) {
    if (!showDropdown) return;

    const items = searchResults;
    if (items.length === 0) {
      if (e.key === 'Escape') {
        setShowDropdown(false);
        setHighlightedIndex(-1);
      }
      return;
    }

    switch (e.key) {
      case 'ArrowDown':
        e.preventDefault();
        setHighlightedIndex((prev) => (prev < items.length - 1 ? prev + 1 : 0));
        break;
      case 'ArrowUp':
        e.preventDefault();
        setHighlightedIndex((prev) => (prev > 0 ? prev - 1 : items.length - 1));
        break;
      case 'Enter':
        e.preventDefault();
        if (highlightedIndex >= 0 && highlightedIndex < items.length) {
          handleResultSelect(items[highlightedIndex]);
        }
        break;
      case 'Escape':
        e.preventDefault();
        setShowDropdown(false);
        setHighlightedIndex(-1);
        break;
    }
  }

  /** Handle selection from dropdown */
  function handleResultSelect(result) {
    const displayName = result.display_name;
    const parts = displayName.split(',').map((p) => p.trim()).filter(Boolean);
    setSearchQuery(parts[0]); // Show just the specific place name in the input
    setShowDropdown(false);
    setHighlightedIndex(-1);
    setError(null);
    flyToLocation(parseFloat(result.lat), parseFloat(result.lon), displayName);
  }

  /** Hide dropdown on blur (delayed so click can register) */
  function handleBlur() {
    setTimeout(() => {
      setShowDropdown(false);
      setHighlightedIndex(-1);
    }, 200);
  }

  /** Show dropdown on focus if results exist */
  function handleFocus() {
    if (searchResults.length > 0) {
      setShowDropdown(true);
    }
  }

  // --- Map initialisation (existing behaviour preserved) ---
  useEffect(() => {
    if (!mapRef.current || mapInstanceRef.current) return;

    const center =
      latitude != null && longitude != null
        ? [latitude, longitude]
        : [10.3157, 123.8854]; // Cebu City default

    const zoom = latitude != null && longitude != null ? 15 : 13;

    const map = L.map(mapRef.current, {
      center,
      zoom,
      scrollWheelZoom: true,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution:
        '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
      maxZoom: 19,
    }).addTo(map);

    // Place initial marker if coordinates exist
    if (latitude != null && longitude != null) {
      markerRef.current = L.marker([latitude, longitude], { draggable: true }).addTo(map);

      markerRef.current.on('dragend', (e) => {
        const pos = e.target.getLatLng();
        onChange?.({ latitude: pos.lat, longitude: pos.lng });
      });
    }

    // Click handler — place or move marker
    map.on('click', (e) => {
      const { lat, lng } = e.latlng;

      if (markerRef.current) {
        markerRef.current.setLatLng([lat, lng]);
      } else {
        markerRef.current = L.marker([lat, lng], { draggable: true }).addTo(map);
        markerRef.current.on('dragend', (ev) => {
          const pos = ev.target.getLatLng();
          onChange?.({ latitude: pos.lat, longitude: pos.lng });
        });
      }

      onChange?.({ latitude: lat, longitude: lng });
    });

    mapInstanceRef.current = map;

    return () => {
      map.remove();
      mapInstanceRef.current = null;
      markerRef.current = null;
      if (searchTimeoutRef.current) {
        clearTimeout(searchTimeoutRef.current);
        searchTimeoutRef.current = null;
      }
    };
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  // --- Sync external prop changes (e.g. clearing lat/lng) ---
  useEffect(() => {
    if (!mapInstanceRef.current) return;
    const map = mapInstanceRef.current;

    if (latitude == null || longitude == null) {
      // Clear marker if coordinates removed
      if (markerRef.current) {
        map.removeLayer(markerRef.current);
        markerRef.current = null;
      }
      map.setView([10.3157, 123.8854], 13);
    }
  }, [latitude, longitude]);

  const hasCoords = latitude != null && longitude != null;

  return (
    <div className="space-y-2">
      <style>{`.leaflet-container { z-index: 0; }`}</style>

      {/* Search input */}
      <div>
        <label className="block text-sm font-medium text-slate-700 mb-1">
          Search Location
        </label>
        <div className="relative">
          <MapPin className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
          <input
            type="text"
            value={searchQuery}
            onChange={handleSearchInput}
            onKeyDown={handleKeyDown}
            onFocus={handleFocus}
            onBlur={handleBlur}
            placeholder="Search for a location..."
            className="block w-full rounded-md border-slate-300 shadow-sm focus:border-primary focus:ring-primary text-sm pl-10 pr-3 py-2"
          />
          {searching && (
            <div className="absolute right-3 top-1/2 -translate-y-1/2">
              <div className="h-4 w-4 border-2 border-slate-300 border-t-primary rounded-full animate-spin" />
            </div>
          )}

          {/* Results dropdown */}
          {showDropdown && (
            <div className="absolute inset-x-0 top-full mt-1 z-10 bg-white border border-slate-200 rounded-lg shadow-lg max-h-60 overflow-y-auto">
              {error && (
                <div className="px-4 py-2 text-sm text-red-500">
                  {error}
                </div>
              )}

              {!error && searchResults.length === 0 && !searching && (
                <div className="px-4 py-2 text-sm text-gray-500">
                  No locations found
                </div>
              )}

              {!error &&
                searchResults.map((result, idx) => (
                  <button
                    key={idx}
                    type="button"
                    onMouseDown={(e) => {
                      e.preventDefault();
                      handleResultSelect(result);
                    }}
                    onMouseEnter={() => setHighlightedIndex(idx)}
                    className={`w-full text-left px-4 py-2 text-sm cursor-pointer transition-colors ${
                      idx === highlightedIndex
                        ? 'bg-blue-50 text-blue-700'
                        : 'text-slate-700 hover:bg-gray-100'
                    }`}
                  >
                    {formatDisplayName(result.display_name)}
                    {result.type && (
                      <span className="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600 capitalize">
                        {result.type}
                      </span>
                    )}
                  </button>
                ))}
            </div>
          )}
        </div>
      </div>

      {/* Leaflet map */}
      <div
        ref={mapRef}
        className="h-64 w-full rounded-lg border border-slate-300 overflow-hidden"
      />

      {/* Coordinate readout */}
      <p className="text-sm text-slate-600">
        {hasCoords
          ? `📍 Selected: ${Number(latitude).toFixed(6)}, ${Number(longitude).toFixed(6)}`
          : '📍 Click on the map to set location'}
      </p>
    </div>
  );
}
