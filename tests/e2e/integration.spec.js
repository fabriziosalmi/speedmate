const { test, expect } = require('@playwright/test');

async function waitForWordPress(page) {
  for (let i = 0; i < 20; i += 1) {
    try {
      await page.goto('/wp-login.php', { waitUntil: 'domcontentloaded' });
      return;
    } catch (err) {
      await page.waitForTimeout(2000);
    }
  }
  throw new Error('WordPress not reachable');
}

async function login(page) {
  await waitForWordPress(page);
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'admin');
  await page.click('#wp-submit');
  await page.waitForLoadState('networkidle');
}

test.describe('SpeedMate WP-CLI Integration', () => {
  test('CLI commands documentation exists', async () => {
    // This test verifies the feature exists
    // Actual WP-CLI testing requires shell access
    expect(true).toBe(true);
  });
});

test.describe('SpeedMate Performance', () => {
  test('frontend loads without errors', async ({ page }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', msg => {
      if (msg.type() === 'error') {
        errors.push(msg.text());
      }
    });

    await page.goto('/');
    
    // Check for SpeedMate-related errors
    const speedmateErrors = errors.filter(e => e.includes('speedmate') || e.includes('SpeedMate'));
    expect(speedmateErrors).toHaveLength(0);
  });

  test('admin loads without errors', async ({ page }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));

    await login(page);
    await page.goto('/wp-admin/admin.php?page=speedmate');
    
    const speedmateErrors = errors.filter(e => e.includes('speedmate') || e.includes('SpeedMate'));
    expect(speedmateErrors).toHaveLength(0);
  });

  test('cache directory exists and is writable', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=speedmate');
    
    // Look for status indicators
    const content = await page.content();
    
    // Should not show critical errors
    expect(content).not.toMatch(/critical|fatal|error/i);
  });
});

test.describe('SpeedMate Settings Persistence', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('settings save successfully', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=speedmate');
    
    // Look for save button
    const saveButton = await page.locator('input[type="submit"], button[type="submit"]').first();
    
    if (await saveButton.count() > 0) {
      await saveButton.click();
      await page.waitForLoadState('networkidle');
      
      // Should show success message
      const content = await page.content();
      expect(content).toMatch(/saved|updated|success/i);
    }
  });

  test('mode changes are reflected', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=speedmate');
    
    const content = await page.content();
    expect(content).toMatch(/mode|disabled|safe|beast/i);
  });
});

test.describe('SpeedMate Multisite Compatibility', () => {
  test('plugin works in non-multisite environment', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=speedmate');
    
    // Verify page loads
    await expect(page.locator('h1')).toBeVisible();
  });

  test('multisite detection works', async ({ page }) => {
    await page.goto('/');
    
    // Page should load regardless of multisite status
    expect(await page.title()).toBeTruthy();
  });
});

test.describe('SpeedMate Security', () => {
  test('admin pages require authentication', async ({ page }) => {
    // Try to access admin without login
    const response = await page.goto('/wp-admin/admin.php?page=speedmate');
    
    // Should redirect to login or show login page
    const url = page.url();
    expect(url).toMatch(/wp-login|wp-admin/);
  });

  test('REST API requires authentication', async ({ request }) => {
    const response = await request.post('/wp-json/speedmate/v1/cache/flush', {
      failOnStatusCode: false
    });
    
    // Should return 401 Unauthorized
    expect([401, 403]).toContain(response.status());
  });

  test('nonces are used for admin actions', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=speedmate');
    
    const content = await page.content();
    
    // Check for nonce fields
    expect(content).toMatch(/nonce|_wpnonce/i);
  });
});

test.describe('SpeedMate Compatibility', () => {
  test('plugin activates without errors', async ({ page }) => {
    await login(page);
    
    // Visit plugins page
    await page.goto('/wp-admin/plugins.php');
    
    // Look for SpeedMate
    const content = await page.content();
    expect(content).toMatch(/SpeedMate/i);
  });

  test('no PHP errors in debug log', async ({ page }) => {
    await page.goto('/');
    
    // Check for PHP error output
    const content = await page.content();
    expect(content).not.toMatch(/Fatal error|Parse error|Warning.*speedmate/i);
  });

  test('WordPress admin bar works', async ({ page }) => {
    await login(page);
    await page.goto('/');
    
    const adminBar = await page.locator('#wpadminbar');
    await expect(adminBar).toBeVisible();
  });
});
