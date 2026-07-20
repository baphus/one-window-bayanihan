import { describe, it, expect } from 'vitest';
import { caseManagerTour } from '../configs/caseManager';
import { agencyTour } from '../configs/agency';
import { adminTour } from '../configs/admin';
import { getTourConfig } from '../index';

describe('Tour configs — role-specific structure', () => {
    it('getTourConfig returns correct config per role', () => {
        expect(getTourConfig('CASE_MANAGER')).toBe(caseManagerTour);
        expect(getTourConfig('AGENCY')).toBe(agencyTour);
        expect(getTourConfig('ADMIN')).toBe(adminTour);
        expect(getTourConfig('UNKNOWN')).toBeNull();
    });

    it('all configs target data-tour="dashboard-header" in first step', () => {
        for (const config of [caseManagerTour, agencyTour, adminTour]) {
            const firstStep = config.pages[0].steps[0];
            expect(firstStep.element).toBe('[data-tour="dashboard-header"]');
            expect(firstStep.title).toContain('Welcome');
        }
    });

    it('CASE_MANAGER tour starts on dashboard and goes to cases', () => {
        expect(caseManagerTour.role).toBe('CASE_MANAGER');
        expect(caseManagerTour.pages).toHaveLength(2);
        expect(caseManagerTour.pages[0].route).toBe('dashboard');
        expect(caseManagerTour.pages[1].route).toBe('cases.index');
        // Description references work queue
        expect(caseManagerTour.pages[0].steps[1].description).toMatch(/dispatch board|work queue/i);
    });

    it('AGENCY tour starts on dashboard, visits services, then goes to referrals', () => {
        expect(agencyTour.role).toBe('AGENCY');
        expect(agencyTour.pages).toHaveLength(3);
        expect(agencyTour.pages[0].route).toBe('dashboard');
        expect(agencyTour.pages[1].route).toBe('agency.services.index');
        expect(agencyTour.pages[2].route).toBe('referrals.index');
        // Description references agency-specific content
        expect(agencyTour.pages[0].steps[0].description).toMatch(/agency/i);
    });

    it('ADMIN tour starts on dashboard and goes to users', () => {
        expect(adminTour.role).toBe('ADMIN');
        expect(adminTour.pages).toHaveLength(2);
        expect(adminTour.pages[0].route).toBe('dashboard');
        expect(adminTour.pages[1].route).toBe('admin.users.index');
        // Description references admin/system-wide content
        expect(adminTour.pages[0].steps[0].description).toMatch(/admin|system/i);
    });

    it('all tour steps have required fields (element, title, description)', () => {
        for (const config of [caseManagerTour, agencyTour, adminTour]) {
            for (const page of config.pages) {
                for (const step of page.steps) {
                    expect(step.element, `Missing element in ${config.role}/${page.route}`).toBeTruthy();
                    expect(step.title, `Missing title in ${config.role}/${page.route}`).toBeTruthy();
                    expect(step.description, `Missing description in ${config.role}/${page.route}`).toBeTruthy();
                    expect(step.element).toMatch(/^\[data-tour="[a-z0-9-]+"\]$/);
                }
            }
        }
    });

    it('no duplicate step elements within the same page', () => {
        for (const config of [caseManagerTour, agencyTour, adminTour]) {
            for (const page of config.pages) {
                const elements = page.steps.map((s) => s.element);
                const unique = new Set(elements);
                expect(elements.length, `Duplicate elements in ${config.role}/${page.route}`).toBe(unique.size);
            }
        }
    });

    it('each tour has a page-guide-button step teaching about page guides', () => {
        for (const config of [caseManagerTour, agencyTour, adminTour]) {
            const allSteps = config.pages.flatMap((p) => p.steps);
            const guideStep = allSteps.find((s) => s.element === '[data-tour="page-guide-button"]');
            expect(guideStep, `${config.role} should reference page-guide-button`).toBeTruthy();
            expect(guideStep.description).toMatch(/\?|guide/i);
        }
    });
});
