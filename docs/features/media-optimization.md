# Media Optimization

SpeedMate provides automatic image optimization with next-gen format conversion, lazy loading, and responsive images.

## Features

### WebP Conversion

Automatic WebP conversion with fallback support:

- **On-the-Fly Conversion**: Generates WebP versions automatically
- **Fallback Support**: Serves original format if WebP not supported
- **Responsive Images**: Creates multiple sizes for srcset
- **Smart Detection**: Checks browser WebP support

### AVIF Support

Next-generation image format with better compression:

- **Smaller File Sizes**: 30-50% smaller than WebP
- **Better Quality**: Superior compression at same quality
- **Gradual Adoption**: Serves to supported browsers only

### Lazy Loading

Deferred loading of off-screen images:

- **Native Lazy Loading**: Uses `loading="lazy"` attribute
- **Intersection Observer**: Polyfill for older browsers
- **IFrame Support**: Lazy loads embedded videos
- **Threshold Control**: Configurable load distance

## WebP Conversion

### How It Works

1. Image uploaded to WordPress
2. SpeedMate generates WebP version
3. HTML rewritten to use `<picture>` element
4. Browser selects best format

### Example Output

**Before:**
```html
<img src="/uploads/2026/01/image.jpg" alt="Example">
```

**After:**
```html
<picture>
  <source type="image/avif" srcset="/uploads/2026/01/image.avif">
  <source type="image/webp" srcset="/uploads/2026/01/image.webp">
  <img src="/uploads/2026/01/image.jpg" alt="Example" loading="lazy">
</picture>
```

### Configuration

```php
update_option('speedmate_settings', [
    'webp_enabled' => true,
    'avif_enabled' => true,
    'webp_quality' => 85,      // 0-100
    'preserve_original' => true, // Keep original files
]);
```

### Batch Conversion

Convert existing images:

```bash
# Convert all images
wp media regenerate --yes

# Convert specific directory
find wp-content/uploads/2026 -name "*.jpg" -exec \
  cwebp -q 85 {} -o {}.webp \;
```

## Lazy Loading

### Configuration

```php
update_option('speedmate_settings', [
    'lazy_load' => true,
    'lazy_threshold' => 200,    // Load 200px before visible
    'lazy_placeholder' => 'data:image/svg+xml,...', // Placeholder
    'lazy_skip_first' => 2,     // Don't lazy load first 2 images
]);
```

### Exclude Specific Images

```php
add_filter('speedmate_lazy_exclude', function($exclude) {
    // Don't lazy load logo
    $exclude[] = 'logo.png';
    
    // Don't lazy load above-the-fold images
    $exclude[] = 'hero-*.jpg';
    
    return $exclude;
});
```

### Custom Implementation

```javascript
// SpeedMate automatically adds this, but you can customize
document.addEventListener('DOMContentLoaded', function() {
  if ('IntersectionObserver' in window) {
    const lazyImages = document.querySelectorAll('img[loading="lazy"]');
    const imageObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const img = entry.target;
          img.src = img.dataset.src;
          imageObserver.unobserve(img);
        }
      });
    });
    
    lazyImages.forEach(img => imageObserver.observe(img));
  }
});
```

## Responsive Images

### Automatic Srcset Generation

```php
// Enable responsive images
update_option('speedmate_settings', [
    'responsive_images' => true,
    'image_sizes' => [
        'thumbnail' => 150,
        'medium' => 300,
        'large' => 1024,
        'full' => 2048,
    ]
]);
```

### Output Example

```html
<img 
  src="/uploads/2026/01/image-1024.webp"
  srcset="
    /uploads/2026/01/image-300.webp 300w,
    /uploads/2026/01/image-600.webp 600w,
    /uploads/2026/01/image-1024.webp 1024w,
    /uploads/2026/01/image-2048.webp 2048w
  "
  sizes="(max-width: 600px) 100vw, (max-width: 1024px) 50vw, 33vw"
  alt="Example"
  loading="lazy"
>
```

## Image Optimization Pipeline

```
Upload Image
    ↓
WordPress Processing
    ↓
SpeedMate Optimization
    ↓
├── Generate WebP
├── Generate AVIF
├── Create Responsive Sizes
├── Add Lazy Loading
└── Optimize Metadata
    ↓
Serve to Browser
```

## Performance Impact

### File Size Comparison

| Format | Size   | Reduction |
|--------|--------|-----------|
| JPG    | 500KB  | Baseline  |
| WebP   | 250KB  | 50%       |
| AVIF   | 175KB  | 65%       |

### Load Time Impact

**Without Optimization:**
- Total Images: 2.5MB
- Load Time: 4.2s (3G)
- LCP: 3.8s

**With Optimization:**
- Total Images: 850KB (WebP + lazy load)
- Load Time: 1.4s (3G)
- LCP: 1.2s

## Advanced Features

### Conditional WebP

Serve WebP only to supported browsers:

```php
add_filter('speedmate_webp_condition', function($use_webp) {
    // Check Accept header
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return strpos($accept, 'image/webp') !== false;
});
```

### Custom Quality

Different quality for different image types:

```php
add_filter('speedmate_webp_quality', function($quality, $image_type) {
    // Higher quality for photos
    if ($image_type === 'photo') {
        return 90;
    }
    
    // Lower quality for graphics
    if ($image_type === 'graphic') {
        return 75;
    }
    
    return $quality;
}, 10, 2);
```

### CDN Integration

```php
add_filter('speedmate_image_url', function($url) {
    // Rewrite to CDN
    return str_replace(
        'https://site.com/wp-content/uploads',
        'https://cdn.site.com/uploads',
        $url
    );
});
```

## Troubleshooting

### WebP Not Generating

```bash
# Check GD/Imagick support
php -r "echo extension_loaded('gd') ? 'GD: Yes' : 'GD: No';"
php -r "echo extension_loaded('imagick') ? 'Imagick: Yes' : 'Imagick: No';"

# Check WebP support
php -r "var_dump(function_exists('imagewebp'));"
```

### Images Not Lazy Loading

1. Check JavaScript console for errors
2. Verify `loading="lazy"` attribute present
3. Check browser support
4. Review exclusion rules

### Quality Issues

1. Increase WebP quality setting
2. Compare with original
3. Check source image quality
4. Try different conversion library

## Best Practices

1. **Use Quality 85**: Best balance of size vs quality
2. **Keep Originals**: Set `preserve_original => true`
3. **Skip Hero Images**: Don't lazy load above-the-fold
4. **Monitor Lighthouse**: Track performance scores
5. **Use CDN**: Serve optimized images from CDN
6. **Test Across Devices**: Verify on mobile and desktop

## Next Steps

- [Configure Lazy Loading](/config/settings)
- [Critical CSS](/features/critical-css)
- [Preload Hints](/features/preload-hints)
