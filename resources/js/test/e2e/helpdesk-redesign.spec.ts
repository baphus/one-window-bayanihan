import { test, expect, Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const ARTICLE_SLUG = 'using-public-tracking-portal';

function collectErrors(page: Page) {
  const errors: string[] = [];
  page.on('pageerror', (err) => errors.push(`pageerror: ${err.message}`));
  page.on('console', (msg) => {
    if (msg.type() === 'error' && !/favicon|404|net::/i.test(msg.text())) {
      errors.push(`console: ${msg.text()}`);
    }
  });
  return errors;
}

test.describe('Helpdesk redesign — rendering', () => {
  for (const [name, path] of [
    ['landing', '/help'],
    ['category', '/help?category=ofw-assistance'],
    ['subcategory', '/help?category=case-submission'],
    ['article', `/help/${ARTICLE_SLUG}`],
    ['search', '/help/search?q=referral'],
  ] as const) {
    test(`${name} page renders without JS errors`, async ({ page }) => {
      const errors = collectErrors(page);
      await page.goto(path);
      await expect(page.locator('#help-main')).toBeVisible();
      expect(errors).toEqual([]);
    });
  }
});

test.describe('Helpdesk redesign — keyboard walkthrough', () => {
  test('skip link is the first tab stop on the landing page', async ({ page }) => {
    await page.goto('/help');
    await page.waitForLoadState('networkidle');
    // Ensure body is focused first so Tab targets the first focusable element
    await page.evaluate(() => { document.body.focus(); });
    await page.keyboard.press('Tab');
    await expect(page.getByRole('link', { name: /skip to main content/i })).toBeFocused({ timeout: 5000 });
  });

  test('landing → topic card → category → article → feedback → contact, by keyboard', async ({ page }) => {
    await page.goto('/help');

    // Topic card is keyboard-activatable
    const topicCard = page.getByRole('link', { name: /OFW Assistance/ }).last();
    await topicCard.focus();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/category=ofw-assistance/);
    await expect(page.getByRole('heading', { level: 1, name: 'OFW Assistance' })).toBeVisible();

    // Article list row is keyboard-activatable
    const articleLink = page.getByRole('link', { name: /Using the Public Tracking Portal/i }).first();
    await articleLink.focus();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(new RegExp(`/help/${ARTICLE_SLUG}`));

    // Feedback widget: keyboard-activate "No" → contact escalation appears
    const noButton = page.getByRole('button', { name: 'No' });
    await noButton.scrollIntoViewIfNeeded();
    await noButton.focus();
    await page.keyboard.press('Enter');
    await expect(page.getByText(/thank you for your feedback/i)).toBeVisible();
    await expect(page.getByRole('link', { name: /contact support/i }).first()).toBeVisible();
  });

  test('focused links show a visible focus indicator', async ({ page }) => {
    await page.goto('/help');
    const card = page.getByRole('link', { name: /Case Management/ }).last();
    await card.focus();
    // Tailwind focus-visible ring renders as a box-shadow
    const shadow = await card.evaluate((el) => getComputedStyle(el).boxShadow);
    expect(shadow).not.toBe('none');
  });

  test('feedback "Yes" persists across reload', async ({ page }) => {
    await page.goto(`/help/${ARTICLE_SLUG}`);
    await page.getByRole('button', { name: 'Yes' }).click();
    await expect(page.getByText(/thank you for your feedback/i)).toBeVisible();
    await page.reload();
    await expect(page.getByText(/thank you for your feedback/i)).toBeVisible();
    await expect(page.getByRole('button', { name: 'Yes' })).toHaveCount(0);
  });
});

test.describe('Helpdesk redesign — accessibility (axe, WCAG 2.1 AA)', () => {
  for (const [name, path] of [
    ['landing', '/help'],
    ['category', '/help?category=ofw-assistance'],
    ['article', `/help/${ARTICLE_SLUG}`],
  ] as const) {
    test(`${name} page has no WCAG A/AA violations`, async ({ page }) => {
      await page.goto(path);
      await page.locator('#help-main').waitFor();
      const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
        .analyze();
      const summary = results.violations.map((v) => ({
        id: v.id,
        impact: v.impact,
        nodes: v.nodes.map((n) => n.target.join(' ')).slice(0, 5),
      }));
      expect(summary).toEqual([]);
    });
  }
});

test.describe('Helpdesk redesign — responsive', () => {
  test.use({ viewport: { width: 375, height: 812 } });

  test('mobile landing has no horizontal overflow', async ({ page }) => {
    await page.goto('/help');
    const overflow = await page.evaluate(
      () => document.documentElement.scrollWidth - document.documentElement.clientWidth
    );
    expect(overflow).toBeLessThanOrEqual(1);
  });

  test('mobile category page uses the Browse topics disclosure', async ({ page }) => {
    await page.goto('/help?category=ofw-assistance');

    // Desktop sidebar hidden, disclosure visible and collapsed
    await expect(page.locator('aside')).toBeHidden();
    const disclosure = page.getByRole('button', { name: /browse topics/i });
    await expect(disclosure).toBeVisible();
    await expect(disclosure).toHaveAttribute('aria-expanded', 'false');

    // Expands with keyboard and reveals the category tree
    await disclosure.focus();
    await page.keyboard.press('Enter');
    await expect(disclosure).toHaveAttribute('aria-expanded', 'true');
    await expect(
      page.locator('#mobile-topics-nav').getByRole('link', { name: 'All topics' })
    ).toBeVisible();
  });
});

test.describe('Helpdesk redesign — desktop layout', () => {
  test.use({ viewport: { width: 1280, height: 900 } });

  test('desktop category page shows sidebar, hides disclosure', async ({ page }) => {
    await page.goto('/help?category=ofw-assistance');
    await expect(page.locator('aside')).toBeVisible();
    await expect(page.getByRole('button', { name: /browse topics/i })).toBeHidden();
  });

  test('desktop landing has no sidebar', async ({ page }) => {
    await page.goto('/help');
    await expect(page.locator('aside')).toHaveCount(0);
  });
});
