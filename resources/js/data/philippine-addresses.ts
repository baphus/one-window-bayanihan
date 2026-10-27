// Re-export shim: the dataset lives in ./philippine-addresses.json.
// Regenerate both files with `npm run addresses:sync`.
import data from './philippine-addresses.json';

export type PhilippineAddressOption = {
    code: string;
    name: string;
};

export type PhilippineAddressData = {
    regions: PhilippineAddressOption[];
    provincesByRegion: Record<string, PhilippineAddressOption[]>;
    citiesByProvince: Record<string, PhilippineAddressOption[]>;
    citiesByRegion: Record<string, PhilippineAddressOption[]>;
    barangaysByCity: Record<string, PhilippineAddressOption[]>;
};

export const philippineAddressData: PhilippineAddressData = data;

export const getRegions = (): PhilippineAddressOption[] => philippineAddressData.regions;

export const getProvincesByRegion = (regionCode?: string): PhilippineAddressOption[] => (regionCode ? philippineAddressData.provincesByRegion[regionCode] ?? [] : []);

export const getCitiesByProvince = (provinceCode?: string): PhilippineAddressOption[] => (provinceCode ? philippineAddressData.citiesByProvince[provinceCode] ?? [] : []);

export const getCitiesByRegion = (regionCode?: string): PhilippineAddressOption[] => (regionCode ? philippineAddressData.citiesByRegion[regionCode] ?? [] : []);

export const getBarangaysByCity = (cityCode?: string): PhilippineAddressOption[] => (cityCode ? philippineAddressData.barangaysByCity[cityCode] ?? [] : []);

const regionForms = (value: string): string[] => {
    const normalized = value.trim().toLowerCase();
    const match = normalized.match(/^region\s+(.+?)\s*\((.+)\)$/i);

    return match
        ? [normalized, `region ${match[1]}`, match[1], match[2]]
        : [normalized];
};

const nameForms = (value: string): string[] => {
    const normalized = value.trim().toLowerCase();
    const forms = [normalized];

    if (normalized.startsWith('city of ')) {
        forms.push(normalized.slice('city of '.length));
    }

    if (normalized.endsWith(' city')) {
        forms.push(normalized.slice(0, -' city'.length));
    }

    return forms;
};

const findOption = (
    options: PhilippineAddressOption[],
    value: string | null | undefined,
    isRegion = false,
): PhilippineAddressOption | undefined => {
    if (!value) {
        return undefined;
    }

    return options.find((option) => (
        option.code === value
        || (isRegion
            ? regionForms(option.name).some((form) => regionForms(value).includes(form))
            : nameForms(option.name).some((form) => nameForms(value).includes(form)))
    ));
};

export const resolveAddressToCodes = (address?: Partial<Record<'region' | 'province' | 'city_municipality' | 'barangay', string>>): Record<'region' | 'province' | 'city_municipality' | 'barangay', string> => {
    const region = findOption(getRegions(), address?.region, true);
    const province = region
        ? findOption(getProvincesByRegion(region.code), address?.province)
        : undefined;
    const cities = province
        ? getCitiesByProvince(province.code)
        : region
            ? getCitiesByRegion(region.code)
            : [];
    const city = findOption(cities, address?.city_municipality);
    const barangay = city ? findOption(getBarangaysByCity(city.code), address?.barangay) : undefined;

    return {
        region: region?.code ?? '',
        province: province?.code ?? '',
        city_municipality: city?.code ?? '',
        barangay: barangay?.code ?? '',
    };
};

/**
 * The inverse of resolveAddressToCodes: turn the dropdown option codes a form
 * holds back into the display names every other surface stores.
 *
 * The profile form keeps its address as option codes (that is what the
 * dropdowns need), but the API, the case record and next-of-kin rows all keep
 * names. Resolving here keeps a single source of truth for the mapping.
 */
export const addressCodesToNames = (address?: Partial<Record<'region' | 'province' | 'city_municipality' | 'barangay', string>>): Record<'region' | 'province' | 'city_municipality' | 'barangay', string> => {
    const region = getRegions().find((option) => option.code === address?.region);
    const province = region
        ? getProvincesByRegion(region.code).find((option) => option.code === address?.province)
        : undefined;
    const cities = province
        ? getCitiesByProvince(province.code)
        : region
            ? getCitiesByRegion(region.code)
            : [];
    const city = cities.find((option) => option.code === address?.city_municipality);
    const barangay = city
        ? getBarangaysByCity(city.code).find((option) => option.code === address?.barangay)
        : undefined;

    return {
        region: region?.name ?? '',
        province: province?.name ?? '',
        city_municipality: city?.name ?? '',
        barangay: barangay?.name ?? '',
    };
};
