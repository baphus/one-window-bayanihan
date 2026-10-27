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
 * without code changes. The generated dataset is pre-scoped to the served
 * regions by `npm run addresses:sync`; widen the config list, keep the sync
 * script's SERVED_REGIONS constant in step with it, and re-run the sync
 * (`-- --all` for every region) so the wider list actually resolves.
 *
 * `values` may hold either option codes (the default, used by forms that send
 * codes to the API) or the display names (`emitNames`, used where the stored
 * record keeps names — e.g. a next-of-kin address). Both shapes are normalized
 * once at the top so the dropdown chains resolve identically either way.
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
            className="h-10 w-full rounded-lg border border-slate-200 px-3 text-[13px] text-slate-700 outline-none transition-colors placeholder:text-slate-400 focus:border-primary focus:ring-1 focus:ring-primary"
        />
    );
}

/**
 * Resolve a stored value (code or name) to the dataset option.
 */
function toOption(options, value) {
    if (!value) return undefined;
    return options.find(
        (o) => o.code === value || o.name.toLowerCase() === String(value).toLowerCase(),
    );
}

export default function AddressDropdowns({ values, onChange, errors, emitNames = false }) {
    const servedRegions = usePage().props?.addresses?.served_regions ?? [];
    const regions = getRegions().filter(
        (o) => servedRegions.length === 0 || servedRegions.includes(o.code),
    );

    // Normalize whatever shape `values` arrived in to option codes, so a record
    // that stores names still pre-selects the right dropdowns.
    const regionOption = toOption(regions, values.region);
    const provinces = regionOption ? getProvincesByRegion(regionOption.code) : [];
    const provinceOption = toOption(provinces, values.province);
    const regionHasProvinces = provinces.length > 0;
    const cities = regionOption
        ? provinceOption
            ? getCitiesByProvince(provinceOption.code)
            : regionHasProvinces
                ? []
                : getCitiesByRegion(regionOption.code)
        : [];
    const cityOption = toOption(cities, values.city_municipality);
    const barangays = cityOption ? getBarangaysByCity(cityOption.code) : [];
    const barangayOption = toOption(barangays, values.barangay);

    /** Codes or names, depending on how the caller stores this address. */
    const output = (option) => (emitNames ? (option?.name ?? '') : (option?.code ?? ''));

    const handleRegionChange = useCallback((value) => {
        // Single atomic update — React 18 batches separate setData calls, causing
        // each to read stale closure state and the last call to wipe out region.
        const option = toOption(regions, value);
        onChange({
            region: output(option),
            province: '',
            city_municipality: '',
            barangay: '',
        });
    }, [onChange, regions, emitNames]);

    const handleProvinceChange = useCallback((value) => {
        onChange({
            province: output(toOption(provinces, value)),
            city_municipality: '',
            barangay: '',
        });
    }, [onChange, provinces, emitNames]);

    const handleCityChange = useCallback((value) => {
        onChange({
            city_municipality: output(toOption(cities, value)),
            barangay: '',
        });
    }, [onChange, cities, emitNames]);

    const handleBarangayChange = useCallback((value) => {
        onChange('barangay', output(toOption(barangays, value)));
    }, [onChange, barangays, emitNames]);

    return (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Field label="Region">
                <SearchableSelect
                    value={regionOption?.code ?? ''}
                    onChange={handleRegionChange}
                    options={regions.map((o) => ({ value: o.code, label: o.name }))}
                    placeholder="Select region"
                />
                <InputError message={errors?.region} className="mt-1" />
            </Field>
            <Field label="Province">
                <SearchableSelect
                    value={provinceOption?.code ?? ''}
                    onChange={handleProvinceChange}
                    options={provinces.map((o) => ({ value: o.code, label: o.name }))}
                    placeholder={!regionOption ? 'Select region first' : regionHasProvinces ? 'Select province' : 'No province needed'}
                    disabled={!regionOption || !regionHasProvinces}
                />
                <InputError message={errors?.province} className="mt-1" />
            </Field>
            <Field label="City/Municipality">
                <SearchableSelect
                    value={cityOption?.code ?? ''}
                    onChange={handleCityChange}
                    options={cities.map((o) => ({ value: o.code, label: o.name }))}
                    placeholder={!regionOption ? 'Select region first' : regionHasProvinces && !provinceOption ? 'Select province first' : 'Select city/municipality'}
                    disabled={!regionOption || (regionHasProvinces && !provinceOption)}
                />
                <InputError message={errors?.city_municipality} className="mt-1" />
            </Field>
            <Field label="Barangay">
                <SearchableSelect
                    value={barangayOption?.code ?? ''}
                    onChange={handleBarangayChange}
                    options={barangays.map((o) => ({ value: o.code, label: o.name }))}
                    placeholder={!cityOption ? 'Select city first' : 'Select barangay'}
                    disabled={!cityOption}
                />
                <InputError message={errors?.barangay} className="mt-1" />
            </Field>
            <div className="sm:col-span-2">
                <Field label="Street">
                    <Input
                        value={values.street ?? ''}
                        onChange={(v) => onChange('street', v)}
                        placeholder="House / block / lot no., street name"
                    />
                </Field>
                <InputError message={errors?.street} className="mt-1" />
            </div>
        </div>
    );
}
