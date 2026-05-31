import { useState, useEffect, useCallback } from 'react';

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
            className={`h-10 w-full rounded-[3px] border border-[#cbd5e1] px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 ${disabled ? 'bg-slate-50 text-slate-400' : ''}`}
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
            className="h-10 w-full rounded-[3px] border border-[#cbd5e1] px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
        />
    );
}

export default function AddressDropdowns({ values, onChange, errors }) {
    const [regions, setRegions] = useState([]);
    const [provinces, setProvinces] = useState([]);
    const [cities, setCities] = useState([]);
    const [barangays, setBarangays] = useState([]);

    const [loadingRegions, setLoadingRegions] = useState(false);
    const [loadingProvinces, setLoadingProvinces] = useState(false);
    const [loadingCities, setLoadingCities] = useState(false);
    const [loadingBarangays, setLoadingBarangays] = useState(false);

    const [apiFailed, setApiFailed] = useState(false);

    // Fetch regions on mount
    useEffect(() => {
        setLoadingRegions(true);
        setApiFailed(false);
        fetch('/api/address/regions')
            .then((res) => res.json())
            .then((data) => {
                if (Array.isArray(data)) {
                    setRegions(data);
                } else {
                    setRegions([]);
                }
            })
            .catch(() => {
                setApiFailed(true);
                setRegions([]);
            })
            .finally(() => setLoadingRegions(false));
    }, []);

    // When region changes, fetch provinces
    const handleRegionChange = useCallback((value) => {
        onChange('region', value);
        onChange('province', '');
        onChange('city_municipality', '');
        onChange('barangay', '');
        setProvinces([]);
        setCities([]);
        setBarangays([]);

        if (!value) return;

        setLoadingProvinces(true);
        fetch(`/api/address/provinces?region=${encodeURIComponent(value)}`)
            .then((res) => res.json())
            .then((data) => {
                setProvinces(Array.isArray(data) ? data : []);
            })
            .catch(() => {
                setApiFailed(true);
                setProvinces([]);
            })
            .finally(() => setLoadingProvinces(false));
    }, [onChange]);

    // When province changes, fetch cities
    const handleProvinceChange = useCallback((value) => {
        onChange('province', value);
        onChange('city_municipality', '');
        onChange('barangay', '');
        setCities([]);
        setBarangays([]);

        if (!value) return;

        setLoadingCities(true);
        fetch(`/api/address/cities?province=${encodeURIComponent(value)}`)
            .then((res) => res.json())
            .then((data) => {
                setCities(Array.isArray(data) ? data : []);
            })
            .catch(() => {
                setApiFailed(true);
                setCities([]);
            })
            .finally(() => setLoadingCities(false));
    }, [onChange]);

    // When city changes, fetch barangays
    const handleCityChange = useCallback((value) => {
        onChange('city_municipality', value);
        onChange('barangay', '');
        setBarangays([]);

        if (!value) return;

        setLoadingBarangays(true);
        fetch(`/api/address/barangays?city=${encodeURIComponent(value)}`)
            .then((res) => res.json())
            .then((data) => {
                setBarangays(Array.isArray(data) ? data : []);
            })
            .catch(() => {
                setApiFailed(true);
                setBarangays([]);
            })
            .finally(() => setLoadingBarangays(false));
    }, [onChange]);

    // If API failed, fall back to text inputs
    if (apiFailed) {
        return (
            <div className="rounded-lg border border-amber-200 bg-amber-50 p-4">
                <p className="mb-3 text-[12px] font-semibold text-amber-800">Address lookup unavailable — please type your address manually.</p>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field label="Region">
                        <Input value={values.region} onChange={(v) => onChange('region', v)} placeholder="e.g. Central Visayas" />
                    </Field>
                    <Field label="Province">
                        <Input value={values.province} onChange={(v) => onChange('province', v)} placeholder="e.g. Cebu" />
                    </Field>
                    <Field label="City/Municipality">
                        <Input value={values.city_municipality} onChange={(v) => onChange('city_municipality', v)} placeholder="e.g. Cebu City" />
                    </Field>
                    <Field label="Barangay">
                        <Input value={values.barangay} onChange={(v) => onChange('barangay', v)} placeholder="e.g. Poblacion" />
                    </Field>
                    <div className="md:col-span-2">
                        <Field label="Street">
                            <Input value={values.street} onChange={(v) => onChange('street', v)} placeholder="House/Block/Lot No." />
                        </Field>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <Field label="Region">
                <Select
                    value={values.region}
                    onChange={handleRegionChange}
                    options={regions}
                    placeholder={loadingRegions ? 'Loading regions...' : 'Select region...'}
                />
            </Field>
            <Field label="Province">
                <Select
                    value={values.province}
                    onChange={handleProvinceChange}
                    options={provinces}
                    placeholder={!values.region ? 'Select region first' : loadingProvinces ? 'Loading provinces...' : 'Select province...'}
                    disabled={!values.region}
                />
            </Field>
            <Field label="City/Municipality">
                <Select
                    value={values.city_municipality}
                    onChange={handleCityChange}
                    options={cities}
                    placeholder={!values.province ? 'Select province first' : loadingCities ? 'Loading cities...' : 'Select city/municipality...'}
                    disabled={!values.province}
                />
            </Field>
            <Field label="Barangay">
                <Select
                    value={values.barangay}
                    onChange={handleBarangayChange || ((v) => { onChange('barangay', v); })}
                    options={barangays}
                    placeholder={!values.city_municipality ? 'Select city first' : loadingBarangays ? 'Loading barangays...' : 'Select barangay...'}
                    disabled={!values.city_municipality}
                />
            </Field>
            <div className="md:col-span-2">
                <Field label="Street">
                    <Input value={values.street} onChange={(v) => onChange('street', v)} placeholder="House/Block/Lot No., Street Name" />
                </Field>
            </div>
        </div>
    );
}
