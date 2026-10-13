const fs = require('fs');
const path = require('path');

const API = 'https://psgc.cloud/api';
const OUT_JSON = path.resolve('resources/js/data/philippine-addresses.json');
const OUT_TS = path.resolve('resources/js/data/philippine-addresses.ts');

// Default output scope: the regions DMW VII serves. Keep in sync with
// config/addresses.php served_regions (no PHP config parsing from Node).
// Pass --all to fetch every region — needed whenever that config list is
// widened or emptied.
const SERVED_REGIONS = ['0700000000', '1800000000'];
const ALL_REGIONS = process.argv.includes('--all');

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

let lastRequestAt = 0;

async function throttle() {
    const now = Date.now();
    const wait = Math.max(0, 1150 - (now - lastRequestAt));

    if (wait) {
        await sleep(wait);
    }

    lastRequestAt = Date.now();
}

async function fetchJson(url, attempts = 6) {
    for (let attempt = 0; attempt < attempts; attempt += 1) {
        await throttle();

        try {
            const response = await fetch(url, { headers: { accept: 'application/json' } });

            if (response.status === 429) {
                const retryAfter = Number(response.headers.get('retry-after')) || Math.min((2 ** attempt) * 5, 60);
                console.log(`Rate limited: ${url}; waiting ${retryAfter}s`);
                await sleep(retryAfter * 1000);
                continue;
            }

            if (!response.ok) {
                throw new Error(`${response.status} ${response.statusText}`);
            }

            return await response.json();
        } catch (error) {
            if (attempt === attempts - 1) {
                throw error;
            }

            const wait = Math.min(1000 * (2 ** attempt), 15000);
            console.log(`Retry ${attempt + 1}/${attempts - 1}: ${url}; ${error.message}; waiting ${wait}ms`);
            await sleep(wait);
        }
    }

    return [];
}

function option(item) {
    return {
        code: String(item.code),
        name: String(item.name),
    };
}

function sortByName(items) {
    return items.map(option).sort((a, b) => a.name.localeCompare(b.name));
}

async function main() {
    console.log(`Fetching Philippine addresses from ${API}${ALL_REGIONS ? ' (all regions)' : ' (served regions only)'}...`);

    const allRegions = sortByName(await fetchJson(`${API}/regions`));
    const regions = ALL_REGIONS
        ? allRegions
        : allRegions.filter((region) => SERVED_REGIONS.includes(region.code));

    if (!ALL_REGIONS) {
        const missing = SERVED_REGIONS.filter((code) => !regions.some((region) => region.code === code));
        if (missing.length > 0) {
            throw new Error(`Served region codes missing from the PSGC response: ${missing.join(', ')}`);
        }
    }

    const provincesByRegion = {};
    const citiesByProvince = {};
    const citiesByRegion = {};
    const barangaysByCity = {};

    for (const [regionIndex, region] of regions.entries()) {
        console.log(`[${regionIndex + 1}/${regions.length}] ${region.name}`);

        const provinces = sortByName(await fetchJson(`${API}/regions/${region.code}/provinces`));
        provincesByRegion[region.code] = provinces;
        citiesByRegion[region.code] = [];

        // NCR and any province-less region keep cities/municipalities directly under the region.
        if (provinces.length === 0) {
            const directCities = sortByName(await fetchJson(`${API}/regions/${region.code}/cities`));
            const directMunicipalities = sortByName(await fetchJson(`${API}/regions/${region.code}/municipalities`));

            citiesByRegion[region.code] = [...directCities, ...directMunicipalities]
                .sort((a, b) => a.name.localeCompare(b.name));

            for (const place of citiesByRegion[region.code]) {
                const cityPath = directCities.some((city) => city.code === place.code) ? 'cities' : 'municipalities';
                barangaysByCity[place.code] = sortByName(await fetchJson(`${API}/${cityPath}/${place.code}/barangays`));
            }
        }

        for (const [provinceIndex, province] of provinces.entries()) {
            console.log(`  [${provinceIndex + 1}/${provinces.length}] ${province.name}`);

            const cities = sortByName(await fetchJson(`${API}/provinces/${province.code}/cities`));
            const municipalities = sortByName(await fetchJson(`${API}/provinces/${province.code}/municipalities`));

            citiesByProvince[province.code] = [...cities, ...municipalities]
                .sort((a, b) => a.name.localeCompare(b.name));

            const barangays = sortByName(await fetchJson(`${API}/provinces/${province.code}/barangays`));

            for (const barangay of barangays) {
                const parentCode = `${barangay.code.slice(0, 7)}000`;

                if (!barangaysByCity[parentCode]) {
                    barangaysByCity[parentCode] = [];
                }

                barangaysByCity[parentCode].push(barangay);
            }
        }
    }

    for (const code of Object.keys(barangaysByCity)) {
        barangaysByCity[code].sort((a, b) => a.name.localeCompare(b.name));
    }

    const generatedAt = new Date().toISOString();
    const data = {
        regions,
        provincesByRegion,
        citiesByProvince,
        citiesByRegion,
        barangaysByCity,
    };

    assertBarangay(data, '0702205001', 'Alambijud');

    const tsShim = `// Auto-generated from ${API} on ${generatedAt} by \`npm run addresses:sync\` — do not edit.
// The dataset itself lives in ./philippine-addresses.json.
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
`;

    fs.mkdirSync(path.dirname(OUT_JSON), { recursive: true });
    fs.writeFileSync(OUT_JSON, `${JSON.stringify(data, null, 4)}\n`, 'utf8');
    fs.writeFileSync(OUT_TS, tsShim, 'utf8');

    const totalProvinces = Object.values(provincesByRegion).reduce((sum, items) => sum + items.length, 0);
    const totalCities = Object.values(citiesByProvince).reduce((sum, items) => sum + items.length, 0)
        + Object.values(citiesByRegion).reduce((sum, items) => sum + items.length, 0);
    const totalBarangays = Object.values(barangaysByCity).reduce((sum, items) => sum + items.length, 0);

    console.log(`Wrote ${OUT_JSON}`);
    console.log(`Wrote ${OUT_TS}`);
    console.log(JSON.stringify({
        regions: regions.length,
        provinces: totalProvinces,
        citiesAndMunicipalities: totalCities,
        barangays: totalBarangays,
    }, null, 2));
}

/**
 * Loud self-check against a known PSGC record: a silent filter or fetch bug
 * must not produce a plausible-looking but wrong dataset. Exits non-zero.
 */
function assertBarangay(data, code, name) {
    const found = Object.values(data.barangaysByCity)
        .flat()
        .some((barangay) => barangay.code === code && barangay.name === name);

    if (!found) {
        console.error(`Self-check FAILED: expected barangay ${name} (${code}) in the generated dataset.`);
        process.exit(1);
    }
}

main().catch((error) => {
    console.error(error);
    process.exit(1);
});
