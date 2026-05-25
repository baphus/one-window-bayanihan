import type { AddressParts, ReferralStatus } from '@/types';

export function formatDisplayDateTime(iso: string): string {
    return new Intl.DateTimeFormat('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
    }).format(new Date(iso));
}

export function formatDisplayTime(iso: string): string {
    return new Intl.DateTimeFormat('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
    }).format(new Date(iso));
}

export function formatDisplayDate(iso: string): string {
    return new Intl.DateTimeFormat('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(iso));
}

export function formatAddressParts(address: AddressParts | null | undefined): string {
    if (!address) {
        return '-';
    }

    const chunks = [
        address.streetAddress,
        address.barangayName,
        address.municipalityName,
        address.provinceName,
        address.regionName,
    ]
        .map((part) => part.trim())
        .filter((part) => part.length > 0);

    return chunks.length > 0 ? chunks.join(', ') : '-';
}

export function createEmptyAddressParts(): AddressParts {
    return {
        regionCode: '',
        regionName: '',
        provinceCode: '',
        provinceName: '',
        municipalityCode: '',
        municipalityName: '',
        barangayCode: '',
        barangayName: '',
        streetAddress: '',
    };
}

export function toCaseHealthStatus(status: ReferralStatus): 'OPEN' | 'CLOSED' {
    return status === 'COMPLETED' || status === 'REJECTED' ? 'CLOSED' : 'OPEN';
}

export function getAllowedReferralStatusTransitions(
    currentStatus: ReferralStatus,
): ReferralStatus[] {
    if (currentStatus === 'PENDING') {
        return ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE', 'REJECTED'];
    }

    if (currentStatus === 'PROCESSING') {
        return ['PROCESSING', 'FOR_COMPLIANCE', 'COMPLETED', 'REJECTED'];
    }

    if (currentStatus === 'FOR_COMPLIANCE') {
        return ['FOR_COMPLIANCE', 'PROCESSING', 'COMPLETED', 'REJECTED'];
    }

    return [currentStatus];
}

export function isValidReferralStatusTransition(
    currentStatus: ReferralStatus,
    nextStatus: ReferralStatus,
): boolean {
    return getAllowedReferralStatusTransitions(currentStatus).includes(nextStatus);
}

export function isDirty<T extends Record<string, unknown>>(initial: T, current: T): boolean {
  return (Object.keys(initial) as (keyof T)[]).some(key => initial[key] !== current[key]);
}

export function getGoogleMapsEmbedUrl(locationQuery: string): string {
    return `https://www.google.com/maps?q=${encodeURIComponent(locationQuery)}&output=embed`;
}

export function getGoogleMapsPlaceUrl(locationQuery: string): string {
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(locationQuery)}`;
}

const NAME_SUFFIXES = new Set(['JR', 'SR', 'II', 'III', 'IV', 'V']);

function normalizeToken(value: string): string {
    return value.replace(/\./g, '').trim().toUpperCase();
}

function shouldFormatName(name: string): boolean {
    if (name.includes(',')) {
        return true;
    }

    const tokens = name
        .trim()
        .split(/\s+/)
        .filter(Boolean);

    if (tokens.length >= 3) {
        return true;
    }

    return tokens.some((token) => token.replace(/\./g, '').length === 1);
}

type ParsedPersonName = {
    firstName: string;
    middleInitial: string;
    surname: string;
    suffix: string;
};

export function parsePersonName(name: string): ParsedPersonName {
    const normalized = name.trim().replace(/\s+/g, ' ');

    if (!normalized) {
        return { firstName: '', middleInitial: '', surname: '', suffix: '' };
    }

    if (normalized.includes(',')) {
        const [rawSurname, rawGiven = '', rawSuffix = ''] = normalized
            .split(',')
            .map((part) => part.trim());
        const givenTokens = rawGiven.split(/\s+/).filter(Boolean);

        const firstName = givenTokens[0] ?? '';
        const middleToken = givenTokens.length > 1 ? givenTokens[givenTokens.length - 1] : '';
        const middleInitial = middleToken
            ? middleToken.replace(/\./g, '').charAt(0).toUpperCase()
            : '';
        const suffix = rawSuffix || '';

        return {
            firstName,
            middleInitial,
            surname: rawSurname,
            suffix,
        };
    }

    const tokens = normalized.split(/\s+/).filter(Boolean);

    if (tokens.length === 1) {
        return { firstName: tokens[0], middleInitial: '', surname: '', suffix: '' };
    }

    const lastToken = tokens[tokens.length - 1];
    const maybeSuffix = normalizeToken(lastToken);
    const hasSuffix = NAME_SUFFIXES.has(maybeSuffix);

    const suffix = hasSuffix ? lastToken.replace(/\./g, '') : '';
    const coreTokens = hasSuffix ? tokens.slice(0, -1) : tokens;

    if (coreTokens.length === 2) {
        return {
            firstName: coreTokens[0],
            middleInitial: '',
            surname: coreTokens[1],
            suffix,
        };
    }

    return {
        firstName: coreTokens[0] ?? '',
        middleInitial: coreTokens[1]
            ? coreTokens[1].replace(/\./g, '').charAt(0).toUpperCase()
            : '',
        surname: coreTokens.length > 2 ? coreTokens.slice(2).join(' ') : '',
        suffix,
    };
}

export function formatPersonName(name: string): string {
    if (!name.trim()) {
        return '';
    }

    if (!shouldFormatName(name)) {
        return name.trim();
    }

    const parts = parsePersonName(name);
    const given = parts.middleInitial
        ? `${parts.firstName} ${parts.middleInitial}.`
        : parts.firstName;
    const suffix = parts.suffix ? ` ${parts.suffix}` : '';

    return `${parts.surname}, ${given}${suffix}`;
}
