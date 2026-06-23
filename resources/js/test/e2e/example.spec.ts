import { test, expect } from '@playwright/test';

test('app loads successfully', async ({ page }) => {
    await page.goto('/');
    await expect(page).toHaveTitle(/Bayanihan|One Window|Login/i);
});
