import { test, expect, type Page } from '@playwright/test';
import { runTinker } from './helpers/artisan';
const CM_EMAIL = 'case@bayanihan.gov.ph';

/**
 * Enable debug OTP mode and reset onboarding state for the test user.
 */
function setupTestUser() {
    runTinker("\\App\\Models\\SystemSetting::setValue('debug_otp_enabled', true)", 'enable case manager debug OTP');
    runTinker(`\\App\\Models\\User::where('email', '${CM_EMAIL}')->update(['onboarding_completed_at' => null, 'onboarding_step' => null])`, 'reset case manager onboarding', 10000);
}

/**
 * Log in as CASE_MANAGER using the role selector (auto-fills credentials)
 * and the auto-filled debug OTP flow.
 */
async function loginAsCaseManager(page: Page) {
    await page.goto('/login');

    // Fill the seeded test credentials directly
    await page.fill('input[type="email"]', CM_EMAIL);
    await page.fill('input[type="password"]', 'P@ssw0rd!');

    // Submit the login form
    await page.getByRole('button', { name: 'Sign In' }).click();

    // Wait for the OTP verification screen
    await page.waitForSelector('text=Verify Your Identity', { timeout: 10000 });

    // Read the debug_otp from the visible "Debug Mode — OTP: XXXXXX" text
    // and manually fill each input. This is more robust than relying on the
    // React auto-fill effect which can suffer timing issues on re-login
    // (e.g. the Skip Tour test calls loginAsCaseManager twice).
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

    // Wait for all 6 OTP inputs to be filled (either by manual fill above or auto-fill)
    await page.waitForFunction(() => {
        const inputs = document.querySelectorAll('input[inputmode="numeric"]');
        return (
            inputs.length === 6 &&
            Array.from(inputs).every((i) => (i instanceof HTMLInputElement ? i.value !== '' : false))
        );
    }, { timeout: 5000 });

    // Click "Verify & Continue"
    await page.getByRole('button', { name: 'Verify & Continue' }).click();

    // Wait for redirect to dashboard
    await page.waitForURL('**/dashboard', { timeout: 15000 });
}

/**
 * Log out via the sidebar button.
 */
async function logout(page: Page) {
    await page.locator('button[title="Log Out"]').click();
    await page.waitForURL('**/', { timeout: 10000 });
}

// ────────────────────────────────────────────────────────────────────────────
// Suite setup — runs once before any test in this file
// ────────────────────────────────────────────────────────────────────────────
test.beforeAll(() => {
    setupTestUser();
});

// ────────────────────────────────────────────────────────────────────────────
// Each test gets its own BrowserContext (fullyParallel: true) so no shared
// cookies/session. We reset DB state in beforeEach to avoid test pollution.
// ────────────────────────────────────────────────────────────────────────────
test.beforeEach(() => {
    test.setTimeout(120000);
    setupTestUser();
});

test.describe('CASE_MANAGER Onboarding', () => {
    test.describe.configure({ mode: 'serial' });
    test('Login as CASE_MANAGER → WelcomeModal appears → click Start Tour → first popover visible', async ({ page }) => {
        await loginAsCaseManager(page);

        // Assert WelcomeModal is visible
        await expect(
            page.getByText('Welcome to One Window Bayanihan!')
        ).toBeVisible({ timeout: 5000 });

        // Click "Start Tour"
        await page.getByRole('button', { name: 'Start Tour' }).click();

        // Wait for Driver.js popover to appear
        await page.waitForSelector('.driver-popover', { timeout: 5000 });

        // Assert the popover title matches the first CM tour step ("Welcome")
        await expect(page.locator('.driver-popover-title')).toContainText('Welcome');

        // Assert the popover description references case-manager–specific content
        await expect(page.locator('.driver-popover-description')).toContainText('work queue');
    });

    test('Login as CASE_MANAGER → click Skip Tour → logout → login → WelcomeModal does not reappear', async ({ page }) => {
        await loginAsCaseManager(page);

        // WelcomeModal should be visible
        await expect(
            page.getByText('Welcome to One Window Bayanihan!')
        ).toBeVisible({ timeout: 5000 });

        // Click "Skip Tour" — this calls POST /onboarding/skip which sets
        // onboarding_completed_at in the database
        await page.getByRole('button', { name: 'Skip Tour' }).click();

        // WelcomeModal should disappear
        await expect(
            page.getByText('Welcome to One Window Bayanihan!')
        ).not.toBeVisible({ timeout: 5000 });

        // Logout
        await logout(page);

        // Login again
        await loginAsCaseManager(page);

        // WelcomeModal should NOT reappear (onboarding was permanently skipped)
        await expect(
            page.getByText('Welcome to One Window Bayanihan!')
        ).not.toBeVisible({ timeout: 5000 });
    });

    test('Login as CASE_MANAGER → click Remind Me Later → refresh → WelcomeModal stays hidden → clear sessionStorage → reappears', async ({ page }) => {
        await loginAsCaseManager(page);

        // WelcomeModal should be visible
        await expect(
            page.getByText('Welcome to One Window Bayanihan!')
        ).toBeVisible({ timeout: 5000 });

        // Click "Remind Me Later" — sets sessionStorage('onboarding_dismissed', 'true')
        await page.getByRole('button', { name: 'Remind Me Later' }).click();

        // WelcomeModal should disappear
        await expect(
            page.getByText('Welcome to One Window Bayanihan!')
        ).not.toBeVisible({ timeout: 5000 });

        // Reload the page — sessionStorage persists across same-tab refreshes
        await page.reload();

        // WelcomeModal should still NOT appear because sessionStorage still has
        // 'onboarding_dismissed' = 'true' (survives reload within same tab)
        await expect(
            page.getByText('Welcome to One Window Bayanihan!')
        ).not.toBeVisible({ timeout: 5000 });

        // Clear sessionStorage to simulate a new tab/session
        await page.evaluate(() => sessionStorage.removeItem('onboarding_dismissed'));

        // Reload again
        await page.reload();

        // WelcomeModal should reappear because onboarding is still required in the DB
        // and sessionStorage was cleared
        await expect(
            page.getByText('Welcome to One Window Bayanihan!')
        ).toBeVisible({ timeout: 5000 });
    });

    test('Welcome tour auto-navigates from Dashboard to Cases and completes', async ({ page }) => {
        await loginAsCaseManager(page);

        await page.getByRole('button', { name: 'Start Tour' }).click();
        await page.waitForSelector('.driver-popover', { timeout: 5000 });

        // Advance through the dashboard steps. The final step's button reads
        // "Next: Cases →" and clicking it navigates to the Cases page.
        // Missing-anchor steps are skipped, so the count varies — loop until
        // the URL changes (bounded to avoid infinite loops).
        for (let i = 0; i < 10 && !page.url().includes('/cases'); i++) {
            await page.locator('.driver-popover-next-btn').click();
            await page.waitForTimeout(500);
        }

        await page.waitForURL('**/cases', { timeout: 10000 });

        // The tour continues on the Cases page automatically
        await page.waitForSelector('.driver-popover', { timeout: 10000 });
        await expect(page.locator('.driver-popover-title')).toContainText('Case');

        // Finish the remaining steps; the last button reads "Done"
        for (let i = 0; i < 10; i++) {
            const btn = page.locator('.driver-popover-next-btn');
            if ((await btn.count()) === 0) break;
            await btn.click();
            await page.waitForTimeout(500);
        }

        // Completion toast confirms POST /onboarding/complete succeeded
        await expect(page.getByText('Tour complete!', { exact: false })).toBeVisible({ timeout: 10000 });
    });

    test('Interrupted tour offers Resume Tour after reload', async ({ page }) => {
        await loginAsCaseManager(page);

        await page.getByRole('button', { name: 'Start Tour' }).click();
        await page.waitForSelector('.driver-popover', { timeout: 5000 });

        // Advance a couple of steps so a step key is persisted
        await page.locator('.driver-popover-next-btn').click();
        await page.waitForTimeout(500);
        await page.locator('.driver-popover-next-btn').click();
        await page.waitForTimeout(700);

        // Simulate an interruption: reload the page mid-tour
        await page.reload();

        // The welcome modal reappears offering to resume
        await expect(page.getByRole('button', { name: 'Resume Tour' })).toBeVisible({ timeout: 10000 });
        await expect(page.getByRole('button', { name: 'Restart' })).toBeVisible();

        // Resuming continues the tour with an overlay on the saved page
        await page.getByRole('button', { name: 'Resume Tour' }).click();
        await page.waitForSelector('.driver-popover', { timeout: 10000 });
    });
});
