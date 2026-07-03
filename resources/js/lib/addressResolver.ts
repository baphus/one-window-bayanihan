import { philippineAddressData } from '@/data/philippine-addresses';

type AddressLike = {
    street?: string | null;
    barangay?: string | null;
    city_municipality?: string | null;
    municipality?: string | null;
    province?: string | null;
    region?: string | null;
};

const addressNameByCode = (() => {
    const map = new Map<string, string>();
    const addOptions = (options: Array<{ code: string; name: string }> = []) => {
        options.forEach((option) => map.set(option.code, option.name));
    };

    addOptions(philippineAddressData.regions);
    Object.values(philippineAddressData.provincesByRegion).forEach(addOptions);
    Object.values(philippineAddressData.citiesByProvince).forEach(addOptions);
    Object.values(philippineAddressData.citiesByRegion).forEach(addOptions);
    Object.values(philippineAddressData.barangaysByCity).forEach(addOptions);

    return map;
})();

export function resolveAddressValue(value?: string | null): string {
    if (!value) return '';
    return addressNameByCode.get(value) || value;
}

export function formatResolvedAddress(address?: AddressLike | null, fallback = 'N/A'): string {
    if (!address) return fallback;

    const parts = [
        address.street,
        resolveAddressValue(address.barangay),
        resolveAddressValue(address.city_municipality || address.municipality),
        resolveAddressValue(address.province),
        resolveAddressValue(address.region),
    ].filter(Boolean);

    return parts.length > 0 ? parts.join(', ') : fallback;
}
