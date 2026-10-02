# PSGC Addresses

> **Version:** 1.1.0 | **Updated:** 2026-10-02 | **Source:** `app/Services/PhilippineAddressService.php`, `app/Services/AddressNameResolver.php`, `resources/js/data/philippine-addresses.ts`, `scripts/sync-philippine-addresses.cjs`, `package.json`

## Overview

Philippine Standard Geographic Code (PSGC) address data is **stateless**: no address table exists in PostgreSQL (the `philippine_addresses` table was dropped in `2026_07_08_000001`). The reference dataset lives in a generated TypeScript file, is read directly by the frontend, and is persisted on client/case records as resolved display **names** (codes are converted to names on write).

## Endpoints

None. The former five public lookup endpoints (`GET /api/address/regions|provinces|cities|barangays|resolve`, served by `PhilippineAddressController` under the `address-lookup` limiter) were removed on 2026-10-02: no frontend code called them — the OFW intake wizard and all dropdowns read the generated file directly. Server-side code resolution still runs through `PhilippineAddressService` (see Resolver Services), which parses the same file.

## Throttle

No address limiter remains. The retired `address-lookup` limiter (**60 requests/minute**, keyed by authenticated user id or IP) was removed with the endpoints.

## Sync Command

```bash
npm run addresses:sync
```

Runs `scripts/sync-philippine-addresses.cjs`, which fetches the full hierarchy from the public PSGC API (`https://psgc.cloud/api`), sorts by name, and regenerates `resources/js/data/philippine-addresses.ts` (`philippineAddressData`: `regions`, `provincesByRegion`, `citiesByProvince`, `citiesByRegion`, `barangaysByCity`, plus frontend getters). The script self-throttles (~1.15 s between requests) with retry/backoff on HTTP 429. Province-less regions (e.g. NCR) keep cities/municipalities directly under the region (`citiesByRegion`); elsewhere barangays are grouped under their parent city via the `XXXXXXXX000` code prefix. Do not edit records by hand — regenerate.

## Resolver Services

- **`App\Services\PhilippineAddressService`** — server-side reader of the generated file. `getRegions/getProvinces/getCities/getBarangays` serve internal callers (e.g. `CaseService`); `resolveNames(codes)` maps codes to names; `resolveAddressToCodes(address)` walks region → province → city → barangay by case-insensitive name match (region names normalized to their parenthesized label, e.g. `Region VII (Central Visayas)` ≡ `Central Visayas`) and returns PSGC codes, stopping at the first unmatched level.
- **`App\Services\AddressNameResolver`** — lightweight code→name helper. `resolve(code)` returns the display name (passthrough for unknown values, `''` for null/empty); `format(street, barangay, municipality, province, region)` joins the resolved parts. Used by exports, reports, tracking, and case display.

## Stateless Design

- **No address tables.** Reads come from the generated file (parsed/cached in memory per request); nothing is queried from PostgreSQL.
- **Names are stored, not codes.** `client_addresses` and `next_of_kin` keep region/province/city/barangay as display-name strings. Migrations `2026_06_21_112449_convert_address_codes_to_names` and `2026_07_27_000002_backfill_address_codes_to_names` converted legacy stored codes to names, so historical rows match the current format.
- **Operational consequence:** a stale generated file only affects lookups/display (regenerate with `npm run addresses:sync`); stored addresses remain human-readable without any join.
