# Beast Mode Configuration

Advanced configuration guide for Beast Mode automatic caching.

## Overview

Beast Mode requires careful tuning to balance caching aggressiveness with server resources and content freshness.

## Threshold Tuning

### Understanding Scores

Pages are scored based on:

```
score = (hits × 10) + (rate × 5) + (avg_time / 100)
```

**Example calculation:**
```
Page: /blog/popular-post
Hits: 142 (in 1 hour)
Rate: 2.3 requests/minute
Avg Time: 450ms

Score = (142 × 10) + (2.3 × 5) + (450 / 100)
      = 1420 + 11.5 + 4.5
      = 1436
```

### Recommended Thresholds

| Site Type | Threshold | Reasoning |
|-----------|-----------|-----------|
| Low Traffic | 20-30 | Cache earlier |
| Medium Traffic | 40-60 | Balanced |
| High Traffic | 70-100 | Cache only very popular |
| Enterprise | 100+ | Very selective |

### Dynamic Threshold

Adjust based on time of day:

```php
add_filter('speedmate_beast_threshold', function($threshold) {
    $hour = (int) date('H');
    
    // Business hours: lower threshold
    if ($hour >= 9 && $hour <= 17) {
        return $threshold * 0.7;
    }
    
    // Night: higher threshold
    if ($hour >= 22 || $hour <= 6) {
        return $threshold * 1.5;
    }
    
    return $threshold;
});
```

## Whitelist Strategies

### By Content Type

```php
'beast_whitelist' => [
    // Homepage
    '/',
    
    // Blog content
    '/blog/*',
    '/category/*',
    '/tag/*',
    '/author/*',
    
    // Pages
    '/about',
    '/contact',
    '/services',
    
    // E-commerce
    '/shop/*',
    '/product/*',
    '/product-category/*',
]
```

### By URL Pattern

```php
'beast_whitelist' => [
    // All blog posts
    '/blog/????/??/??/*',  // Date-based URLs
    
    // All numeric IDs
    '/*-p[0-9]+',  // Posts with -p123 suffix
    
    // All category pages
    '/category/*/page/*',
]
```

### Dynamic Whitelist

```php
add_filter('speedmate_beast_whitelist', function($whitelist) {
    // Add all published posts
    $posts = get_posts([
        'numberposts' => 100,
        'post_status' => 'publish',
    ]);
    
    foreach ($posts as $post) {
        $whitelist[] = parse_url(get_permalink($post), PHP_URL_PATH);
    }
    
    // Add popular categories
    $categories = get_categories([
        'orderby' => 'count',
        'order' => 'DESC',
        'number' => 10,
    ]);
    
    foreach ($categories as $cat) {
        $whitelist[] = parse_url(get_category_link($cat), PHP_URL_PATH);
    }
    
    return $whitelist;
});
```

## Blacklist Strategies

### E-Commerce Protection

```php
'beast_blacklist' => [
    // Checkout flow
    '/checkout/*',
    '/cart/*',
    '/order-received/*',
    '/order-pay/*',
    
    // User account
    '/my-account/*',
    '/customer/*',
    '/account/*',
    
    // Payment
    '/payment/*',
    '/billing/*',
]
```

### User-Specific Content

```php
'beast_blacklist' => [
    // User profiles
    '/profile/*',
    '/user/*',
    '/dashboard/*',
    
    // Dynamic forms
    '/contact-form/*',
    '/form/*',
    
    // Search
    '/*?s=*',
    '/search/*',
    
    // Admin
    '/wp-admin/*',
    '/wp-login.php',
]
```

### Conditional Blacklist

```php
add_filter('speedmate_beast_blacklist', function($blacklist) {
    // Don't cache if user is logged in
    if (is_user_logged_in()) {
        $blacklist[] = '/*';  // Blacklist everything
    }
    
    // Don't cache during sale
    if (is_sale_active()) {
        $blacklist[] = '/shop/*';
        $blacklist[] = '/product/*';
    }
    
    return $blacklist;
});
```

## Time Window Configuration

### Adaptive Window

```php
add_filter('speedmate_beast_time_window', function($window) {
    // Get current traffic rate
    $current_rate = \SpeedMate\Utils\Stats::get_current_rate();
    
    // High traffic: shorter window
    if ($current_rate > 100) {
        return 1800;  // 30 minutes
    }
    
    // Low traffic: longer window
    if ($current_rate < 10) {
        return 7200;  // 2 hours
    }
    
    return $window;  // Default 1 hour
});
```

### Peak Hours Detection

```php
add_filter('speedmate_beast_time_window', function($window) {
    $hour = (int) date('H');
    
    // Peak hours: shorter window for faster adaptation
    if ($hour >= 9 && $hour <= 17) {
        return 1800;  // 30 minutes
    }
    
    // Off-peak: longer window
    return 3600;  // 1 hour
});
```

## Cache TTL per Pattern

```php
add_filter('speedmate_beast_ttl', function($ttl, $url) {
    // Homepage: 30 minutes
    if ($url === '/') {
        return 1800;
    }
    
    // Blog posts: 1 hour
    if (preg_match('#^/blog/#', $url)) {
        return 3600;
    }
    
    // Product pages: 15 minutes
    if (preg_match('#^/product/#', $url)) {
        return 900;
    }
    
    // Static pages: 24 hours
    if (preg_match('#^/(about|contact|privacy)#', $url)) {
        return 86400;
    }
    
    return $ttl;  // Default
}, 10, 2);
```

## Scoring Customization

### Custom Scoring Algorithm

```php
add_filter('speedmate_beast_score', function($score, $url, $data) {
    // Boost score for specific patterns
    if (preg_match('#^/important/#', $url)) {
        $score *= 2;
    }
    
    // Reduce score for long pages
    if ($data['avg_time'] > 1000) {  // > 1 second
        $score *= 0.8;
    }
    
    // Boost for high engagement
    if ($data['avg_scroll_depth'] > 80) {
        $score *= 1.2;
    }
    
    return $score;
}, 10, 3);
```

### Resource-Based Scoring

```php
add_filter('speedmate_beast_score', function($score, $url, $data) {
    // Penalize resource-heavy pages
    $queries = $data['db_queries'] ?? 0;
    $memory = $data['memory_usage'] ?? 0;
    
    if ($queries > 50) {
        $score *= 0.7;  // Reduce score
    }
    
    if ($memory > 50 * 1024 * 1024) {  // > 50MB
        $score *= 0.8;
    }
    
    return $score;
}, 10, 3);
```

## Monitoring Configuration

### Enable Detailed Logging

```php
update_option('speedmate_settings', [
    'logging_enabled' => true,
    'log_level' => 'debug',
    'log_beast_decisions' => true,
]);
```

### Custom Logging

```php
add_action('speedmate_beast_decision', function($decision, $url, $score) {
    if ($decision === 'cache') {
        error_log(sprintf(
            '[Beast] Auto-cached: %s (score: %d)',
            $url,
            $score
        ));
    }
}, 10, 3);
```

### Metrics Collection

```php
add_action('speedmate_beast_score_calculated', function($url, $score, $data) {
    // Send to analytics
    if (function_exists('send_to_analytics')) {
        send_to_analytics('beast_score', [
            'url' => $url,
            'score' => $score,
            'hits' => $data['hits'],
            'rate' => $data['rate'],
        ]);
    }
}, 10, 3);
```

## Performance Optimization

### Resource Limits

```php
update_option('speedmate_settings', [
    'beast_max_cached_pages' => 1000,  // Max pages to cache
    'beast_max_memory' => 100 * 1024 * 1024,  // 100MB
    'beast_max_cache_size' => 500 * 1024 * 1024,  // 500MB
]);
```

### Cleanup Strategy

```php
add_filter('speedmate_beast_cleanup', function($should_cleanup, $stats) {
    // Cleanup if cache size > 400MB
    if ($stats['cache_size'] > 400 * 1024 * 1024) {
        return true;
    }
    
    // Cleanup if hit rate < 70%
    if ($stats['hit_rate'] < 70) {
        return true;
    }
    
    return $should_cleanup;
}, 10, 2);
```

## A/B Testing

### Test Different Thresholds

```php
add_filter('speedmate_beast_threshold', function($threshold) {
    // 50% users get threshold 40, 50% get threshold 60
    $group = isset($_COOKIE['ab_group']) ? $_COOKIE['ab_group'] : rand(0, 1);
    
    if ($group === 0) {
        return 40;  // Group A: more aggressive
    } else {
        return 60;  // Group B: more conservative
    }
});
```

### Track Results

```php
add_action('speedmate_page_served', function($url, $cached) {
    $group = isset($_COOKIE['ab_group']) ? $_COOKIE['ab_group'] : 'unknown';
    
    // Log performance per group
    error_log(sprintf(
        '[Beast A/B] Group: %s, URL: %s, Cached: %s',
        $group,
        $url,
        $cached ? 'yes' : 'no'
    ));
});
```

## Troubleshooting

### Too Aggressive

**Symptoms**: Stale content, high cache invalidation

**Solution**:
```php
// Increase threshold
'beast_threshold' => 80,  // Was 50

// Shorten TTL
'cache_ttl' => 1800,  // Was 3600

// Add to blacklist
'beast_blacklist' => [
    '/news/*',  // Frequently updated
]
```

### Not Aggressive Enough

**Symptoms**: Low cache hit rate, many cache misses

**Solution**:
```php
// Decrease threshold
'beast_threshold' => 30,  // Was 50

// Add to whitelist
'beast_whitelist' => [
    '/blog/*',
    '/category/*',
]

// Increase time window
'beast_time_window' => 7200,  // Was 3600
```

## Next Steps

- [Cache Control](/config/cache-control)
- [Traffic Analysis](/features/beast-mode)
- [Performance Monitoring](/dev/testing)
