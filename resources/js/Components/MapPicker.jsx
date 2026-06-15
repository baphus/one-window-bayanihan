import { useEffect, useRef } from 'react';
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

  useEffect(() => {
    if (!mapRef.current || mapInstanceRef.current) return;

    const center = latitude != null && longitude != null
      ? [latitude, longitude]
      : [10.3157, 123.8854]; // Cebu City default

    const zoom = latitude != null && longitude != null ? 15 : 13;

    const map = L.map(mapRef.current, {
      center,
      zoom,
      scrollWheelZoom: true,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
      maxZoom: 19,
    }).addTo(map);

    // Place initial marker if coordinates exist
    if (latitude != null && longitude != null) {
      markerRef.current = L.marker([latitude, longitude], { draggable: true })
        .addTo(map);

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
    };
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  // Sync external prop changes (e.g., clearing lat/lng)
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
      <div
        ref={mapRef}
        className="h-64 w-full rounded-lg border border-slate-300 overflow-hidden"
      />
      <p className="text-sm text-slate-600">
        {hasCoords
          ? `📍 Selected: ${Number(latitude).toFixed(6)}, ${Number(longitude).toFixed(6)}`
          : '📍 Click on the map to set location'}
      </p>
    </div>
  );
}
