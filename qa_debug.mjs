import { chromium } from 'playwright';
import fs from 'fs';

const BASE_URL = 'http://127.0.0.1:8000';

async function main() {
    const browser = await chromium.launch({ headless: false, slowMo: 200 });
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const page = await context.newPage();

    // Listen for console errors
    page.on('console', msg => {
        if (msg.type() === 'error') console.log('  🖥️ Console error:', msg.text().substring(0, 200));
    });
    page.on('pageerror', err => console.log('  🖥️ Page error:', err.message.substring(0, 200)));

    try {
        // Login first
        await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(2000);

        // Click admin button
        await page.locator('button:has-text("admin@bayanihan.gov.ph")').click();
        await page.waitForTimeout(500);

        // Click Sign In
        await page.locator('button:has-text("Sign In")').click();
        await page.waitForTimeout(3000);

        // Verify OTP page and auto-fill
        await page.waitForTimeout(1000);

        // Click Verify
        await page.locator('button:has-text("Verify & Continue")').click();
        await page.waitForTimeout(3000);

        console.log('After login URL:', page.url());

        // Navigate to admin/agencies
        await page.goto(`${BASE_URL}/admin/agencies`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(3000);

        console.log('Agencies page URL:', page.url());

        // Check page content - just grab the text
        const bodyText = await page.evaluate(() => document.body.innerText.substring(0, 1500));
        console.log('--- Page Text ---');
        console.log(bodyText);
        console.log('--- End ---');

        // Check for modals
        const modals = await page.locator('[class*="fixed"],[class*="modal"],[class*="overlay"]').count();
        console.log(`Modals/overlays found: ${modals}`);

        // Try clicking New Agency
        const newBtn = page.locator('button:has-text("New Agency")');
        console.log(`New Agency button visible: ${await newBtn.isVisible()}`);
        
        await newBtn.click();
        await page.waitForTimeout(2000);

        // Check what's now visible
        const text2 = await page.evaluate(() => document.body.innerText.substring(0, 2000));
        console.log('--- After Click ---');
        console.log(text2);
        console.log('--- End ---');

        // Check all inputs
        const allInputs = await page.locator('input').count();
        console.log(`Total inputs: ${allInputs}`);
        for (let i = 0; i < allInputs; i++) {
            const type = await page.locator('input').nth(i).getAttribute('type');
            console.log(`  Input ${i}: type=${type}`);
        }

        // Check if leaflet container exists
        const leafletCount = await page.locator('.leaflet-container').count();
        console.log(`Leaflet containers: ${leafletCount}`);

        // Check all divs with specific classes
        const allElements = await page.evaluate(() => {
            const els = [];
            document.querySelectorAll('input, .leaflet-container, [class*=modal], [class*=overlay], [class*=fixed]').forEach(el => {
                els.push({
                    tag: el.tagName,
                    type: el.type,
                    class: el.className?.substring(0, 80),
                    style: el.style?.position,
                    visible: el.offsetParent !== null,
                    rect: el.getBoundingClientRect ? `${el.getBoundingClientRect().width}x${el.getBoundingClientRect().height}` : 'N/A'
                });
            });
            return els;
        });
        console.log('All relevant elements:');
        allElements.forEach((el, i) => console.log(`  ${i}: ${el.tag} type=${el.type} class="${el.class}" visible=${el.visible} rect=${el.rect}`));

        await page.screenshot({ path: 'qa_screenshots/debug-state.png', fullPage: true });
        console.log('\nDebug screenshot saved');

    } catch (error) {
        console.error('Error:', error.message);
        await page.screenshot({ path: 'qa_screenshots/debug-error.png', fullPage: true });
    } finally {
        await browser.close();
    }
}

main().catch(console.error);
