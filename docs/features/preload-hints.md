# Preload Hints

SpeedMate automatically adds resource hints (DNS prefetch, preconnect, preload) to optimize resource loading priority and reduce latency.

## Resource Hints Overview

| Hint Type | Purpose | Use Case |
|-----------|---------|----------|
| DNS Prefetch | Resolve domain DNS early | Third-party domains |
| Preconnect | Establish connection early | Critical third-party resources |
| Preload | Load resource ASAP | Critical fonts, CSS, images |
| Prefetch | Load resource for next page | Likely navigation targets |

## DNS Prefetch

Resolves DNS for third-party domains before they're needed:

```html
<link rel="dns-prefetch" href="//fonts.googleapis.com">
<link rel="dns-prefetch" href="//cdn.example.com">
```

### Automatic Detection

SpeedMate automatically adds DNS prefetch for:
- External stylesheets
- External scripts
- External images
- CDN resources

### Configuration

```php
update_option('speedmate_settings', [
    'dns_prefetch' => [
        'fonts.googleapis.com',
        'cdn.example.com',
        'analytics.google.com',
    ]
]);
```

### Custom Domains

```php
add_filter('speedmate_dns_prefetch', function($domains) {
    $domains[] = 'custom-cdn.com';
    $domains[] = 'third-party-api.com';
    return $domains;
});
```

## Preconnect

Establishes full connection (DNS + TCP + TLS) for critical resources:

```html
<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
<link rel="preconnect" href="https://cdn.example.com">
```

### When to Use

Use preconnect for:
- Critical CSS/JS from CDN
- Web fonts
- API endpoints
- Critical images

**Note**: Limit to 4-6 domains (browser limit)

### Configuration

```php
update_option('speedmate_settings', [
    'preconnect' => [
        'https://fonts.googleapis.com',
        'https://fonts.gstatic.com',
    ]
]);
```

## Preload

Loads critical resources with high priority:

```html
<link rel="preload" href="style.css" as="style">
<link rel="preload" href="font.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="hero.jpg" as="image">
```

### Automatic Preloading

SpeedMate preloads:
- Critical CSS
- Above-the-fold images
- Web fonts
- Hero images

### Resource Types

```php
update_option('speedmate_settings', [
    'preload' => [
        [
            'href' => '/wp-content/themes/theme/style.css',
            'as' => 'style'
        ],
        [
            'href' => '/wp-content/themes/theme/fonts/font.woff2',
            'as' => 'font',
            'type' => 'font/woff2',
            'crossorigin' => true
        ],
        [
            'href' => '/wp-content/uploads/hero.jpg',
            'as' => 'image'
        ]
    ]
]);
```

### Dynamic Preload

```php
add_filter('speedmate_preload_resources', function($resources) {
    if (is_front_page()) {
        $resources[] = [
            'href' => '/wp-content/uploads/hero.jpg',
            'as' => 'image',
            'importance' => 'high'
        ];
    }
    return $resources;
});
```

## Prefetch

Loads resources for likely next navigation:

```html
<link rel="prefetch" href="/next-page">
<link rel="prefetch" href="/blog/popular-post">
```

### Automatic Prefetch

SpeedMate prefetches:
- Next/previous post links
- Category/tag archives
- Popular pages
- Related posts

### Configuration

```php
update_option('speedmate_settings', [
    'prefetch_enabled' => true,
    'prefetch_on_hover' => true,      // Prefetch on link hover
    'prefetch_on_visible' => true,    // Prefetch visible links
]);
```

### Custom Prefetch

```php
add_filter('speedmate_prefetch_urls', function($urls) {
    // Prefetch top pages
    $urls[] = '/about';
    $urls[] = '/contact';
    $urls[] = '/services';
    
    // Prefetch next in series
    if (is_singular('course')) {
        $next = get_next_post();
        if ($next) {
            $urls[] = get_permalink($next);
        }
    }
    
    return $urls;
});
```

## Intelligent Loading

### Hover-Based Prefetch

Prefetch page when user hovers over link:

```javascript
// SpeedMate adds this automatically
document.querySelectorAll('a[href^="/"]').forEach(link => {
  link.addEventListener('mouseenter', () => {
    const href = link.getAttribute('href');
    if (!document.querySelector(`link[rel="prefetch"][href="${href}"]`)) {
      const prefetch = document.createElement('link');
      prefetch.rel = 'prefetch';
      prefetch.href = href;
      document.head.appendChild(prefetch);
    }
  }, { once: true });
});
```

### Intersection Observer Prefetch

Prefetch visible links:

```javascript
const observer = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      const link = entry.target;
      const href = link.getAttribute('href');
      // Prefetch logic
    }
  });
});

document.querySelectorAll('a[href^="/"]').forEach(link => {
  observer.observe(link);
});
```

## Font Optimization

### Web Font Preload

```html
<link rel="preload" href="/fonts/font.woff2" 
      as="font" type="font/woff2" crossorigin>
```

### Configuration

```php
update_option('speedmate_settings', [
    'font_preload' => [
        '/wp-content/themes/theme/fonts/main.woff2',
        '/wp-content/themes/theme/fonts/heading.woff2',
    ]
]);
```

### Font Display Strategy

```css
@font-face {
  font-family: 'Custom Font';
  src: url('font.woff2') format('woff2');
  font-display: swap; /* Show fallback immediately */
}
```

## Performance Impact

### Without Resource Hints

```
DNS Lookup: 50ms
TCP: 100ms
TLS: 200ms
Total: 350ms per domain
```

### With Preconnect

```
DNS Lookup: 0ms (already resolved)
TCP: 0ms (connection established)
TLS: 0ms (handshake complete)
Total: ~0ms (connection ready)
```

### Time Savings

| Resource Type | Without Hints | With Hints | Savings |
|---------------|---------------|------------|---------|
| External CSS  | 400ms         | 50ms       | 350ms   |
| Web Fonts     | 500ms         | 100ms      | 400ms   |
| CDN Images    | 300ms         | 50ms       | 250ms   |

## Best Practices

1. **Limit Preconnects**: Max 4-6 domains
2. **Preload Critical Only**: Hero image, main font, critical CSS
3. **Use DNS Prefetch Widely**: Low cost, high benefit
4. **Prefetch Smartly**: Base on user behavior
5. **Monitor Performance**: Track actual usage

### Priority Guidelines

**High Priority (Preload)**:
- Critical CSS
- Hero image
- Main web font

**Medium Priority (Preconnect)**:
- Font CDN
- Critical third-party JS
- Image CDN

**Low Priority (DNS Prefetch)**:
- Analytics
- Ads
- Social widgets

## Advanced Features

### Conditional Hints

```php
add_filter('speedmate_resource_hints', function($hints) {
    // Only preconnect to CDN on pages with images
    if (has_post_thumbnail()) {
        $hints['preconnect'][] = 'https://cdn.example.com';
    }
    
    // Prefetch shop on non-shop pages
    if (!is_shop()) {
        $hints['prefetch'][] = '/shop';
    }
    
    return $hints;
});
```

### User Agent Detection

```php
add_filter('speedmate_preload', function($enabled) {
    // Don't preload on slow connections
    if (isset($_SERVER['HTTP_DOWNLINK']) && 
        floatval($_SERVER['HTTP_DOWNLINK']) < 1.0) {
        return false;
    }
    return $enabled;
});
```

### A/B Testing

```php
add_filter('speedmate_prefetch_strategy', function($strategy) {
    // 50% get hover prefetch, 50% get visible prefetch
    return (rand(0, 1) === 0) ? 'hover' : 'visible';
});
```

## Monitoring

### Resource Timing API

```javascript
// Check if preload worked
performance.getEntriesByType('resource').forEach(resource => {
  console.log(`${resource.name}: ${resource.duration}ms`);
});

// Check connection timing
const timing = performance.getEntriesByType('navigation')[0];
console.log('DNS:', timing.domainLookupEnd - timing.domainLookupStart);
console.log('TCP:', timing.connectEnd - timing.connectStart);
```

### Chrome DevTools

1. Open DevTools
2. Network tab
3. Look for "Highest" priority resources
4. Check timing breakdown

## Troubleshooting

### Hints Not Applied

```bash
# Check if hints present in HTML
curl -s https://site.com | grep -E "(prefetch|preconnect|preload)"
```

### Unused Preloads

Chrome warning: "The resource was preloaded but not used"

**Solution**: Only preload resources actually used on page

### Too Many Preconnects

**Issue**: Browser limits preconnect to 6 domains

**Solution**: Prioritize most critical domains

## Next Steps

- [Critical CSS](/features/critical-css)
- [Traffic Warmer](/features/traffic-warmer)
- [Performance Testing](/dev/testing)
