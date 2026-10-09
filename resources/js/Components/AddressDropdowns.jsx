import { useCallback } from 'react';
import { usePage } from '@inertiajs/react';
import InputError from '@/Components/InputError';
import SearchableSelect from '@/Components/SearchableSelect';
import {
    getBarangaysByCity,
    getCitiesByProvince,
    getCitiesByRegion,
    getProvincesByRegion,
    getRegions,
} from '@/data/philippine-addresses';

/**
 * The region dropdown is scoped to the served regions from
 * `props.addresses.served_regions` (config/addresses.php), so coverage can be
 * widened — or removed entirely (empty array = every Philippine region) —
 * without code changes. Filtered here rather than in the generated data file
 * so `npm run addresses:sync` keeps regenerating philippine-addresses.ts.
 */
function Field({ label, required, children, className }) {
    return (
        <div className={className}>
            <label className="mb-1.5 block text-[11px] font-bold uppercase tracking-[0.08em] text-slate-600">
                {label}{required ? ' *' : ''}
            </label>
            {children}
        </div>
    );
}

function Input({ value, onChange, placeholder }) {
    return (
        <input
            type="text"
            value={value}
            onChange={(e) => onChange(e.target.value)}
            placeholder={placeholder}
            className="h-10 w-full rounded-[3px] border border-slate-300 px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
        />
    );
}

export default function AddressDropdowns({ values, onChange, errors }) {
    const servedRegions = usePage().props?.addresses?.served_regions ?? [];
    const regions = getRegions().filter(
        (o) => servedRegions.length === 0 || servedRegions.includes(o.code),
    );
    const provinces = getProvincesByRegion(values.region);
    const regionHasProvinces = provinces.length > 0;
    const cities = values.province
        ? getCitiesByProvince(values.province)
        : regionHasProvinces
            ? []
            : getCitiesByRegion(values.region);
    const barangays = getBarangaysByCity(values.city_municipality);

    const handleRegionChange = useCallback((value) => {
        // Single atomic update — React 18 batches separate setData calls, causing
        // each to read stale closure state and the last call to wipe out region.
        onChange({
            region: value,
            province: '',
            city_municipality: '',
            barangay: '',
        });
    }, [onChange]);

    const handleProvinceChange = useCallback((value) => {
        onChange({
            province: value,
            city_municipality: '',
            barangay: '',
        });
    }, [onChange]);

    const handleCityChange = useCallback((value) => {
        onChange({
            city_municipality: value,
            barangay: '',
        });
    }, [onChange]);

    const handleBarangayChange = useCallback((value) => {
        onChange('barangay', value);
    }, [onChange]);

    return (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <Field label="Region">
                <SearchableSelect
                    value={values.region}
                    onChange={handleRegionChange}
                    options={regions.map((o) => ({ value: o.code, label: o.name }))}
                    placeholder="Select region..."
                />
                <InputError message={errors?.region} className="mt-1" />
            </Field>
            <Field label="Province">
                <SearchableSelect
                    value={values.province}
                    onChange={handleProvinceChange}
                    options={provinces.map((o) => ({ value: o.code, label: o.name }))}
                    placeholder={!values.region ? 'Select region first' : regionHasProvinces ? 'Select province...' : 'No province needed'}
                    disabled={!values.region || !regionHasProvinces}
                />
                <InputError message={errors?.province} className="mt-1" />
            </Field>
            <Field label="City/Municipality">
                <SearchableSelect
                    value={values.city_municipality}
                    onChange={handleCityChange}
                    options={cities.map((o) => ({ value: o.code, label: o.name }))}
                    placeholder={!values.region ? 'Select region first' : regionHasProvinces && !values.province ? 'Select province first' : 'Select city/municipality...'}
                    disabled={!values.region || (regionHasProvinces && !values.province)}
                />
                <InputError message={errors?.city_municipality} className="mt-1" />
            </Field>
            <Field label="Barangay">
                <SearchableSelect
                    value={values.barangay}
                    onChange={handleBarangayChange}
                    options={barangays.map((o) => ({ value: o.code, label: o.name }))}
                    placeholder={!values.city_municipality ? 'Select city first' : 'Select barangay...'}
                    disabled={!values.city_municipality}
                />
                <InputError message={errors?.barangay} className="mt-1" />
            </Field>
            <div className="md:col-span-2">
                <Field label="Street">
                    <Input value={values.street} onChange={(v) => onChange('street', v)} placeholder="House/Block/Lot No., Street Name" />
                </Field>
            </div>
        </div>
    );
}
