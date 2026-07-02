const fs = require('fs');
const path = require('path');

const API = 'https://psgc.cloud/api';
const OUT_FILE = path.resolve('resources/js/data/philippine-addresses.ts');

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
    console.log(`Fetching Philippine addresses from ${API}...`);

    const regions = sortByName(await fetchJson(`${API}/regions`));
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

    const body = `// Auto-generated from ${API} on ${generatedAt}.\n// Do not edit individual records by hand; run \`npm run addresses:sync\` to regenerate.\n\nexport type PhilippineAddressOption = {\n    code: string;\n    name: string;\n};\n\nexport type PhilippineAddressData = {\n    regions: PhilippineAddressOption[];\n    provincesByRegion: Record<string, PhilippineAddressOption[]>;\n    citiesByProvince: Record<string, PhilippineAddressOption[]>;\n    citiesByRegion: Record<string, PhilippineAddressOption[]>;\n    barangaysByCity: Record<string, PhilippineAddressOption[]>;\n};\n\nexport const philippineAddressData: PhilippineAddressData = ${JSON.stringify(data, null, 4)};\n\nexport const getRegions = (): PhilippineAddressOption[] => philippineAddressData.regions;\n\nexport const getProvincesByRegion = (regionCode?: string): PhilippineAddressOption[] => (regionCode ? philippineAddressData.provincesByRegion[regionCode] ?? [] : []);\n\nexport const getCitiesByProvince = (provinceCode?: string): PhilippineAddressOption[] => (provinceCode ? philippineAddressData.citiesByProvince[provinceCode] ?? [] : []);\n\nexport const getCitiesByRegion = (regionCode?: string): PhilippineAddressOption[] => (regionCode ? philippineAddressData.citiesByRegion[regionCode] ?? [] : []);\n\nexport const getBarangaysByCity = (cityCode?: string): PhilippineAddressOption[] => (cityCode ? philippineAddressData.barangaysByCity[cityCode] ?? [] : []);\n`;

    fs.mkdirSync(path.dirname(OUT_FILE), { recursive: true });
    fs.writeFileSync(OUT_FILE, body, 'utf8');

    const totalProvinces = Object.values(provincesByRegion).reduce((sum, items) => sum + items.length, 0);
    const totalCities = Object.values(citiesByProvince).reduce((sum, items) => sum + items.length, 0)
        + Object.values(citiesByRegion).reduce((sum, items) => sum + items.length, 0);
    const totalBarangays = Object.values(barangaysByCity).reduce((sum, items) => sum + items.length, 0);

    console.log(`Wrote ${OUT_FILE}`);
    console.log(JSON.stringify({
        regions: regions.length,
        provinces: totalProvinces,
        citiesAndMunicipalities: totalCities,
        barangays: totalBarangays,
    }, null, 2));
}

main().catch((error) => {
    console.error(error);
    process.exit(1);
});
