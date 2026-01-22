# Hooks & Filters

Complete reference for SpeedMate WordPress hooks and filters.

## Actions

### speedmate_cache_flushed

Fires after cache has been flushed.

```php
add_action('speedmate_cache_flushed', function($type, $pattern) {
    // Log flush event
    error_log("Cache flushed: type={$type}, pattern={$pattern}");
}, 10, 2);
```

**Parameters:**
- `$type` (string): Type of flush (`all`, `page`, `fragment`)
- `$pattern` (string|null): URL pattern if selective flush

### speedmate_cache_warmed

Fires after cache has been warmed.

```php
add_action('speedmate_cache_warmed', function($urls, $results) {
    foreach ($results as $result) {
        if ($result['status'] === 'success') {
            // Track successful warming
        }
    }
}, 10, 2);
```

**Parameters:**
- `$urls` (array): URLs that were warmed
- `$results` (array): Results with status and timing

### speedmate_beast_decision

Fires when Beast Mode makes a caching decision.

```php
add_action('speedmate_beast_decision', function($decision, $url, $score) {
    if ($decision === 'cache') {
        // Send to analytics
        send_to_analytics('beast_cached', [
            'url' => $url,
            'score' => $score
        ]);
    }
}, 10, 3);
```

**Parameters:**
- `$decision` (string): `cache` or `skip`
- `$url` (string): URL being evaluated
- `$score` (int): Calculated score

### speedmate_webp_converted

Fires after image converted to WebP.

```php
add_action('speedmate_webp_converted', function($source, $webp, $quality) {
    error_log("WebP created: {$webp} (quality: {$quality})");
}, 10, 3);
```

**Parameters:**
- `$source` (string): Original image path
- `$webp` (string): WebP image path
- `$quality` (int): Compression quality used

### speedmate_critical_css_generated

Fires after critical CSS extracted.

```php
add_action('speedmate_critical_css_generated', function($template, $css, $size) {
    error_log("Critical CSS for {$template}: " . strlen($css) . " bytes");
}, 10, 3);
```

**Parameters:**
- `$template` (string): Template name
- `$css` (string): Critical CSS content
- `$size` (int): Size in bytes

## Filters

### speedmate_cache_key

Modify cache key generation.

```php
add_filter('speedmate_cache_key', function($key, $url) {
    // Add user role to cache key
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        $key .= '_role_' . implode('_', $user->roles);
    }
    return $key;
}, 10, 2);
```

**Parameters:**
- `$key` (string): Generated cache key
- `$url` (string): URL being cached

**Return:** Modified cache key (string)

### speedmate_cache_ttl

Modify cache TTL per URL.

```php
add_filter('speedmate_cache_ttl', function($ttl, $url) {
    // Longer TTL for static pages
    if (preg_match('#^/(about|contact|privacy)#', $url)) {
        return 86400;  // 24 hours
    }
    
    // Shorter TTL for news
    if (preg_match('#^/news/#', $url)) {
        return 1800;  // 30 minutes
    }
    
    return $ttl;
}, 10, 2);
```

**Parameters:**
- `$ttl` (int): Default TTL in seconds
- `$url` (string): URL being cached

**Return:** Modified TTL (int)

### speedmate_cache_exclude_urls

Exclude URLs from caching.

```php
add_filter('speedmate_cache_exclude_urls', function($exclude) {
    $exclude[] = '/checkout/*';
    $exclude[] = '/cart/*';
    $exclude[] = '/my-account/*';
    return $exclude;
});
```

**Parameters:**
- `$exclude` (array): Current exclusion patterns

**Return:** Modified exclusion array

### speedmate_cache_exclude_cookies

Exclude based on cookies.

```php
add_filter('speedmate_cache_exclude_cookies', function($exclude) {
    $exclude[] = 'woocommerce_cart_hash';
    $exclude[] = 'wp_woocommerce_session_*';
    return $exclude;
});
```

**Parameters:**
- `$exclude` (array): Current cookie patterns

**Return:** Modified array

### speedmate_beast_threshold

Modify Beast Mode threshold.

```php
add_filter('speedmate_beast_threshold', function($threshold) {
    $hour = (int) date('H');
    
    // Lower during business hours
    if ($hour >= 9 && $hour <= 17) {
        return $threshold * 0.7;
    }
    
    return $threshold;
});
```

**Parameters:**
- `$threshold` (int): Current threshold

**Return:** Modified threshold (int)

### speedmate_beast_score

Modify page score calculation.

```php
add_filter('speedmate_beast_score', function($score, $url, $data) {
    // Boost important pages
    if (preg_match('#^/important/#', $url)) {
        $score *= 1.5;
    }
    
    return $score;
}, 10, 3);
```

**Parameters:**
- `$score` (int): Calculated score
- `$url` (string): URL being scored
- `$data` (array): Traffic data (hits, rate, time)

**Return:** Modified score (int)

### speedmate_beast_whitelist

Modify Beast Mode whitelist.

```php
add_filter('speedmate_beast_whitelist', function($whitelist) {
    // Add all published posts
    $posts = get_posts(['numberposts' => 100]);
    foreach ($posts as $post) {
        $whitelist[] = parse_url(get_permalink($post), PHP_URL_PATH);
    }
    return $whitelist;
});
```

**Parameters:**
- `$whitelist` (array): Current whitelist patterns

**Return:** Modified array

### speedmate_webp_quality

Modify WebP quality per image.

```php
add_filter('speedmate_webp_quality', function($quality, $image_path) {
    // Higher quality for featured images
    if (strpos($image_path, 'featured') !== false) {
        return 95;
    }
    
    // Lower for thumbnails
    if (strpos($image_path, 'thumbnail') !== false) {
        return 75;
    }
    
    return $quality;
}, 10, 2);
```

**Parameters:**
- `$quality` (int): Default quality (0-100)
- `$image_path` (string): Image file path

**Return:** Modified quality (int)

### speedmate_lazy_exclude

Exclude images from lazy loading.

```php
add_filter('speedmate_lazy_exclude', function($exclude) {
    $exclude[] = 'logo.png';
    $exclude[] = 'hero-*.jpg';
    $exclude[] = 'above-fold-*';
    return $exclude;
});
```

**Parameters:**
- `$exclude` (array): Current exclusion patterns

**Return:** Modified array

### speedmate_critical_css

Modify critical CSS content.

```php
add_filter('speedmate_critical_css', function($css, $template) {
    // Inject custom critical CSS
    $custom = file_get_contents(get_template_directory() . '/critical.css');
    return $css . "\n" . $custom;
}, 10, 2);
```

**Parameters:**
- `$css` (string): Extracted critical CSS
- `$template` (string): Template name

**Return:** Modified CSS (string)

### speedmate_critical_selectors

Modify selectors for critical CSS.

```php
add_filter('speedmate_critical_selectors', function($selectors) {
    // Always include these
    $selectors[] = '.header';
    $selectors[] = '.nav';
    $selectors[] = '.hero';
    
    // Exclude these
    $exclude = ['.footer', '.sidebar'];
    return array_diff($selectors, $exclude);
});
```

**Parameters:**
- `$selectors` (array): Current CSS selectors

**Return:** Modified array

### speedmate_preload_resources

Modify preload resources.

```php
add_filter('speedmate_preload_resources', function($resources) {
    if (is_front_page()) {
        $resources[] = [
            'href' => get_template_directory_uri() . '/css/home.css',
            'as' => 'style'
        ];
    }
    return $resources;
});
```

**Parameters:**
- `$resources` (array): Current preload resources

**Return:** Modified array

### speedmate_dns_prefetch

Modify DNS prefetch domains.

```php
add_filter('speedmate_dns_prefetch', function($domains) {
    $domains[] = 'custom-cdn.com';
    $domains[] = 'analytics.example.com';
    return $domains;
});
```

**Parameters:**
- `$domains` (array): Current domains

**Return:** Modified array

### speedmate_cached_html

Modify cached HTML before serving.

```php
add_filter('speedmate_cached_html', function($html, $url) {
    // Inject timestamp
    $html = str_replace(
        '</body>',
        '<!-- Cached: ' . date('c') . ' --></body>',
        $html
    );
    
    return $html;
}, 10, 2);
```

**Parameters:**
- `$html` (string): Cached HTML content
- `$url` (string): URL being served

**Return:** Modified HTML (string)

### speedmate_cache_headers

Modify cache response headers.

```php
add_filter('speedmate_cache_headers', function($headers, $url) {
    $headers['Cache-Control'] = 'public, max-age=3600';
    $headers['Vary'] = 'Accept-Encoding';
    return $headers;
}, 10, 2);
```

**Parameters:**
- `$headers` (array): Current headers
- `$url` (string): URL being served

**Return:** Modified headers array

## Usage Examples

### Custom Cache Strategy

```php
// Exclude logged-in users from cache
add_filter('speedmate_cache_exclude_cookies', function($exclude) {
    if (is_user_logged_in()) {
        $exclude[] = '*';  // Exclude all
    }
    return $exclude;
});

// But cache specific pages even for logged-in users
add_filter('speedmate_cache_exclude_urls', function($exclude) {
    if (is_user_logged_in()) {
        // Remove homepage from exclusions
        $exclude = array_diff($exclude, ['/']);
    }
    return $exclude;
});
```

### Performance Monitoring

```php
// Track cache performance
add_action('speedmate_cache_served', function($url, $cached) {
    $time = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
    
    send_to_analytics('page_served', [
        'url' => $url,
        'cached' => $cached,
        'time' => $time,
    ]);
});
```

### Dynamic Content Handling

```php
// Replace dynamic content with placeholders
add_filter('speedmate_cached_html', function($html) {
    // Replace user-specific content
    $html = str_replace(
        '{{USERNAME}}',
        is_user_logged_in() ? wp_get_current_user()->display_name : 'Guest',
        $html
    );
    
    return $html;
});
```

## Next Steps

- [REST API Reference](/api/rest-api)
- [WP-CLI Commands](/api/wp-cli)
- [Development Guide](/dev/architecture)
