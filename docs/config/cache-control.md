# Cache Control

Fine-grained control over cache behavior, invalidation, and optimization.

## Cache Invalidation

### Automatic Invalidation

SpeedMate automatically invalidates cache when:

```php
// Post/page updates
add_action('save_post', function($post_id) {
    \SpeedMate\Cache\StaticCache::instance()->flush_url(
        get_permalink($post_id)
    );
});

// Comment posted
add_action('comment_post', function($comment_id, $approved) {
    if ($approved === 1) {
        $comment = get_comment($comment_id);
        \SpeedMate\Cache\StaticCache::instance()->flush_url(
            get_permalink($comment->comment_post_ID)
        );
    }
}, 10, 2);

// Theme switched
add_action('switch_theme', function() {
    \SpeedMate\Cache\StaticCache::instance()->flush_all();
});
```

### Manual Invalidation

```php
// Flush single URL
\SpeedMate\Cache\StaticCache::instance()->flush_url('https://site.com/page');

// Flush by pattern
\SpeedMate\Cache\StaticCache::instance()->flush_pattern('/blog/*');

// Flush all
\SpeedMate\Cache\StaticCache::instance()->flush_all();
```

### Selective Invalidation

```php
add_action('woocommerce_product_set_stock', function($product) {
    // Only flush product page, not entire cache
    $cache = \SpeedMate\Cache\StaticCache::instance();
    $cache->flush_url(get_permalink($product->get_id()));
    
    // Also flush parent categories
    $categories = get_the_terms($product->get_id(), 'product_cat');
    foreach ($categories as $cat) {
        $cache->flush_url(get_term_link($cat));
    }
});
```

## Cache Exclusions

### URL-Based Exclusions

```php
add_filter('speedmate_cache_exclude_urls', function($exclude) {
    // Exclude specific URLs
    $exclude[] = '/checkout';
    $exclude[] = '/cart';
    $exclude[] = '/my-account/*';
    
    // Exclude by query string
    $exclude[] = '/*?preview=*';
    $exclude[] = '/*?customize_*';
    
    return $exclude;
});
```

### Cookie-Based Exclusions

```php
add_filter('speedmate_cache_exclude_cookies', function($exclude) {
    // Don't cache if these cookies present
    $exclude[] = 'wordpress_logged_in_*';
    $exclude[] = 'woocommerce_cart_hash';
    $exclude[] = 'wp_woocommerce_session_*';
    
    return $exclude;
});
```

### User Agent Exclusions

```php
add_filter('speedmate_cache_exclude_user_agents', function($exclude) {
    // Don't cache for these user agents
    $exclude[] = 'bot';
    $exclude[] = 'crawler';
    $exclude[] = 'spider';
    $exclude[] = 'lighthouse';
    
    return $exclude;
});
```

## Cache Variants

### Mobile vs Desktop

```php
add_filter('speedmate_cache_key', function($key, $url) {
    // Create separate cache for mobile
    $is_mobile = wp_is_mobile();
    return $key . ($is_mobile ? '_mobile' : '_desktop');
}, 10, 2);
```

### Geolocation

```php
add_filter('speedmate_cache_key', function($key, $url) {
    // Separate cache per country
    $country = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? 'US';
    return $key . '_' . strtolower($country);
}, 10, 2);
```

### A/B Testing

```php
add_filter('speedmate_cache_key', function($key, $url) {
    // Separate cache per A/B test group
    $group = $_COOKIE['ab_test_group'] ?? 'default';
    return $key . '_ab_' . $group;
}, 10, 2);
```

## Cache Warming

### Automatic Warming

```php
add_action('save_post', function($post_id) {
    if (get_post_status($post_id) === 'publish') {
        // Warm cache after publish
        $url = get_permalink($post_id);
        wp_remote_get($url, [
            'blocking' => false,  // Don't wait for response
            'timeout' => 0.01,
        ]);
    }
});
```

### Scheduled Warming

```php
add_action('speedmate_daily_warm', function() {
    $urls = [
        home_url('/'),
        home_url('/blog'),
        home_url('/about'),
    ];
    
    foreach ($urls as $url) {
        wp_remote_get($url, ['blocking' => false]);
        usleep(500000);  // 500ms delay
    }
});

// Schedule if not already scheduled
if (!wp_next_scheduled('speedmate_daily_warm')) {
    wp_schedule_event(time(), 'daily', 'speedmate_daily_warm');
}
```

## Cache Compression

### Gzip Configuration

```php
update_option('speedmate_settings', [
    'gzip_enabled' => true,
    'gzip_level' => 6,  // 1-9, higher = more compression
]);
```

### Brotli Compression

```php
add_filter('speedmate_compression_method', function($method) {
    // Use Brotli if available
    if (function_exists('brotli_compress')) {
        return 'brotli';
    }
    return 'gzip';
});
```

## Cache Headers

### Custom Headers

```php
add_filter('speedmate_cache_headers', function($headers) {
    $headers['Cache-Control'] = 'public, max-age=3600';
    $headers['Vary'] = 'Accept-Encoding, User-Agent';
    $headers['X-Content-Type-Options'] = 'nosniff';
    
    return $headers;
});
```

### CDN Headers

```php
add_filter('speedmate_cache_headers', function($headers, $url) {
    // Set longer cache for CDN
    if (strpos($url, '/wp-content/uploads/') !== false) {
        $headers['Cache-Control'] = 'public, max-age=31536000, immutable';
    }
    
    return $headers;
}, 10, 2);
```

## Cache Storage

### Custom Storage Path

```php
define('SPEEDMATE_CACHE_DIR', WP_CONTENT_DIR . '/custom-cache/speedmate');
```

### Storage Cleanup

```php
add_filter('speedmate_cache_cleanup', function($should_cleanup) {
    // Cleanup if disk usage > 90%
    $disk_free = disk_free_space(SPEEDMATE_CACHE_DIR);
    $disk_total = disk_total_space(SPEEDMATE_CACHE_DIR);
    $usage = 1 - ($disk_free / $disk_total);
    
    return $usage > 0.9;
});
```

## Dynamic Content

### Fragment Caching

```php
// Cache dynamic widget separately
function render_popular_posts() {
    $cache_key = 'popular_posts';
    $cached = get_transient($cache_key);
    
    if ($cached !== false) {
        return $cached;
    }
    
    $posts = get_posts(['numberposts' => 5, 'orderby' => 'comment_count']);
    
    ob_start();
    foreach ($posts as $post) {
        echo '<li><a href="' . get_permalink($post) . '">' . $post->post_title . '</a></li>';
    }
    $output = ob_get_clean();
    
    set_transient($cache_key, $output, 3600);
    
    return $output;
}
```

### ESI (Edge Side Includes)

```php
add_filter('speedmate_cache_html', function($html) {
    // Replace dynamic content with ESI tags
    $html = preg_replace(
        '/<div id="user-widget">(.*?)<\/div>/s',
        '<esi:include src="/esi/user-widget" />',
        $html
    );
    
    return $html;
});
```

## Cache Preloading

### Sitemap-Based Preload

```php
function speedmate_preload_from_sitemap() {
    $sitemap_url = home_url('/sitemap.xml');
    $xml = simplexml_load_file($sitemap_url);
    
    foreach ($xml->url as $url) {
        $loc = (string) $url->loc;
        wp_remote_get($loc, ['blocking' => false]);
        usleep(500000);  // 500ms delay
    }
}

add_action('speedmate_preload_sitemap', 'speedmate_preload_from_sitemap');
```

### Priority-Based Preload

```php
function speedmate_preload_priority() {
    $urls = [
        'high' => [
            home_url('/'),
            home_url('/about'),
        ],
        'medium' => [
            home_url('/blog'),
            home_url('/contact'),
        ],
        'low' => [
            home_url('/privacy'),
        ],
    ];
    
    foreach ($urls as $priority => $url_list) {
        $delay = ($priority === 'high') ? 100000 : 500000;
        
        foreach ($url_list as $url) {
            wp_remote_get($url, ['blocking' => false]);
            usleep($delay);
        }
    }
}
```

## Debugging

### Cache Status Headers

```php
add_filter('speedmate_cache_headers', function($headers, $cached) {
    $headers['X-Cache'] = $cached ? 'HIT' : 'MISS';
    $headers['X-Cache-Time'] = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
    
    if ($cached) {
        $headers['X-Cache-Age'] = time() - filemtime($cache_file);
    }
    
    return $headers;
}, 10, 2);
```

### Debug Logging

```php
add_action('speedmate_cache_decision', function($decision, $url, $reason) {
    error_log(sprintf(
        '[Cache] Decision: %s, URL: %s, Reason: %s',
        $decision,
        $url,
        $reason
    ));
}, 10, 3);
```

## Best Practices

1. **Invalidate Selectively**: Don't flush entire cache on small updates
2. **Use Fragments**: Cache dynamic widgets separately
3. **Monitor Hit Rate**: Aim for 90%+ hit rate
4. **Set Appropriate TTL**: Balance freshness vs performance
5. **Test Exclusions**: Verify user-specific content not cached

## Next Steps

- [Beast Mode Configuration](/config/beast-mode)
- [Settings Reference](/config/settings)
- [Performance Testing](/dev/testing)
