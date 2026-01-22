const { test, expect } = require('@playwright/test');

async function waitForWordPress(page, attempts = 20) {
  for (let i = 0; i < attempts; i += 1) {
    try {
      await page.goto('/wp-login.php', { waitUntil: 'domcontentloaded' });
      return;
    } catch (err) {
      await page.waitForTimeout(2000);
    }
  }
  throw new Error('WordPress not reachable');
}

test('SpeedMate admin page loads', async ({ page }) => {
  await waitForWordPress(page);
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'admin');
  await page.click('#wp-submit');
  await page.goto('/wp-admin/admin.php?page=speedmate');
  await expect(page.locator('h1')).toHaveText(/SpeedMate/);
});
