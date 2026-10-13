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
