import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const PROJECT_ROOT = path.resolve(__dirname, '../../../..');
const ADMIN_EMAIL = 'admin@bayanihan.gov.ph';

/**
 * Enable debug OTP mode and reset onboarding state for the test user.
 */
function setupTestUser() {
    try {
        execSync(
            'php artisan tinker --execute="\\App\\Models\\SystemSetting::setValue(\'debug_otp_enabled\', true)"',
            { cwd: PROJECT_ROOT, timeout: 15000 }
        );
        execSync(
            `php artisan tinker --execute="\\App\\Models\\User::where(\'email\', \'${ADMIN_EMAIL}\')->update([\'onboarding_completed_at\' => null])"`,
            { cwd: PROJECT_ROOT, timeout: 10000 }
        );
    } catch (e) {
        console.warn('Setup warning:', e.message);
    }
}

/**
 * Log in as ADMIN user.
 *
 * Unlike CM/Agency, there is no dedicated role pill for admin on the login page.
 * Instead there is a "System Admin" mock-credentials button we click to
 * auto-fill the email/password fields.
 */
async function loginAsAdmin(page) {
    await page.goto('/login');

    // Click the "System Admin" mock-credentials link to auto-fill
    await page.getByText('System Admin: admin@bayanihan.gov.ph').click();

    // Wait for auto-fill to populate the email field
    await page.waitForFunction(
        (expectedEmail) => {
            const input = document.querySelector('input[type="email"]');
            return input instanceof HTMLInputElement && input.value === expectedEmail;
        },
        ADMIN_EMAIL,
        { timeout: 5000 }
    );

    // Submit login form
    await page.getByRole('button', { name: 'Sign In' }).click();

    // Wait for OTP step
    await page.waitForSelector('text=OTP SENT', { timeout: 10000 });

    // Read the debug_otp from the visible "Debug Mode — OTP: XXXXXX" text
    // and manually fill each input. More robust than relying on the React
    // auto-fill effect which can suffer timing issues on re-login.
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

    // Wait for all 6 OTP inputs to be filled (manual fill or auto-fill)
    await page.waitForFunction(() => {
        const inputs = document.querySelectorAll('input[inputmode="numeric"]');
        return (
            inputs.length === 6 &&
            Array.from(inputs).every((i) => (i instanceof HTMLInputElement ? i.value !== '' : false))
        );
    }, { timeout: 5000 });

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
    setupTestUser();
});

test.describe('ADMIN Onboarding', () => {
    test('Login as ADMIN → WelcomeModal appears → tour steps include admin-specific pages', async ({ page }) => {
        await loginAsAdmin(page);

        // Assert WelcomeModal is visible
        await expect(
            page.getByText('Welcome to One Window Bayanihan!')
        ).toBeVisible({ timeout: 5000 });

        // Start the tour
        await page.getByRole('button', { name: 'Start Tour' }).click();

        // Wait for Driver.js popover to appear
        await page.waitForSelector('.driver-popover', { timeout: 5000 });

        // The first ADMIN tour step is 'Welcome' but the description should
        // reference admin-specific content ("admin dashboard" / "system-wide")
        await expect(page.locator('.driver-popover-title')).toContainText('Welcome');

        // Assert the description mentions admin-specific functionality
        const description = page.locator('.driver-popover-description');
        await expect(description).toContainText('admin');

        // The targeted first-step element has data-tour="dashboard-header"
        await expect(page.locator('[data-tour="dashboard-header"]')).toBeAttached();
    });
});
