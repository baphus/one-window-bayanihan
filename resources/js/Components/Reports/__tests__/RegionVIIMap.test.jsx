import { fireEvent, render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import RegionVIIMap, {
  CHOROPLETH_STEPS,
  NO_DATA_FILL,
  REGION_VII_BOUNDS,
  RENDERED_PROVINCE_IDS,
  choroplethFill,
  matchRegionVII,
} from '../RegionVIIMap';
import GeographicMapSection from '@/Pages/Reports/sections/GeographicMapSection';
import { REGION_VII_GEOJSON, REGION_VII_PROVINCES } from '@/data/regionVIIBoundaries';

const captured = vi.hoisted(() => ({ geojson: null, markers: [] }));
const lazyState = vi.hoisted(() => ({ geo: undefined, map: undefined }));

vi.mock('@/Hooks/useLazyProp', () => ({
  useLazyProp: (key) => (key === 'geographicMapData' ? [lazyState.map, false] : [lazyState.geo, false]),
}));

vi.mock('@inertiajs/react', () => ({
  usePage: () => ({ props: { geographicMapData: lazyState.map } }),
}));

vi.mock('react-leaflet', () => ({
  MapContainer: ({ children }) => {
    captured.markers = [];
    return <div data-testid="leaflet-map">{children}</div>;
  },
  GeoJSON: (props) => {
    captured.geojson = props;
    return <div data-testid="leaflet-geojson" />;
  },
  // eslint-disable-next-line react/prop-types
  Marker: ({ icon, eventHandlers, children }) => {
    captured.markers.push({ icon, eventHandlers });
    return <div data-testid="leaflet-marker">{children}</div>;
  },
  Tooltip: ({ children }) => <div>{children}</div>,
  useMap: () => ({ fitBounds: vi.fn(), setMaxBounds: vi.fn() }),
}));

vi.mock('leaflet', () => ({
  default: { divIcon: (options) => ({ html: options.html }) },
}));

const provinces = [
  { id: 'cebu', name: 'Cebu', count: 12, value: '072200000' },
  { id: 'bohol', name: 'Bohol', count: 5, value: '071200000' },
  { id: 'negros-oriental', name: 'Negros Oriental', count: 0, value: '074600000' },
  { id: 'siquijor', name: 'Siquijor', count: 3, value: '076100000' },
];

describe('Region VII boundary data', () => {
  it('bundles genuine multi-area geometry for all four provinces', () => {
    expect(REGION_VII_PROVINCES.map((province) => province.id).sort()).toEqual([
      'bohol',
      'cebu',
      'negros-oriental',
      'siquijor',
    ]);
    expect(REGION_VII_GEOJSON.features.length).toBeGreaterThan(100);
    const kinds = new Set(REGION_VII_GEOJSON.features.map((feature) => feature.properties.province));
    expect(kinds).toEqual(new Set(['cebu', 'bohol', 'negros-oriental', 'siquijor']));
  });
});

describe('matchRegionVII', () => {
  it.each([
    ['CEBU', 'cebu'],
    ['Cebu City', 'cebu'],
    ['BOHOL', 'bohol'],
    ['Negros Oriental', 'negros-oriental'],
    ['SIQUIJOR', 'siquijor'],
  ])('maps payload name %s to %s', (input, expected) => {
    expect(matchRegionVII(input)).toBe(expected);
  });

  it('returns null outside Region VII', () => {
    expect(matchRegionVII('Metro Manila')).toBeNull();
    expect(matchRegionVII(null)).toBeNull();
    expect(matchRegionVII('')).toBeNull();
  });
});

describe('choroplethFill', () => {
  it('paints zero and empty datasets gray', () => {
    expect(choroplethFill(0, 12)).toBe(NO_DATA_FILL);
    expect(choroplethFill(5, 0)).toBe(NO_DATA_FILL);
  });

  it('scales light to dark with the count', () => {
    expect(choroplethFill(12, 12)).toBe(CHOROPLETH_STEPS[CHOROPLETH_STEPS.length - 1]);
    const mid = choroplethFill(6, 12);
    expect(CHOROPLETH_STEPS).toContain(mid);
    expect(choroplethFill(1, 12)).toBe(CHOROPLETH_STEPS[0]);
  });
});

describe('RegionVIIMap', () => {
  it('renders only Cebu and Bohol, dropping the other two provinces', async () => {
    expect(RENDERED_PROVINCE_IDS).toEqual(['cebu', 'bohol']);

    const onProvinceClick = vi.fn();
    render(<RegionVIIMap provinces={provinces} onProvinceClick={onProvinceClick} />);

    // The leaflet chunk loads on demand; wait for it before reading the layers.
    await screen.findByTestId('leaflet-map');

    // Boundary layer carries Cebu + Bohol areas only.
    const renderedKinds = new Set(
      captured.geojson.data.features.map((feature) => feature.properties.province),
    );
    expect(renderedKinds).toEqual(new Set(['cebu', 'bohol']));

    // Badge markers for the two rendered provinces only.
    expect(captured.markers).toHaveLength(2);
    expect(screen.queryByText(/Negros Oriental/)).toBeNull();
    expect(screen.queryByText(/Siquijor/)).toBeNull();
  });

  it('renders the real boundary layer with count badges and legend', async () => {
    const onProvinceClick = vi.fn();
    render(<RegionVIIMap provinces={provinces} onProvinceClick={onProvinceClick} />);

    await screen.findByTestId('leaflet-map');
    expect(captured.geojson.data.features.length).toBeGreaterThan(50);

    // Highest count gets the darkest step; zero gets gray.
    expect(captured.geojson.style({ properties: { province: 'cebu' } }).fillColor).toBe(
      CHOROPLETH_STEPS[CHOROPLETH_STEPS.length - 1],
    );
    expect(captured.geojson.style({ properties: { province: 'bohol' } }).fillColor).not.toBe(
      NO_DATA_FILL,
    );

    // One badge marker per rendered province, badge shows the exact count.
    expect(captured.markers[0].icon.html).toContain('>12<');

    // Legend explains the shading in plain words.
    expect(screen.getByText('No cases')).toBeInTheDocument();
    expect(screen.getByText('Fewer → more cases')).toBeInTheDocument();
  });

  it('binds name-plus-count tooltips and routes clicks to the filter', async () => {
    const onProvinceClick = vi.fn();
    render(<RegionVIIMap provinces={provinces} onProvinceClick={onProvinceClick} />);
    await screen.findByTestId('leaflet-map');

    const layer = { bindTooltip: vi.fn(), on: vi.fn() };
    captured.geojson.onEachFeature({ properties: { province: 'bohol', name: 'Candijay' } }, layer);
    expect(layer.bindTooltip).toHaveBeenCalledWith('Bohol — 5 cases', expect.anything());

    const clickHandler = layer.on.mock.calls.find(([event]) => event === 'click')[1];
    clickHandler();
    expect(onProvinceClick).toHaveBeenCalledWith('071200000');

    captured.markers[0].eventHandlers.click();
    expect(onProvinceClick).toHaveBeenCalledWith('072200000');
  });

  it('outlines the selected province', async () => {
    render(<RegionVIIMap provinces={provinces} selectedProvince="072200000" onProvinceClick={() => {}} />);
    await screen.findByTestId('leaflet-map');
    expect(captured.geojson.style({ properties: { province: 'cebu' } }).weight).toBe(3);
    expect(captured.geojson.style({ properties: { province: 'bohol' } }).weight).toBe(1);
  });

  it('keeps the initial view on Cebu + Bohol, excluding the south and west', () => {
    const [[south, west], [north, east]] = REGION_VII_BOUNDS;
    // Siquijor sits below ~9.3, Negros Oriental west of ~123.2.
    expect(south).toBeGreaterThan(9.1);
    expect(west).toBeGreaterThan(123);
    // Cebu spans up past 11.3 and east past 124.5.
    expect(north).toBeGreaterThan(11.3);
    expect(east).toBeGreaterThan(124.5);
  });
});

describe('GeographicMapSection', () => {
  const provinceOptions = [{ value: '072200000', label: 'Cebu' }];

  it('renders the map plus a keyboard-accessible list with the same counts', async () => {
    lazyState.geo = { labels: ['Cebu', 'Bohol'], data: [12, 5] };
    lazyState.map = undefined;
    const setProvince = vi.fn();
    const setCity = vi.fn();
    render(
      <GeographicMapSection
        province={null}
        setProvince={setProvince}
        setCity={setCity}
        provinceOptions={provinceOptions}
      />,
    );

    await screen.findByTestId('leaflet-map');
    const list = screen.getByRole('list', { name: 'Cases by province' });
    expect(within(list).getByText('Cebu')).toBeInTheDocument();
    expect(within(list).getByText('12 cases')).toBeInTheDocument();
    expect(within(list).getByText('5 cases')).toBeInTheDocument();

    fireEvent.click(within(list).getByRole('button', { name: 'Cebu 12 cases — filter to this province' }));
    expect(setProvince).toHaveBeenCalledWith('072200000');
    expect(setCity).toHaveBeenCalledWith(null);
  });

  it('prefers the map-data provinces shape when present', () => {
    lazyState.geo = undefined;
    lazyState.map = { provinces: [{ name: 'Bohol', cases: 9 }] };
    render(<GeographicMapSection provinceOptions={[]} />);
    expect(screen.getByText('Bohol')).toBeInTheDocument();
    expect(screen.getByText('9 cases')).toBeInTheDocument();
  });

  it('drops out-of-scope provinces from the map and the list', async () => {
    lazyState.geo = undefined;
    lazyState.map = {
      provinces: [
        { name: 'Cebu', cases: 12 },
        { name: 'Bohol', cases: 5 },
        { name: 'Negros Oriental', cases: 7 },
        { name: 'Siquijor', cases: 3 },
      ],
    };
    render(<GeographicMapSection provinceOptions={[]} />);

    await screen.findByTestId('leaflet-map', undefined, { timeout: 5000 });
    const list = screen.getByRole('list', { name: 'Cases by province' });
    expect(within(list).getByText('Cebu')).toBeInTheDocument();
    expect(within(list).getByText('Bohol')).toBeInTheDocument();
    expect(within(list).queryByText('Negros Oriental')).toBeNull();
    expect(within(list).queryByText('Siquijor')).toBeNull();
    expect(captured.markers).toHaveLength(2);
  });

  it('keeps hover, badge, and list counts equal to the payload, including zero', async () => {
    lazyState.geo = undefined;
    lazyState.map = {
      provinces: [
        { name: 'Cebu', cases: 12 },
        { name: 'Bohol', cases: 0 },
      ],
    };
    render(<GeographicMapSection provinceOptions={[]} />);

    // Marker badges carry the exact payload counts.
    await screen.findByTestId('leaflet-map', undefined, { timeout: 5000 });
    expect(captured.markers[0].icon.html).toContain('>12<');
    expect(captured.markers[1].icon.html).toContain('>0<');

    // Hover tooltips agree, word for word.
    const layer = { bindTooltip: vi.fn(), on: vi.fn() };
    captured.geojson.onEachFeature({ properties: { province: 'cebu', name: 'Alcantara' } }, layer);
    expect(layer.bindTooltip).toHaveBeenCalledWith('Cebu — 12 cases', expect.anything());
    captured.geojson.onEachFeature({ properties: { province: 'bohol', name: 'Candijay' } }, layer);
    expect(layer.bindTooltip).toHaveBeenCalledWith('Bohol — 0 cases', expect.anything());

    // Zero-count shading stays gray, never a data blue.
    expect(captured.geojson.style({ properties: { province: 'bohol' } }).fillColor).toBe(
      NO_DATA_FILL,
    );

    // The accessible list shows the same numbers.
    const list = screen.getByRole('list', { name: 'Cases by province' });
    expect(within(list).getByText('12 cases')).toBeInTheDocument();
    expect(within(list).getByText('0 cases')).toBeInTheDocument();
  });

  it('shows the empty message when no geographic data exists', () => {
    lazyState.geo = undefined;
    lazyState.map = undefined;
    render(<GeographicMapSection provinceOptions={[]} />);
    expect(screen.getByText('No geographic data available.')).toBeInTheDocument();
    expect(screen.queryByTestId('leaflet-map')).not.toBeInTheDocument();
  });
});
