import { test, expect, type Page } from '@playwright/test';
import { execSync } from 'child_process';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const PROJECT_ROOT = path.resolve(__dirname, '../../../..');
const CM_EMAIL = 'case@bayanihan.gov.ph';

/**
 * Enable debug OTP, mark onboarding complete (so no WelcomeModal), and clear
 * page-guide/checklist state so the nudge and checklist behave first-visit.
 */
function setupTestUser() {
    try {
        execSync(
            'php artisan tinker --execute="\\App\\Models\\SystemSetting::setValue(\'debug_otp_enabled\', true)"',
            { cwd: PROJECT_ROOT, timeout: 15000 }
        );
        execSync(
            `php artisan tinker --execute="\\App\\Models\\User::where(\'email\', \'${CM_EMAIL}\')->update([\'onboarding_completed_at\' => now(), \'onboarding_step\' => null, \'seen_page_guides\' => null, \'checklist_progress\' => null])"`,
            { cwd: PROJECT_ROOT, timeout: 10000 }
        );
    } catch (e) {
        console.warn('Setup warning:', e.message);
    }
}

async function loginAsCaseManager(page: Page) {
    await page.goto('/login');

    // Fill the seeded test credentials directly
    await page.fill('input[type="email"]', CM_EMAIL);
    await page.fill('input[type="password"]', 'P@ssw0rd!');

    await page.getByRole('button', { name: 'Sign In' }).click();
    await page.waitForSelector('text=Verify Your Identity', { timeout: 10000 });

    const debugOtpLocator = page.locator('text=Debug Mode');
    if (await debugOtpLocator.count() > 0) {
        const debugText = await debugOtpLocator.textContent({ timeout: 3000 });
        const otpMatch = debugText?.match(/OTP:\s*(\d{6})/);
        if (otpMatch) {
            const otp = otpMatch[1];
            const inputs = page.locator('input[inputmode="numeric"]');
            for (let i = 0; i < otp.length; i++) {
                await inputs.nth(i).press(otp[i]);
            }
        }
    }

    await page.waitForFunction(() => {
        const inputs = document.querySelectorAll('input[inputmode="numeric"]');
        return (
            inputs.length === 6 &&
            Array.from(inputs).every((i) => (i instanceof HTMLInputElement ? i.value !== '' : false))
        );
    }, { timeout: 5000 });

    await page.getByRole('button', { name: 'Verify & Continue' }).click();
    await page.waitForURL('**/dashboard', { timeout: 15000 });
}

// All tests in this file share one seeded user whose onboarding state is
// reset in beforeEach — they must never run in parallel workers.
test.describe.configure({ mode: 'serial' });

test.beforeEach(() => {
    test.setTimeout(120000);
    setupTestUser();
});

test.describe('Page guides', () => {

    test('[?] launcher pulses on first visit, opens the guide, and stops pulsing once seen', async ({ page }) => {
        await loginAsCaseManager(page);

        // Dashboard has a registered guide → launcher visible and pulsing (unseen)
        const launcher = page.locator('[data-tour="page-guide-button"]');
        await expect(launcher).toBeVisible({ timeout: 5000 });
        await expect(launcher).toHaveClass(/owb-guide-nudge/);

        // Open the guide
        await launcher.click();
        await page.waitForSelector('.driver-popover', { timeout: 5000 });
        await expect(page.locator('.driver-popover-title')).toContainText('Daily Start');

        // Close the guide
        await page.locator('.driver-popover-close-btn').click();
        await expect(page.locator('.driver-popover')).not.toBeVisible({ timeout: 5000 });

        // Seen → no more pulse
        await expect(launcher).not.toHaveClass(/owb-guide-nudge/);

        // Persisted: still no pulse after a full reload
        await page.reload();
        await expect(launcher).toBeVisible({ timeout: 10000 });
        await expect(launcher).not.toHaveClass(/owb-guide-nudge/);
    });

    test('Guide final step deep-links to the Helpdesk article', async ({ page }) => {
        await loginAsCaseManager(page);

        // Reports has a guide mapped to the using-reports-analytics article
        await page.goto('/reports');
        const launcher = page.locator('[data-tour="page-guide-button"]');
        await expect(launcher).toBeVisible({ timeout: 10000 });
        await launcher.click();
        await page.waitForSelector('.driver-popover', { timeout: 5000 });

        // Walk to the final step: wait for the read-more link on each step
        // before advancing, so a slow popover re-render can't cause an extra
        // click past the final step (which would close the guide).
        let found = false;
        for (let i = 0; i < 12 && !found; i++) {
            try {
                await page.waitForSelector('.driver-popover-readmore a', { timeout: 1500 });
                found = true;
            } catch {
                await page.locator('.driver-popover-next-btn').click();
            }
        }

        const readmore = page.locator('.driver-popover-readmore a');
        await expect(readmore).toBeVisible();
        await expect(readmore).toHaveAttribute('href', /using-reports-analytics/);
    });
});

test.describe('Getting-started checklist', () => {
    test.describe.configure({ mode: 'serial' });

    test('Checklist shows on dashboard, visit item completes, dismiss persists', async ({ page }) => {
        await loginAsCaseManager(page);

        // Checklist card is visible with CM items
        const card = page.locator('[data-tour="getting-started-checklist"]');
        await expect(card).toBeVisible({ timeout: 5000 });
        await expect(card.getByText('Create your first case')).toBeVisible();
        await expect(card.getByText('0 of 4', { exact: false })).toBeVisible();

        // Visiting Reports marks the visit-type item
        await page.goto('/reports');
        await page.waitForTimeout(1000); // allow the mark request to land
        await page.goto('/dashboard');

        await expect(card.getByText('1 of 4', { exact: false })).toBeVisible({ timeout: 10000 });

        // Dismiss hides the card...
        await card.getByRole('button', { name: 'Dismiss' }).click();
        await expect(card).not.toBeVisible({ timeout: 5000 });

        // ...and stays hidden after reload (persisted server-side)
        await page.reload();
        await page.waitForSelector('[data-tour="dashboard-header"]', { timeout: 10000 });
        await expect(page.locator('[data-tour="getting-started-checklist"]')).not.toBeVisible();
    });
});
