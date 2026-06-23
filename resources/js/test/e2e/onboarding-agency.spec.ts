import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const PROJECT_ROOT = path.resolve(__dirname, '../../../..');
const AGENCY_EMAIL = 'owwa@bayanihan.gov.ph';

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
            `php artisan tinker --execute="\\App\\Models\\User::where(\'email\', \'${AGENCY_EMAIL}\')->update([\'onboarding_completed_at\' => null])"`,
            { cwd: PROJECT_ROOT, timeout: 10000 }
        );
    } catch (e) {
        console.warn('Setup warning:', e.message);
    }
}

/**
 * Log in as AGENCY user using the role selector (auto-fills credentials)
 * and the debug OTP auto-fill flow.
 */
async function loginAsAgency(page) {
    await page.goto('/login');

    // Click "Agency Focal" role pill to auto-fill email + password
    await page.getByRole('button', { name: 'Agency Focal' }).click();

    // Wait for auto-fill to populate the email field
    await page.waitForFunction(
        (expectedEmail) => {
            const input = document.querySelector('input[type="email"]');
            return input instanceof HTMLInputElement && input.value === expectedEmail;
        },
        AGENCY_EMAIL,
        { timeout: 5000 }
    );

    // Submit login form
    await page.getByRole('button', { name: 'Sign In' }).click();

    // Wait for OTP step
    await page.waitForSelector('text=OTP SENT', { timeout: 10000 });

    // Wait for all 6 OTP inputs to be auto-filled
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

test.describe('AGENCY Onboarding', () => {
    test('Login as AGENCY → WelcomeModal appears → tour steps are AGENCY-specific', async ({ page }) => {
        await loginAsAgency(page);

        // Assert WelcomeModal is visible
        await expect(
            page.getByText('Welcome to One Window Bayanihan!')
        ).toBeVisible({ timeout: 5000 });

        // Start the tour
        await page.getByRole('button', { name: 'Start Tour' }).click();

        // Wait for Driver.js popover to appear
        await page.waitForSelector('.driver-popover', { timeout: 5000 });

        // The first AGENCY tour step is 'Performance Metrics' with description
        // referencing agency-specific content
        await expect(page.locator('.driver-popover-title')).toContainText('Performance Metrics');

        // Assert the popover description is AGENCY-specific
        await expect(page.locator('.driver-popover-description')).toContainText('agency');

        // The targeted element should be agency-specific data-tour attribute
        await expect(page.locator('[data-tour="dashboard-agency-metrics"]')).toBeAttached();
    });
});
