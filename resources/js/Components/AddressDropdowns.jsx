import { useCallback } from 'react';
import InputError from '@/Components/InputError';
import {
    getBarangaysByCity,
    getCitiesByProvince,
    getCitiesByRegion,
    getProvincesByRegion,
    getRegions,
} from '@/data/philippine-addresses';

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

function Select({ value, onChange, options, placeholder, disabled }) {
    return (
        <select
            value={value}
            onChange={(e) => onChange(e.target.value)}
            disabled={disabled}
            className={`h-10 w-full rounded-[3px] border border-slate-300 px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 ${disabled ? 'bg-slate-50 text-slate-400' : ''}`}
        >
            {placeholder && <option value="">{placeholder}</option>}
            {options.map((opt) => (
                <option key={opt.code} value={opt.code}>{opt.name}</option>
            ))}
        </select>
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
    const regions = getRegions();
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
                <Select
                    value={values.region}
                    onChange={handleRegionChange}
                    options={regions}
                    placeholder="Select region..."
                />
                <InputError message={errors?.region} className="mt-1" />
            </Field>
            <Field label="Province">
                <Select
                    value={values.province}
                    onChange={handleProvinceChange}
                    options={provinces}
                    placeholder={!values.region ? 'Select region first' : regionHasProvinces ? 'Select province...' : 'No province needed'}
                    disabled={!values.region || !regionHasProvinces}
                />
                <InputError message={errors?.province} className="mt-1" />
            </Field>
            <Field label="City/Municipality">
                <Select
                    value={values.city_municipality}
                    onChange={handleCityChange}
                    options={cities}
                    placeholder={!values.region ? 'Select region first' : regionHasProvinces && !values.province ? 'Select province first' : 'Select city/municipality...'}
                    disabled={!values.region || (regionHasProvinces && !values.province)}
                />
                <InputError message={errors?.city_municipality} className="mt-1" />
            </Field>
            <Field label="Barangay">
                <Select
                    value={values.barangay}
                    onChange={handleBarangayChange}
                    options={barangays}
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
