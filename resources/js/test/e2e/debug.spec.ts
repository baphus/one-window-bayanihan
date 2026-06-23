import { test, expect } from '@playwright/test';

test('Debug: capture login page', async ({ page }) => {
    const logs: string[] = [];
    page.on('console', msg => {
        logs.push(`[${msg.type()}] ${msg.text()}`);
    });
    page.on('pageerror', err => {
        logs.push(`[PAGE ERROR] ${err.message}`);
    });
    page.on('response', resp => {
        if (resp.status() >= 400) {
            logs.push(`[HTTP ${resp.status()}] ${resp.url()}`);
        }
    });

    await page.goto('/login', { waitUntil: 'load' });
    await page.waitForTimeout(5000);

    const scripts = await page.locator('script').all();
    for (const script of scripts) {
        const src = await script.getAttribute('src');
        if (src) logs.push(`SCRIPT: ${src}`);
    }

    const viteEntry = await page.locator('script[type="module"]').count();
    logs.push(`Vite module scripts: ${viteEntry}`);

    const appContent = await page.locator('#app').innerHTML();
    logs.push(`#app innerHTML length: ${appContent.length}`);
    logs.push(`#app innerHTML (first 300): ${appContent.substring(0, 300)}`);

    const buttons = await page.locator('button').count();
    logs.push(`Total buttons: ${buttons}`);

    for (const log of logs) {
        console.log(log);
    }
});
