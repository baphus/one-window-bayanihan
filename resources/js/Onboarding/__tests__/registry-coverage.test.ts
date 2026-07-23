import { describe, it, expect } from 'vitest';
import { pageGuides, getPageGuide, rolePageGuides } from '../registry';
import { navByRole } from '@/Components/AppSidebar';

/**
 * Sidebar href → Ziggy route name. Derived from `php artisan route:list`.
 * When a new sidebar destination is added, add its route name here AND a
 * guide in resources/js/Onboarding/registry.ts — this suite fails otherwise,
 * which is the enforcement mechanism for "every page has a guide".
 */
const HREF_TO_ROUTE: Record<string, string> = {
    '/dashboard': 'dashboard',
    '/notifications/page': 'notifications.page',
    '/cases': 'cases.index',
    '/cases/drafts': 'cases.drafts',
    '/cases/trash': 'cases.trash',
    '/clients': 'clients.index',
    '/referrals': 'referrals.index',
    '/overdue-referrals': 'overdue-referrals.index',
    '/stakeholders': 'stakeholders.index',
    '/reports': 'reports.index',
    '/audit-logs': 'audit-logs.index',
    '/help': 'helpdesk.index',
    '/services': 'agency.services.index',
    '/survey-forms': 'survey.forms.index',
    '/surveys': 'survey.responses.index',
    '/feedbacks': 'feedbacks.index',
    '/servqual-configs': 'servqual-configs.index',
    '/admin/agencies': 'admin.agencies.index',
    '/admin/services': 'admin.services.index',
    '/admin/users': 'admin.users.index',
    '/admin/system/logs': 'admin.system.logs',
    '/admin/system/email-logs': 'admin.system.email-logs.index',
    '/admin/case-statuses': 'admin.case-statuses.index',
    '/admin/case-categories': 'admin.case-categories.index',
    '/admin/case-issues': 'admin.case-issues.index',
    '/admin/data-export': 'admin.data-export.index',
    '/admin/system/maintenance': 'admin.system.maintenance',
    '/admin/system-settings': 'admin.system-settings.index',
    '/admin/system/security': 'admin.system.security',
    '/admin/system/active-sessions': 'admin.system.active-sessions',
};

function sidebarItems(): { role: string; name: string; href: string }[] {
    return Object.entries(navByRole).flatMap(([role, groups]) =>
        (groups as { items: { name: string; href: string }[] }[]).flatMap((group) =>
            group.items.map((item) => ({ role, name: item.name, href: item.href })),
        ),
    );
}

describe('page guide registry coverage', () => {
    it('every sidebar href has a route-name mapping', () => {
        const unmapped = sidebarItems().filter((item) => !HREF_TO_ROUTE[item.href]);
        expect(
            unmapped,
            `Unmapped sidebar hrefs (add to HREF_TO_ROUTE in this test): ${unmapped.map((i) => `${i.role}:${i.name} (${i.href})`).join(', ')}`,
        ).toEqual([]);
    });

    it('every navigable sidebar page has a registered guide', () => {
        const uncovered = sidebarItems().filter((item) => {
            const routeName = HREF_TO_ROUTE[item.href];
            return routeName && !pageGuides[routeName];
        });

        expect(
            uncovered,
            `Sidebar pages without a page guide in registry.ts: ${uncovered.map((i) => `${i.role}:${i.name} (${HREF_TO_ROUTE[i.href]})`).join(', ')}`,
        ).toEqual([]);
    });

    it('every guide has 3-6 steps with data-tour selectors and content', () => {
        for (const [routeName, guide] of Object.entries(pageGuides)) {
            expect(guide.title, `${routeName} needs a title`).toBeTruthy();
            expect(guide.steps.length, `${routeName} should have 3-6 steps (has ${guide.steps.length})`).toBeGreaterThanOrEqual(3);
            expect(guide.steps.length, `${routeName} should have 3-6 steps (has ${guide.steps.length})`).toBeLessThanOrEqual(6);
            for (const step of guide.steps) {
                expect(step.element, `${routeName}: step elements must target data-tour anchors`).toMatch(/^\[data-tour="[a-z0-9-]+"\]$/);
                expect(step.title, `${routeName}: every step needs a title`).toBeTruthy();
                expect(step.description, `${routeName}: every step needs a description`).toBeTruthy();
            }
        }
    });

    it('getPageGuide returns role-specific override when one exists', () => {
        const caseManagerGuide = getPageGuide('referrals.show', 'CASE_MANAGER');
        expect(caseManagerGuide).not.toBeNull();
        expect(caseManagerGuide!.title).toBe('Referral Detail');
        // Case-manager variant has 5 steps (agency-detail variant has 6 — actions differ)
        expect(caseManagerGuide!.steps.length).toBe(5);
    });

    it('getPageGuide without role returns the shared guide', () => {
        const sharedGuide = getPageGuide('referrals.show');
        expect(sharedGuide).not.toBeNull();
        expect(sharedGuide!.title).toBe('Referral Detail');
        // The shared (agency) variant has different step count
        expect(sharedGuide!.steps.length).toBeGreaterThanOrEqual(3);
    });

    it('getPageGuide falls back to shared guide when no role override exists', () => {
        const fallback = getPageGuide('cases.index', 'CASE_MANAGER');
        expect(fallback).not.toBeNull();
        expect(fallback!.title).toBe('Cases');
    });

    it('getPageGuide returns null for unknown routes', () => {
        expect(getPageGuide('nonexistent.route')).toBeNull();
        expect(getPageGuide('nonexistent.route', 'CASE_MANAGER')).toBeNull();
    });

    it('rolePageGuides entries use qualified keys with known route names', () => {
        for (const [key, guide] of Object.entries(rolePageGuides)) {
            const match = key.match(/^(\w+):(.+)$/);
            expect(match, `rolePageGuides key "${key}" must be <ROLE>:<route-name>`).not.toBeNull();
            const routeName = match![2];
            // The base guide should exist in pageGuides (ancestor context)
            expect(pageGuides[routeName], `role override "${key}" has no matching base guide for route "${routeName}"`).toBeDefined();
            expect(guide.steps.length).toBeGreaterThanOrEqual(3);
            expect(guide.steps.length).toBeLessThanOrEqual(6);
        }
    });
});
