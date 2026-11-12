import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import PresenceRoster from '../PresenceRoster';
import { canShowCasePresence, canShowReferralPresence } from '../presencePolicy';

vi.mock('@/Hooks/useLiveViewers', () => ({ default: () => [
    { id: 'self', name: 'Maria Santos' }, { id: 'other', name: 'Juan Dela Cruz' },
] }));

const roles = { ADMIN: 'ADMIN', CASE_MANAGER: 'CASE_MANAGER', AGENCY: 'AGENCY', OFW: 'OFW' };

describe('PresenceRoster', () => {
    it('labels roster, names viewers, and calls self You', () => {
        render(<PresenceRoster url="/cases/1/presence" currentUserId="self" />);
        expect(screen.getByText('Currently viewing')).toBeTruthy();
        expect(screen.getAllByLabelText('You')).toHaveLength(2);
        expect(screen.getAllByLabelText('Juan Dela Cruz')).toHaveLength(2);
    });

    it('allows only staff case roles and agency-matched staff referral roles', () => {
        expect(canShowCasePresence(roles.CASE_MANAGER, roles)).toBe(true);
        expect(canShowCasePresence(roles.ADMIN, roles)).toBe(true);
        expect(canShowCasePresence(roles.AGENCY, roles)).toBe(true);
        expect(canShowCasePresence(roles.OFW, roles)).toBe(false);
        expect(canShowReferralPresence({ role: roles.ADMIN, agcy_id: 'dmw' }, { agcy_id: 'dmw' }, roles)).toBe(true);
        expect(canShowReferralPresence({ role: roles.CASE_MANAGER, agcy_id: 'dmw' }, { agcy_id: 'dmw' }, roles)).toBe(true);
        expect(canShowReferralPresence({ role: roles.AGENCY, agcy_id: 'dmw' }, { agcy_id: 'dmw' }, roles)).toBe(true);
        expect(canShowReferralPresence({ role: roles.CASE_MANAGER, agcy_id: 'other' }, { agcy_id: 'dmw' }, roles)).toBe(false);
        expect(canShowReferralPresence({ role: roles.OFW, agcy_id: 'dmw' }, { agcy_id: 'dmw' }, roles)).toBe(false);
    });
});
