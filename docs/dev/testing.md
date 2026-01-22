# Testing

Comprehensive testing guide for SpeedMate.

## Testing Stack

- **PHPUnit**: Unit and integration tests
- **Playwright**: End-to-end browser tests
- **WP-CLI**: Command-line testing
- **Docker**: Isolated test environments

## Setup

### Install Dependencies

```bash
cd speedmate/
composer install
npm install
```

### Configure Test Environment

```bash
# Copy test config
cp tests/.env.example tests/.env

# Edit with your test database
DB_NAME=speedmate_test
DB_USER=root
DB_PASSWORD=password
DB_HOST=localhost
```

## Unit Tests

### Run All Unit Tests

```bash
vendor/bin/phpunit
```

### Run Specific Test

```bash
vendor/bin/phpunit tests/unit/Cache/StaticCacheTest.php
```

### Example Unit Test

```php
<?php
namespace SpeedMate\Tests\Unit\Cache;

use PHPUnit\Framework\TestCase;
use SpeedMate\Cache\StaticCache;

class StaticCacheTest extends TestCase
{
    private StaticCache $cache;
    
    protected function setUp(): void
    {
        $this->cache = StaticCache::instance();
    }
    
    public function test_generate_cache_key()
    {
        $url = 'https://site.com/page';
        $key = $this->cache->generate_key($url);
        
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $key);
    }
    
    public function test_cache_storage()
    {
        $url = 'https://site.com/test';
        $html = '<html><body>Test</body></html>';
        
        $this->cache->save($url, $html);
        $cached = $this->cache->get($url);
        
        $this->assertEquals($html, $cached);
    }
    
    public function test_cache_expiration()
    {
        $url = 'https://site.com/expire';
        $html = '<html><body>Expire</body></html>';
        
        $this->cache->save($url, $html, 1);  // 1 second TTL
        sleep(2);
        
        $cached = $this->cache->get($url);
        $this->assertNull($cached);
    }
}
```

## Integration Tests

### WordPress Integration

```bash
# Install WordPress test suite
bash bin/install-wp-tests.sh speedmate_test root password localhost latest
```

### Example Integration Test

```php
<?php
namespace SpeedMate\Tests\Integration;

use WP_UnitTestCase;
use SpeedMate\Cache\StaticCache;
use SpeedMate\Perf\BeastMode;

class BeastModeTest extends WP_UnitTestCase
{
    public function test_auto_caching_after_threshold()
    {
        $url = home_url('/test-post');
        
        // Create post
        $post_id = $this->factory->post->create([
            'post_title' => 'Test Post',
            'post_status' => 'publish',
        ]);
        
        // Simulate 100 views
        for ($i = 0; $i < 100; $i++) {
            do_action('speedmate_page_view', $url);
        }
        
        // Check if auto-cached
        $cache = StaticCache::instance();
        $this->assertTrue($cache->is_cached($url));
    }
    
    public function test_cache_invalidation_on_post_update()
    {
        $post_id = $this->factory->post->create();
        $url = get_permalink($post_id);
        
        // Cache the post
        StaticCache::instance()->save($url, '<html>Test</html>');
        
        // Update post
        wp_update_post(['ID' => $post_id, 'post_title' => 'Updated']);
        
        // Cache should be cleared
        $this->assertFalse(StaticCache::instance()->is_cached($url));
    }
}
```

## E2E Tests

### Playwright Setup

```bash
# Install Playwright
npm install @playwright/test
npx playwright install
```

### Example E2E Test

```javascript
// tests/e2e/cache.spec.js
const { test, expect } = require('@playwright/test');

test.describe('Cache Functionality', () => {
  test('should serve cached page on second visit', async ({ page }) => {
    // First visit - cache miss
    await page.goto('http://localhost:8080/');
    let cacheHeader = await page.evaluate(() => {
      return document.querySelector('meta[http-equiv="X-Cache"]')?.content;
    });
    expect(cacheHeader).toBe('MISS');
    
    // Second visit - cache hit
    await page.goto('http://localhost:8080/');
    cacheHeader = await page.evaluate(() => {
      return document.querySelector('meta[http-equiv="X-Cache"]')?.content;
    });
    expect(cacheHeader).toBe('HIT');
  });
  
  test('should flush cache via admin interface', async ({ page }) => {
    // Login
    await page.goto('http://localhost:8080/wp-admin');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'password');
    await page.click('#wp-submit');
    
    // Navigate to SpeedMate settings
    await page.goto('http://localhost:8080/wp-admin/options-general.php?page=speedmate');
    
    // Click flush button
    await page.click('button:has-text("Flush Cache")');
    
    // Verify success message
    await expect(page.locator('.notice-success')).toContainText('Cache flushed');
  });
});
```

### Run E2E Tests

```bash
npx playwright test
```

## Performance Testing

### Lighthouse CI

```yaml
# .github/workflows/lighthouse.yml
name: Lighthouse CI
on: [push]

jobs:
  lighthouse:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: actions/setup-node@v3
      
      - name: Run Lighthouse
        run: |
          npm install -g @lhci/cli
          lhci autorun --collect.url=http://localhost:8080
```

### Load Testing

```bash
# Install k6
brew install k6

# Run load test
k6 run tests/performance/load-test.js
```

**Example load test:**

```javascript
// tests/performance/load-test.js
import http from 'k6/http';
import { check, sleep } from 'k6';

export let options = {
  stages: [
    { duration: '30s', target: 20 },  // Ramp up to 20 users
    { duration: '1m', target: 20 },   // Stay at 20 users
    { duration: '30s', target: 0 },   // Ramp down
  ],
};

export default function () {
  let response = http.get('http://localhost:8080/');
  
  check(response, {
    'status is 200': (r) => r.status === 200,
    'cache hit': (r) => r.headers['X-Cache'] === 'HIT',
    'response time < 50ms': (r) => r.timings.duration < 50,
  });
  
  sleep(1);
}
```

## WP-CLI Testing

### Test Cache Operations

```bash
# Flush cache
wp speedmate flush
echo $?  # Should be 0 on success

# Warm cache
wp speedmate warm --urls=https://site.com/
echo $?  # Should be 0 on success

# Check stats
wp speedmate stats
```

### Automated Test Script

```bash
#!/bin/bash
set -e

echo "Testing SpeedMate WP-CLI commands..."

# Test flush
wp speedmate flush
if [ $? -eq 0 ]; then
  echo "✓ Flush test passed"
else
  echo "✗ Flush test failed"
  exit 1
fi

# Test warm
wp speedmate warm --urls=$(wp option get home)
if [ $? -eq 0 ]; then
  echo "✓ Warm test passed"
else
  echo "✗ Warm test failed"
  exit 1
fi

# Test stats
wp speedmate stats --format=json > /dev/null
if [ $? -eq 0 ]; then
  echo "✓ Stats test passed"
else
  echo "✗ Stats test failed"
  exit 1
fi

echo "All tests passed!"
```

## Docker Testing

### Test Environment

```yaml
# docker-compose.test.yml
version: '3.8'

services:
  wordpress:
    image: wordpress:latest
    environment:
      WORDPRESS_DB_HOST: db
      WORDPRESS_DB_USER: test
      WORDPRESS_DB_PASSWORD: test
      WORDPRESS_DB_NAME: test
    volumes:
      - ./:/var/www/html/wp-content/plugins/speedmate
      
  db:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: test
      MYSQL_USER: test
      MYSQL_PASSWORD: test
      MYSQL_RANDOM_ROOT_PASSWORD: '1'
```

### Run Tests in Docker

```bash
# Start test environment
docker-compose -f docker-compose.test.yml up -d

# Run tests
docker-compose exec wordpress vendor/bin/phpunit

# Cleanup
docker-compose -f docker-compose.test.yml down -v
```

## Coverage

### Generate Coverage Report

```bash
vendor/bin/phpunit --coverage-html coverage/
```

### View Coverage

```bash
open coverage/index.html
```

### CI Coverage

```yaml
# .github/workflows/coverage.yml
name: Coverage
on: [push]

jobs:
  coverage:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: shivammathur/setup-php@v2
        with:
          php-version: 8.1
          coverage: xdebug
          
      - name: Install dependencies
        run: composer install
        
      - name: Run tests with coverage
        run: vendor/bin/phpunit --coverage-clover coverage.xml
        
      - name: Upload to Codecov
        uses: codecov/codecov-action@v3
        with:
          files: ./coverage.xml
```

## Continuous Integration

### GitHub Actions

```yaml
# .github/workflows/tests.yml
name: Tests
on: [push, pull_request]

jobs:
  phpunit:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php: [7.4, 8.0, 8.1, 8.2]
        wordpress: [6.0, 6.1, 6.2, 6.3]
    
    steps:
      - uses: actions/checkout@v3
      
      - uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          
      - name: Install dependencies
        run: composer install
        
      - name: Run PHPUnit
        run: vendor/bin/phpunit
  
  playwright:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: actions/setup-node@v3
        with:
          node-version: 18
          
      - name: Install Playwright
        run: npm ci && npx playwright install --with-deps
        
      - name: Run E2E tests
        run: npx playwright test
```

## Manual Testing

### Test Checklist

**Cache Functionality:**
- [ ] Page cached on first visit
- [ ] Cached page served on second visit
- [ ] Cache invalidated on post update
- [ ] Cache excludes logged-in users
- [ ] Cache respects query strings

**Beast Mode:**
- [ ] Pages auto-cached after threshold
- [ ] Whitelist patterns work correctly
- [ ] Blacklist patterns work correctly
- [ ] Score calculation accurate
- [ ] Dashboard shows correct stats

**Media Optimization:**
- [ ] WebP images generated
- [ ] Images lazy loaded
- [ ] Fallback to original format
- [ ] Responsive srcset generated

**Performance:**
- [ ] Lighthouse score > 90
- [ ] LCP < 2.5s
- [ ] FCP < 1.8s
- [ ] TTI < 3.8s

## Debugging Tests

### Enable Debug Mode

```php
define('SPEEDMATE_DEBUG', true);
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Verbose PHPUnit

```bash
vendor/bin/phpunit --verbose --debug
```

### Playwright Debug

```bash
PWDEBUG=1 npx playwright test
```

## Next Steps

- [Architecture](/dev/architecture)
- [Contributing](/dev/contributing)
- [CI/CD Setup](/.github/workflows/)
