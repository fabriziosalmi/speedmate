# Traffic Warmer

SpeedMate's Traffic Warmer proactively pre-caches pages based on user navigation patterns, ensuring cache is always warm for likely next pages.

## How It Works

```
User visits Page A
    ↓
Analyze navigation patterns
    ↓
Predict likely next pages
    ↓
Pre-cache in background
    ↓
User clicks to Page B (already cached)
```

## Features

### Pattern Learning

Traffic Warmer learns from:
- User navigation sequences
- Common paths through site
- Time spent on pages
- Scroll depth
- Link visibility

### Predictive Caching

Automatically pre-caches:
- Related posts
- Next/previous pagination
- Category archives
- Popular pages
- Recently viewed

### Background Processing

- Non-blocking: Doesn't slow down current page
- Throttled: Respects server resources
- Scheduled: Can run via cron
- Priority-based: Caches most likely pages first

## Configuration

### Enable Traffic Warmer

```php
update_option('speedmate_settings', [
    'traffic_warmer_enabled' => true,
    'warm_on_visit' => true,           // Warm during user visit
    'warm_on_idle' => true,            // Warm during idle time
    'warm_concurrent' => 3,            // Concurrent warm requests
    'warm_delay' => 500,               // Delay between requests (ms)
]);
```

### Via WordPress Admin

1. Navigate to **Settings > SpeedMate**
2. Enable **Traffic Warmer**
3. Configure warming strategy
4. Save changes

## Warming Strategies

### 1. On-Visit Warming

Warms cache while user browses:

```php
update_option('speedmate_settings', [
    'warm_strategy' => 'on-visit',
    'warm_depth' => 2,                 // Levels deep to warm
    'warm_max_urls' => 10,             // Max URLs per visit
]);
```

### 2. Scheduled Warming

Warms cache on schedule:

```php
update_option('speedmate_settings', [
    'warm_strategy' => 'scheduled',
    'warm_cron_schedule' => 'hourly',  // hourly, daily, weekly
]);
```

### 3. Idle Warming

Warms during server idle time:

```php
update_option('speedmate_settings', [
    'warm_strategy' => 'idle',
    'warm_idle_threshold' => 10,      // Start if < 10 requests/min
]);
```

## URL Selection

### Automatic Selection

Traffic Warmer automatically identifies:

**Navigation Patterns:**
```
Home → Blog → Post → Related Post
Category → Post → Next Post
Shop → Product → Cart
```

**Popular Pages:**
- Top 10 most visited
- Recently updated
- Trending posts

**Related Content:**
- Same category
- Same tags  
- Same author
- Related products (WooCommerce)

### Manual URL List

```php
update_option('speedmate_settings', [
    'warm_urls' => [
        '/',
        '/about',
        '/contact',
        '/blog',
        '/shop',
    ]
]);
```

### Dynamic URL Generation

```php
add_filter('speedmate_warm_urls', function($urls) {
    // Add all published posts
    $posts = get_posts(['numberposts' => 100]);
    foreach ($posts as $post) {
        $urls[] = get_permalink($post);
    }
    
    // Add all categories
    $categories = get_categories();
    foreach ($categories as $cat) {
        $urls[] = get_category_link($cat);
    }
    
    return $urls;
});
```

## WP-CLI Integration

### Manual Warming

```bash
# Warm specific URLs
wp speedmate warm --urls=https://site.com/,https://site.com/about

# Warm from file
wp speedmate warm --file=urls.txt

# Warm with concurrent requests
wp speedmate warm --file=urls.txt --concurrent=5
```

### Automated Warming

```bash
# Cron job - warm cache daily
0 3 * * * wp speedmate warm --file=/var/www/urls.txt --path=/var/www/html

# After deployment
wp speedmate warm --urls=$(wp post list --post_type=page --field=url --format=csv)
```

## Intelligent Features

### Priority Queue

URLs prioritized by:

1. **Visit frequency**: Most visited = highest priority
2. **Recency**: Recently viewed = higher priority
3. **Depth**: Homepage > category > post
4. **Update time**: Recently updated = higher priority

```php
add_filter('speedmate_warm_priority', function($priority, $url) {
    // Boost homepage priority
    if ($url === home_url('/')) {
        return 100;
    }
    
    // Boost shop pages
    if (strpos($url, '/shop') !== false) {
        return 80;
    }
    
    return $priority;
}, 10, 2);
```

### Navigation Prediction

Machine learning-based prediction:

```javascript
// Track navigation patterns
{
  "pattern": "/blog → /blog/post-1",
  "frequency": 142,
  "confidence": 0.89
}

// Predicted next pages
[
  { "url": "/blog/post-2", "probability": 0.76 },
  { "url": "/blog/post-3", "probability": 0.54 },
  { "url": "/category/tech", "probability": 0.42 }
]
```

### Hover Intent

Pre-cache on link hover:

```javascript
document.querySelectorAll('a[href^="/"]').forEach(link => {
  let timeout;
  
  link.addEventListener('mouseenter', () => {
    timeout = setTimeout(() => {
      // Warm cache after 200ms hover
      fetch(`/wp-json/speedmate/v1/cache/warm`, {
        method: 'POST',
        body: JSON.stringify({ urls: [link.href] })
      });
    }, 200);
  });
  
  link.addEventListener('mouseleave', () => {
    clearTimeout(timeout);
  });
});
```

## Performance Impact

### Cache Hit Improvement

**Without Traffic Warmer:**
```
Cold Cache Hit Rate: 65%
Warm Cache Hit Rate: 85%
Average: 75%
```

**With Traffic Warmer:**
```
Cold Cache Hit Rate: 95%
Warm Cache Hit Rate: 98%
Average: 96.5%
```

### Navigation Speed

| Scenario | Without Warmer | With Warmer | Improvement |
|----------|----------------|-------------|-------------|
| Homepage → Blog | 450ms | 3ms | 99.3% |
| Blog → Post | 520ms | 3ms | 99.4% |
| Post → Related | 480ms | 3ms | 99.4% |

## Resource Management

### Throttling

```php
update_option('speedmate_settings', [
    'warm_throttle' => [
        'max_concurrent' => 3,         // Max parallel requests
        'delay_between' => 500,        // 500ms delay
        'max_cpu' => 70,              // Stop if CPU > 70%
        'max_memory' => 80,           // Stop if memory > 80%
    ]
]);
```

### Bandwidth Control

```php
add_filter('speedmate_warm_bandwidth', function($limit) {
    // Limit to 1MB/s during business hours
    $hour = (int) date('H');
    if ($hour >= 9 && $hour <= 17) {
        return 1024 * 1024; // 1MB/s
    }
    
    // No limit during off-hours
    return 0;
});
```

## Monitoring

### Dashboard Widget

```
Traffic Warmer Status
━━━━━━━━━━━━━━━━━━━━
Status:        Active
Warmed:        1,245 pages
Queue:         23 pending
Success Rate:  98.4%
Avg Time:      245ms
```

### Logging

```php
update_option('speedmate_settings', [
    'logging_enabled' => true,
    'log_warm_operations' => true,
]);
```

Log format:
```json
{
  "event": "cache_warmed",
  "url": "/blog/post-name",
  "status": "success",
  "time": 245,
  "size": 45672,
  "timestamp": "2026-01-22T10:30:00Z"
}
```

## Best Practices

1. **Start Conservative**: Begin with 3 concurrent requests
2. **Monitor Resource Usage**: Watch CPU and memory
3. **Warm During Off-Hours**: Schedule heavy warming at night
4. **Prioritize Critical Pages**: Focus on high-traffic pages
5. **Track Success Rate**: Aim for >95% success rate

## Advanced Features

### A/B Testing

```php
add_filter('speedmate_warm_strategy', function($strategy) {
    // 50% on-visit, 50% scheduled
    return (rand(0, 1) === 0) ? 'on-visit' : 'scheduled';
});
```

### Geographic Distribution

```php
add_filter('speedmate_warm_urls', function($urls) {
    // Warm different URLs based on user location
    $country = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? 'US';
    
    if ($country === 'US') {
        $urls[] = '/us/store-locator';
    } elseif ($country === 'UK') {
        $urls[] = '/uk/store-locator';
    }
    
    return $urls;
});
```

## Troubleshooting

### Warming Not Working

```bash
# Check warmer status
wp speedmate info | grep -A5 "Traffic Warmer"

# Check queue
wp eval "print_r(\SpeedMate\Cache\TrafficWarmer::instance()->get_queue());"

# Check logs
tail -f wp-content/speedmate.log | grep warm
```

### High Resource Usage

1. Reduce concurrent requests
2. Increase delay between requests
3. Add CPU/memory limits
4. Warm during off-peak hours

### Low Success Rate

1. Check server logs for errors
2. Verify URLs are accessible
3. Check for rate limiting
4. Review exclude rules

## Next Steps

- [Beast Mode Integration](/features/beast-mode)
- [Static Cache](/features/static-cache)
- [Performance Monitoring](/dev/testing)
