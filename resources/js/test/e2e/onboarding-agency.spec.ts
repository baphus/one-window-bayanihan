import { test, expect, type Page } from '@playwright/test';
import { runTinker } from './helpers/artisan';
const AGENCY_EMAIL = 'owwa@bayanihan.gov.ph';

/**
 * Enable debug OTP mode and reset onboarding state for the test user.
 */
function setupTestUser() {
    runTinker("\\App\\Models\\SystemSetting::setValue('debug_otp_enabled', true)", 'enable agency debug OTP');
    runTinker(`\\App\\Models\\User::where('email', '${AGENCY_EMAIL}')->update(['onboarding_completed_at' => null, 'onboarding_step' => null])`, 'reset agency onboarding', 10000);
}

/**
 * Log in as AGENCY user using the role selector (auto-fills credentials)
 * and the debug OTP auto-fill flow.
 */
async function loginAsAgency(page: Page) {
    await page.goto('/login');

    // Fill the seeded test credentials directly
    await page.fill('input[type="email"]', AGENCY_EMAIL);
    await page.fill('input[type="password"]', 'P@ssw0rd!');

    // Submit login form
    await page.getByRole('button', { name: 'Sign In' }).click();

    // Wait for OTP step
    await page.waitForSelector('text=Verify Your Identity', { timeout: 10000 });

    // Wait for debug OTP banner and manually fill inputs
    const debugOtpLocator = page.locator('text=Debug Mode');
    try {
        await debugOtpLocator.waitFor({ state: 'visible', timeout: 10000 });
        const debugText = await debugOtpLocator.textContent();
        const otpMatch = debugText?.match(/OTP:\s*(\d{6})/);
        if (otpMatch) {
            const otp = otpMatch[1];
            const inputs = page.locator('input[inputmode="numeric"]');
            for (let i = 0; i < 6; i++) {
                await inputs.nth(i).fill(otp[i]);
            }
        }
    } catch {
        // Debug banner not visible — rely on React auto-fill
    }

    // Wait for all 6 OTP inputs to be filled
    await page.waitForFunction(() => {
        const inputs = document.querySelectorAll('input[inputmode="numeric"]');
        return (
            inputs.length === 6 &&
            Array.from(inputs).every((i) => (i instanceof HTMLInputElement ? i.value !== '' : false))
        );
    }, { timeout: 15000 });

    // Click "Verify & Continue"
    await page.getByRole('button', { name: 'Verify & Continue' }).click();

    // Wait for dashboard
    await page.waitForURL('**/dashboard', { timeout: 15000 });
}

// ────────────────────────────────────────────────────────────────────────────
// Suite setup
// ────────────────────────────────────────────────────────────────────────────
test.beforeAll(() => {
    setupTestUser();
});

test.beforeEach(() => {
    test.setTimeout(120000);
    setupTestUser();
});

test.describe('AGENCY Onboarding', () => {
    test('Login as AGENCY → WelcomeModal appears → tour steps are AGENCY-specific', async ({ page }) => {
        await loginAsAgency(page);

        // Assert WelcomeModal is visible
        await expect(
            page.getByText('Welcome to One Window Bayanihan!')
        ).toBeVisible({ timeout: 5000 });

        // Start the tour — wait for button to be stable (avoid detach during re-render)
        const startTourBtn = page.getByRole('button', { name: 'Start Tour' });
        await startTourBtn.waitFor({ state: 'visible', timeout: 10000 });
        await page.waitForTimeout(500); // Allow any pending re-renders to settle
        await startTourBtn.click({ timeout: 10000 });

        // Wait for Driver.js popover to appear
        await page.waitForSelector('.driver-popover', { timeout: 5000 });

        // The first AGENCY tour step is the welcome/orientation step with a
        // description referencing agency-specific content
        await expect(page.locator('.driver-popover-title')).toContainText('Welcome');

        // Assert the popover description is AGENCY-specific
        await expect(page.locator('.driver-popover-description')).toContainText('agency');

        // The targeted first-step element has data-tour="dashboard-header"
        await expect(page.locator('[data-tour="dashboard-header"]')).toBeAttached();
    });
});
