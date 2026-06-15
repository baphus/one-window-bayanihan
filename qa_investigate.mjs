import { chromium } from 'playwright';
import fs from 'fs';

const BASE_URL = 'http://127.0.0.1:8000';
const SCREENSHOTS_DIR = './qa_screenshots';

async function main() {
    const browser = await chromium.launch({ headless: false, slowMo: 100 });
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const page = await context.newPage();

    page.on('console', msg => {
        if (msg.type() === 'error') console.log('  🖥️ Console error:', msg.text().substring(0, 300));
    });
    page.on('pageerror', err => console.log('  🖥️ Page error:', err.message.substring(0, 300)));

    try {
        // Login
        await page.goto(`${BASE_URL}/login`, { waitUntil: 'load', timeout: 60000 });
        await page.waitForTimeout(3000);
        await page.locator('button:has-text("admin@bayanihan.gov.ph")').click();
        await page.waitForTimeout(500);
        await page.locator('button:has-text("Sign In")').click();
        await page.waitForTimeout(3000);

        // Handle OTP
        await page.waitForTimeout(1000);
        await page.locator('button:has-text("Verify & Continue")').click();
        await page.waitForTimeout(3000);

        console.log('URL after login:', page.url());

        // Navigate to admin/agencies
        await page.goto(`${BASE_URL}/admin/agencies`, { waitUntil: 'load', timeout: 60000 });
        await page.waitForTimeout(3000);

        console.log('Agencies URL:', page.url());

        // Capture page state
        const bodyHTML = await page.evaluate(() => document.getElementById('app')?.innerHTML?.substring(0, 3000) || 'NO APP');
        console.log('\n--- App inner HTML (first 3000 chars) ---');
        console.log(bodyHTML);
        console.log('--- End ---\n');

        // Check for the New Agency button
        const newBtn = page.locator('button:has-text("New Agency")');
        console.log(`'New Agency' button visible: ${await newBtn.isVisible()}`);
        
        // Click New Agency
        await newBtn.click();
        await page.waitForTimeout(3000);

        console.log('URL after click:', page.url());

        // Debug after click
        const afterHTML = await page.evaluate(() => {
            const app = document.getElementById('app');
            if (!app) return 'NO APP ELEMENT';
            // Check for fixed overlay (modal)
            const overlays = document.querySelectorAll('[class*="fixed"]');
            const overlayInfo = [];
            overlays.forEach(o => {
                overlayInfo.push({
                    class: o.className?.substring(0, 100),
                    childCount: o.children.length,
                    innerTextFirst100: o.innerText?.substring(0, 100)
                });
            });
            const inputs = document.querySelectorAll('input');
            const modals = document.querySelectorAll('[class*="z-50"]');
            return {
                overlays: overlayInfo,
                inputCount: inputs.length,
                inputTypes: [...inputs].map(i => ({ type: i.type, id: i.id, accept: i.accept })),
                modalCount: modals.length,
                modals: [...modals].map(m => ({
                    class: m.className?.substring(0, 100),
                    children: m.children.length,
                    text: m.innerText?.substring(0, 200)
                })),
                hasLeaflet: document.querySelectorAll('.leaflet-container').length > 0,
                hasFileInput: document.querySelectorAll('input[type="file"]').length > 0,
            };
        });

        console.log('\n--- Page Analysis After Click ---');
        console.log(JSON.stringify(afterHTML, null, 2));
        console.log('--- End ---');

        // Take screenshot
        await page.screenshot({ path: `${SCREENSHOTS_DIR}/investigate-modal.png`, fullPage: true });
        console.log('\nScreenshot saved to investigate-modal.png');

    } catch (error) {
        console.error('Error:', error.message);
        try {
            await page.screenshot({ path: `${SCREENSHOTS_DIR}/investigate-error.png`, fullPage: true });
        } catch {}
    } finally {
        await browser.close();
    }
}

main().catch(console.error);
