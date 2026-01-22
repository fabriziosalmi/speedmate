# WP-CLI Commands

Complete reference for SpeedMate WP-CLI commands.

## Overview

SpeedMate provides comprehensive WP-CLI integration for cache management, automation, and maintenance.

```bash
wp speedmate <command> [options]
```

## Available Commands

### flush

Flush cache by type or pattern.

```bash
wp speedmate flush [--type=<type>] [--pattern=<pattern>]
```

**Options:**

- `--type=<type>`: Cache type to flush
  - `all` - All cache files (default)
  - `page` - Page cache only
  - `fragment` - Dynamic fragments only
  - `transient` - Transients only

- `--pattern=<pattern>`: URL pattern to match
  - Supports wildcards: `*`, `?`
  - Example: `--pattern=/blog/*`

**Examples:**

```bash
# Flush all cache
wp speedmate flush

# Flush page cache only
wp speedmate flush --type=page

# Flush blog posts
wp speedmate flush --pattern=/blog/*

# Flush specific URL
wp speedmate flush --pattern=/about
```

**Output:**
```
✓ Cache flushed successfully
Files removed: 142
Directories removed: 8
```

### warm

Pre-cache URLs proactively.

```bash
wp speedmate warm [--urls=<urls>] [--file=<file>] [--concurrent=<num>]
```

**Options:**

- `--urls=<urls>`: Comma-separated list of URLs
- `--file=<file>`: Path to file with URLs (one per line)
- `--concurrent=<num>`: Concurrent requests (default: 5, max: 10)

**Examples:**

```bash
# Warm specific URLs
wp speedmate warm --urls=https://site.com/,https://site.com/about

# Warm from file
wp speedmate warm --file=urls.txt

# Warm with 10 concurrent requests
wp speedmate warm --file=urls.txt --concurrent=10
```

**urls.txt format:**
```
https://site.com/
https://site.com/about
https://site.com/blog
https://site.com/contact
```

**Output:**
```
Warming cache...
✓ https://site.com/ (245ms)
✓ https://site.com/about (189ms)
✓ https://site.com/blog (312ms)
Total: 3 URLs warmed in 1.2s
```

### stats

Display cache statistics.

```bash
wp speedmate stats [--format=<format>]
```

**Options:**

- `--format=<format>`: Output format
  - `table` - Table format (default)
  - `json` - JSON format
  - `yaml` - YAML format
  - `csv` - CSV format

**Examples:**

```bash
# Table format
wp speedmate stats

# JSON format
wp speedmate stats --format=json

# Export to CSV
wp speedmate stats --format=csv > stats.csv
```

**Output (table):**
```
+------------------+--------+
| Metric           | Value  |
+------------------+--------+
| Total Files      | 1,245  |
| Total Size       | 45.2MB |
| Cache Hits       | 8,932  |
| Cache Misses     | 421    |
| Hit Rate         | 95.5%  |
| Avg Response     | 12ms   |
+------------------+--------+
```

**Output (json):**
```json
{
  "total_files": 1245,
  "total_size": 47431680,
  "cache_hits": 8932,
  "cache_misses": 421,
  "hit_rate": 95.5,
  "avg_response_time": 12
}
```

### info

Display plugin information and status.

```bash
wp speedmate info
```

**Output:**
```
SpeedMate v0.3.1
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Status:           Active
Mode:             beast
Cache Directory:  /var/www/html/wp-content/cache/speedmate
Advanced Cache:   Enabled
Multisite:        No

Settings:
  Cache TTL:      3600s
  WebP:           Enabled
  Critical CSS:   Enabled
  Lazy Load:      Enabled
  Preload Hints:  Enabled
  Logging:        Disabled

Beast Mode:
  Whitelist:      5 patterns
  Traffic Hits:   1,245
  Auto-cached:    142 pages
```

### gc

Run garbage collection manually.

```bash
wp speedmate gc [--force]
```

**Options:**

- `--force`: Force immediate cleanup, ignore schedules

**Examples:**

```bash
# Normal garbage collection
wp speedmate gc

# Force immediate cleanup
wp speedmate gc --force
```

**Output:**
```
Running garbage collection...
✓ Deleted 45 expired transients
✓ Deleted 12 spam comments
✓ Deleted 234 post revisions
✓ Deleted 56 orphaned postmeta rows
Completed in 2.3s
```

## Automation Examples

### Cron Jobs

```bash
# Flush cache daily at 3am
0 3 * * * wp speedmate flush --path=/var/www/html

# Warm cache every 6 hours
0 */6 * * * wp speedmate warm --file=/var/www/urls.txt --path=/var/www/html

# Weekly garbage collection
0 2 * * 0 wp speedmate gc --path=/var/www/html
```

### CI/CD Integration

```bash
# Deploy script
#!/bin/bash
set -e

# Deploy code
git pull origin main

# Clear cache
wp speedmate flush

# Warm critical pages
wp speedmate warm --urls=https://site.com/,https://site.com/shop

# Verify
wp speedmate stats
```

### GitHub Actions

```yaml
name: Warm Cache
on:
  schedule:
    - cron: '0 */6 * * *'

jobs:
  warm:
    runs-on: ubuntu-latest
    steps:
      - name: Warm cache
        run: |
          ssh user@server "wp speedmate warm --file=/var/www/urls.txt --path=/var/www/html"
```

### Multisite

```bash
# Flush cache for all sites
wp site list --field=url | xargs -I {} wp speedmate flush --url={}

# Warm cache for all sites
for site in $(wp site list --field=url); do
  wp speedmate warm --url=$site --urls=$site,$site/about,$site/contact
done

# Stats for all sites
wp site list --field=url | while read url; do
  echo "=== $url ==="
  wp speedmate stats --url=$url
done
```

## Exit Codes

- `0`: Success
- `1`: General error
- `2`: Invalid arguments
- `3`: Cache operation failed
- `4`: Permission denied

## Error Handling

```bash
# Check exit code
wp speedmate flush
if [ $? -eq 0 ]; then
  echo "Cache flushed successfully"
else
  echo "Cache flush failed"
  exit 1
fi

# With error output
wp speedmate flush 2>&1 | tee cache-flush.log
```

## Best Practices

1. **Use `--path` in cron**: Always specify `--path` when running from cron
2. **Limit concurrent warming**: Don't exceed 10 concurrent requests
3. **Monitor performance**: Run stats after major operations
4. **Automate garbage collection**: Schedule weekly GC runs
5. **Log operations**: Redirect output to logs for debugging

## Next Steps

- [REST API Reference](/api/rest-api)
- [Hooks & Filters](/api/hooks)
- [Automation Examples](/dev/automation)
