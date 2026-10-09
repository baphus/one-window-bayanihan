import { render, screen, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';
import AddressDropdowns from '../AddressDropdowns';

const state = { servedRegions: ['0700000000', '1800000000'] };

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { addresses: { served_regions: state.servedRegions } } }),
}));

const baseValues = {
    region: '',
    province: '',
    city_municipality: '',
    barangay: '',
    street: '',
};

function renderDropdowns(overrides = {}) {
    const props = {
        values: { ...baseValues, ...(overrides.values ?? {}) },
        onChange: overrides.onChange ?? (() => {}),
        errors: overrides.errors ?? {},
    };

    render(<AddressDropdowns {...props} />);

    return props;
}

describe('AddressDropdowns region scope', () => {
    it('only lists the served regions in the region dropdown', () => {
        renderDropdowns();

        const regionInput = screen.getByPlaceholderText('Select region...');
        fireEvent.focus(regionInput);

        expect(screen.getByText('Negros Island Region')).toBeInTheDocument();
        expect(screen.getByText('Region VII (Central Visayas)')).toBeInTheDocument();
    });

    it('never offers regions outside the served scope', () => {
        renderDropdowns();

        fireEvent.focus(screen.getByPlaceholderText('Select region...'));

        // Spot-check regions that must remain unavailable.
        expect(screen.queryByText('National Capital Region (NCR)')).not.toBeInTheDocument();
        expect(screen.queryByText('Region VI (Western Visayas)')).not.toBeInTheDocument();
        expect(screen.queryByText('Region VIII (Eastern Visayas)')).not.toBeInTheDocument();
    });

    it('lists every region when served_regions is empty (unrestricted scope)', () => {
        state.servedRegions = [];

        try {
            renderDropdowns();
            fireEvent.focus(screen.getByPlaceholderText('Select region...'));

            expect(screen.getByText('Negros Island Region')).toBeInTheDocument();
            expect(screen.getByText('Region VII (Central Visayas)')).toBeInTheDocument();
            expect(screen.getByText('National Capital Region (NCR)')).toBeInTheDocument();
            expect(screen.getByText('Region VI (Western Visayas)')).toBeInTheDocument();
        } finally {
            state.servedRegions = ['0700000000', '1800000000'];
        }
    });

    it('still cascades provinces for a served region', () => {
        renderDropdowns({ values: { region: '0700000000' } });

        const provinceInput = screen.getByPlaceholderText('Select province...');
        expect(provinceInput).toBeEnabled();
        fireEvent.focus(provinceInput);

        // Region VII covers Cebu and Bohol in this dataset; confirms the
        // filtered code still resolves to its provinces.
        expect(screen.getByText('Cebu')).toBeInTheDocument();
        expect(screen.getByText('Bohol')).toBeInTheDocument();
    });
});