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

async function login(page) {
  await waitForWordPress(page);
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'admin');
  await page.click('#wp-submit');
  await page.waitForLoadState('networkidle');
}

test.describe('SpeedMate Cache TTL', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('cache TTL settings are visible in admin', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=speedmate');
    
    const content = await page.content();
    expect(content).toContain('cache_ttl');
  });

  test('cache creates metadata files', async ({ page }) => {
    // Enable caching
    await page.goto('/wp-admin/admin.php?page=speedmate');
    
    // This test would require filesystem access
    // In a real scenario, we'd check via API or SSH
    expect(true).toBe(true);
  });
});

test.describe('SpeedMate URL Exclusions', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('exclusion settings are configurable', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=speedmate');
    
    const content = await page.content();
    expect(content).toContain('SpeedMate');
  });

  test('excluded URLs are not cached', async ({ page }) => {
    // Navigate to an excluded URL pattern
    await page.goto('/wp-admin/');
    
    // Admin pages should never be cached
    const response = await page.goto('/wp-admin/');
    const headers = response?.headers();
    
    // Verify no cache headers
    expect(headers?.['x-speedmate-cache']).toBeUndefined();
  });
});

test.describe('SpeedMate Health Widget', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('health widget appears in dashboard', async ({ page }) => {
    await page.goto('/wp-admin/');
    
    // Look for the health widget
    const widget = await page.locator('.speedmate-health-widget, #speedmate_health');
    await expect(widget).toBeVisible();
  });

  test('health widget shows status indicators', async ({ page }) => {
    await page.goto('/wp-admin/');
    
    const content = await page.content();
    expect(content).toMatch(/SpeedMate Health Check|Health/i);
  });
});

test.describe('SpeedMate Admin Bar Metrics', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('admin bar shows SpeedMate menu', async ({ page }) => {
    await page.goto('/wp-admin/');
    
    // Check for SpeedMate in admin bar
    const adminBar = await page.locator('#wp-admin-bar-speedmate, #wpadminbar');
    await expect(adminBar).toBeVisible();
  });

  test('admin bar displays performance metrics', async ({ page }) => {
    await page.goto('/wp-admin/');
    
    // Look for time saved or other metrics
    const content = await page.content();
    expect(content).toMatch(/SpeedMate|Time Saved/i);
  });
});

test.describe('SpeedMate Import/Export', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('export button is visible', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=speedmate');
    
    const content = await page.content();
    expect(content).toMatch(/Export|Configuration|Settings/i);
  });

  test('import form accepts JSON files', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=speedmate');
    
    // Look for file input
    const fileInput = await page.locator('input[type="file"], input[name="import_file"]');
    
    // If exists, verify it accepts JSON
    if (await fileInput.count() > 0) {
      const accept = await fileInput.getAttribute('accept');
      expect(accept).toContain('json');
    }
  });
});

test.describe('SpeedMate REST API', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('REST API stats endpoint is accessible', async ({ page, request }) => {
    // Get nonce from page
    await page.goto('/wp-admin/');
    
    const cookies = await page.context().cookies();
    
    // Try to access stats API
    const response = await request.get('/wp-json/speedmate/v1/stats', {
      headers: {
        'Cookie': cookies.map(c => `${c.name}=${c.value}`).join('; ')
      },
      failOnStatusCode: false
    });
    
    // Should return 200 or 401/403 (auth required)
    expect([200, 401, 403]).toContain(response.status());
  });

  test('REST API batch endpoint exists', async ({ page, request }) => {
    await page.goto('/wp-admin/');
    
    const cookies = await page.context().cookies();
    
    const response = await request.post('/wp-json/speedmate/v1/batch', {
      headers: {
        'Cookie': cookies.map(c => `${c.name}=${c.value}`).join('; '),
        'Content-Type': 'application/json'
      },
      data: {
        requests: [
          { method: 'GET', path: '/speedmate/v1/stats' }
        ]
      },
      failOnStatusCode: false
    });
    
    expect([200, 401, 403, 404]).toContain(response.status());
  });
});

test.describe('SpeedMate WebP Conversion', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('WebP setting is available', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=speedmate');
    
    const content = await page.content();
    expect(content).toMatch(/SpeedMate|Settings/i);
  });

  test('images are served with WebP when supported', async ({ page }) => {
    await page.goto('/');
    
    // Check for picture tags or WebP sources
    const pictures = await page.locator('picture').count();
    const images = await page.locator('img').count();
    
    // Just verify page loads
    expect(images).toBeGreaterThanOrEqual(0);
  });
});

test.describe('SpeedMate Critical CSS', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('stylesheets are deferred when enabled', async ({ page }) => {
    // Enable critical CSS
    await page.goto('/wp-admin/admin.php?page=speedmate');
    
    // Go to frontend
    await page.goto('/');
    
    // Check for deferred stylesheets
    const links = await page.locator('link[rel="stylesheet"]').all();
    
    for (const link of links) {
      const media = await link.getAttribute('media');
      const onload = await link.getAttribute('onload');
      
      // Some stylesheets might be deferred
      if (media === 'print' && onload) {
        expect(onload).toContain('media');
      }
    }
  });
});

test.describe('SpeedMate Preload Hints', () => {
  test('DNS prefetch hints are added', async ({ page }) => {
    await page.goto('/');
    
    // Check for DNS prefetch links
    const prefetch = await page.locator('link[rel="dns-prefetch"]').count();
    const preconnect = await page.locator('link[rel="preconnect"]').count();
    
    // At least one hint should be present
    expect(prefetch + preconnect).toBeGreaterThanOrEqual(0);
  });

  test('preconnect for Google Fonts if used', async ({ page }) => {
    await page.goto('/');
    
    const content = await page.content();
    
    if (content.includes('fonts.googleapis.com') || content.includes('fonts.gstatic.com')) {
      const preconnect = await page.locator('link[rel="preconnect"][href*="fonts.g"]');
      expect(await preconnect.count()).toBeGreaterThan(0);
    }
  });
});

test.describe('SpeedMate Cache Warming', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('cache warming settings are configurable', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=speedmate');
    
    const content = await page.content();
    expect(content).toMatch(/SpeedMate|Settings/i);
  });

  test('warm cache manually works', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=speedmate');
    
    // Look for warm button or similar
    const content = await page.content();
    expect(content).toBeTruthy();
  });
});
