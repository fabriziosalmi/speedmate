# REST API

Complete REST API reference for SpeedMate.

## Base URL

All API endpoints are prefixed with:

```
https://yoursite.com/wp-json/speedmate/v1/
```

## Authentication

SpeedMate uses WordPress nonce authentication for write operations:

```bash
# Get nonce
NONCE=$(curl -s https://site.com/wp-json/speedmate/v1/nonce \
  --cookie "wordpress_logged_in_xxx=...")

# Use nonce
curl -X POST https://site.com/wp-json/speedmate/v1/cache/flush \
  -H "X-WP-Nonce: $NONCE"
```

For read-only operations (stats, info), authentication is optional.

## Endpoints

### Cache Operations

#### Flush Cache

```http
POST /speedmate/v1/cache/flush
```

Flush cache by type or pattern.

**Request Body:**
```json
{
  "type": "all",
  "pattern": "/blog/*"
}
```

**Parameters:**
- `type` (string, optional): Cache type (`all`, `page`, `fragment`, `transient`)
- `pattern` (string, optional): URL pattern with wildcards

**Response:**
```json
{
  "success": true,
  "files_removed": 142,
  "dirs_removed": 8,
  "message": "Cache flushed successfully"
}
```

**cURL Example:**
```bash
curl -X POST https://site.com/wp-json/speedmate/v1/cache/flush \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: $NONCE" \
  -d '{"type":"page","pattern":"/blog/*"}'
```

#### Warm Cache

```http
POST /speedmate/v1/cache/warm
```

Pre-cache specific URLs.

**Request Body:**
```json
{
  "urls": [
    "https://site.com/",
    "https://site.com/about"
  ],
  "concurrent": 5
}
```

**Parameters:**
- `urls` (array, required): List of URLs to warm
- `concurrent` (int, optional): Concurrent requests (default: 5, max: 10)

**Response:**
```json
{
  "success": true,
  "warmed": 2,
  "failed": 0,
  "duration": 1.2,
  "results": [
    {
      "url": "https://site.com/",
      "status": "success",
      "time": 0.245
    },
    {
      "url": "https://site.com/about",
      "status": "success",
      "time": 0.189
    }
  ]
}
```

**cURL Example:**
```bash
curl -X POST https://site.com/wp-json/speedmate/v1/cache/warm \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: $NONCE" \
  -d '{"urls":["https://site.com/","https://site.com/about"]}'
```

### Statistics

#### Get Cache Stats

```http
GET /speedmate/v1/stats
```

Retrieve cache statistics.

**Response:**
```json
{
  "total_files": 1245,
  "total_size": 47431680,
  "cache_hits": 8932,
  "cache_misses": 421,
  "hit_rate": 95.5,
  "avg_response_time": 12,
  "beast_mode": {
    "active": true,
    "auto_cached_pages": 142,
    "traffic_hits": 1245
  }
}
```

**cURL Example:**
```bash
curl https://site.com/wp-json/speedmate/v1/stats
```

#### Get Info

```http
GET /speedmate/v1/info
```

Get plugin information and status.

**Response:**
```json
{
  "version": "0.3.1",
  "status": "active",
  "mode": "beast",
  "cache_dir": "/var/www/html/wp-content/cache/speedmate",
  "advanced_cache": true,
  "multisite": false,
  "settings": {
    "cache_ttl": 3600,
    "webp_enabled": true,
    "critical_css_enabled": true,
    "lazy_load": true,
    "preload_enabled": true,
    "logging_enabled": false
  }
}
```

**cURL Example:**
```bash
curl https://site.com/wp-json/speedmate/v1/info
```

### Batch Operations

#### Batch Warm

```http
POST /speedmate/v1/batch/warm
```

Warm multiple URLs with advanced options.

**Request Body:**
```json
{
  "urls": [
    "https://site.com/",
    "https://site.com/about",
    "https://site.com/blog"
  ],
  "options": {
    "concurrent": 5,
    "delay": 100,
    "user_agent": "SpeedMate/1.0",
    "follow_redirects": true
  }
}
```

**Response:**
```json
{
  "success": true,
  "total": 3,
  "warmed": 3,
  "failed": 0,
  "duration": 2.1,
  "results": [...]
}
```

#### Batch Flush

```http
POST /speedmate/v1/batch/flush
```

Flush multiple patterns.

**Request Body:**
```json
{
  "patterns": [
    "/blog/*",
    "/category/*",
    "/tag/*"
  ]
}
```

**Response:**
```json
{
  "success": true,
  "patterns_flushed": 3,
  "files_removed": 245,
  "dirs_removed": 12
}
```

## Error Handling

All endpoints return consistent error responses:

```json
{
  "code": "cache_flush_failed",
  "message": "Failed to flush cache: Permission denied",
  "data": {
    "status": 500
  }
}
```

**Error Codes:**
- `invalid_nonce` (403): Invalid or missing nonce
- `invalid_params` (400): Invalid request parameters
- `cache_flush_failed` (500): Cache flush operation failed
- `cache_warm_failed` (500): Cache warming failed
- `permission_denied` (403): Insufficient permissions

**Example Error Handling:**
```javascript
fetch('https://site.com/wp-json/speedmate/v1/cache/flush', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-WP-Nonce': nonce
  },
  body: JSON.stringify({ type: 'all' })
})
.then(response => {
  if (!response.ok) {
    return response.json().then(err => {
      throw new Error(err.message);
    });
  }
  return response.json();
})
.then(data => console.log('Success:', data))
.catch(error => console.error('Error:', error));
```

## Rate Limiting

SpeedMate implements rate limiting to prevent abuse:

- **Cache Flush**: 10 requests per minute
- **Cache Warm**: 5 requests per minute (limited by URL count)
- **Stats/Info**: No rate limit

Rate limit headers:
```
X-RateLimit-Limit: 10
X-RateLimit-Remaining: 7
X-RateLimit-Reset: 1640000000
```

## JavaScript Client

Example JavaScript client implementation:

```javascript
class SpeedMateAPI {
  constructor(baseUrl, nonce) {
    this.baseUrl = baseUrl;
    this.nonce = nonce;
  }

  async flush(type = 'all', pattern = null) {
    const response = await fetch(`${this.baseUrl}/cache/flush`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': this.nonce
      },
      body: JSON.stringify({ type, pattern })
    });
    return response.json();
  }

  async warm(urls, concurrent = 5) {
    const response = await fetch(`${this.baseUrl}/cache/warm`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': this.nonce
      },
      body: JSON.stringify({ urls, concurrent })
    });
    return response.json();
  }

  async stats() {
    const response = await fetch(`${this.baseUrl}/stats`);
    return response.json();
  }

  async info() {
    const response = await fetch(`${this.baseUrl}/info`);
    return response.json();
  }
}

// Usage
const api = new SpeedMateAPI(
  'https://site.com/wp-json/speedmate/v1',
  document.querySelector('#_wpnonce').value
);

// Flush cache
await api.flush('page', '/blog/*');

// Warm URLs
await api.warm([
  'https://site.com/',
  'https://site.com/about'
]);

// Get stats
const stats = await api.stats();
console.log('Cache hit rate:', stats.hit_rate + '%');
```

## Next Steps

- [WP-CLI Commands](/api/wp-cli)
- [Hooks & Filters](/api/hooks)
- [Development Guide](/dev/architecture)
