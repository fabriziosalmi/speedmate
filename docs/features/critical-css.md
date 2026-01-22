# Critical CSS

SpeedMate automatically extracts and inlines critical CSS to eliminate render-blocking resources and improve First Contentful Paint (FCP).

## What is Critical CSS?

Critical CSS is the minimum CSS required to render above-the-fold content. By inlining it in the `<head>`, browsers can start rendering immediately without waiting for full stylesheets.

## How It Works

```
Page Load
    ↓
Extract Critical CSS (first paint)
    ↓
Inline in <head>
    ↓
Async Load Full Stylesheets
    ↓
Render Below-the-Fold
```

## Benefits

- **Faster FCP**: Render starts immediately
- **Better Lighthouse Score**: +10-20 points
- **Reduced Blocking**: No stylesheet delays
- **Improved UX**: Content visible faster

## Automatic Extraction

SpeedMate automatically:

1. Analyzes page HTML structure
2. Identifies above-the-fold selectors
3. Extracts matching CSS rules
4. Inlines in `<head>`
5. Async loads remaining CSS

### Configuration

```php
update_option('speedmate_settings', [
    'critical_css_enabled' => true,
    'critical_viewport_height' => 1080, // Extract CSS for this height
    'critical_inline' => true,          // Inline in HTML
    'critical_async_load' => true,      // Async load remaining CSS
]);
```

## Manual Extraction

Extract critical CSS for specific templates:

```bash
# Extract for homepage
wp speedmate critical-css / --save

# Extract for blog template
wp speedmate critical-css /blog/sample-post --template=single

# Extract for all post types
wp speedmate critical-css --all-templates
```

## Per-Template Critical CSS

SpeedMate generates separate critical CSS for each template:

```
cache/speedmate/critical-css/
├── home.css
├── single-post.css
├── page.css
├── archive.css
└── category.css
```

### Template Detection

```php
add_filter('speedmate_critical_template', function($template) {
    if (is_singular('product')) {
        return 'single-product';
    }
    
    if (is_post_type_archive('product')) {
        return 'archive-product';
    }
    
    return $template;
});
```

## Output Example

### Before Critical CSS

```html
<head>
  <link rel="stylesheet" href="/wp-content/themes/theme/style.css">
  <link rel="stylesheet" href="/wp-content/plugins/plugin/style.css">
  <!-- Render blocked until CSS loads -->
</head>
```

### After Critical CSS

```html
<head>
  <style id="speedmate-critical-css">
    /* Critical CSS inlined */
    body { margin: 0; font-family: sans-serif; }
    .header { background: #fff; padding: 20px; }
    .hero { height: 600px; background: #000; }
    /* ... only above-the-fold CSS */
  </style>
  
  <link rel="preload" href="/style.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript><link rel="stylesheet" href="/style.css"></noscript>
  <!-- Non-critical CSS loads async -->
</head>
```

## Extraction Methods

### 1. Puppeteer Method (Recommended)

Uses headless browser for accurate extraction:

```bash
# Requires Node.js and Puppeteer
npm install -g critical

# Extract critical CSS
critical https://site.com/ \
  --inline \
  --base=./ \
  --width=1300 \
  --height=900
```

### 2. PHP Method

Built-in PHP extraction:

```php
$critical = \SpeedMate\Perf\CriticalCSS::instance()->extract_for_url('/');
```

### 3. Third-Party Services

- [CriticalCSS.com](https://criticalcss.com)
- [Penthouse](https://github.com/pocketjoso/penthouse)

## Advanced Configuration

### Viewport Sizes

Extract for multiple viewport sizes:

```php
update_option('speedmate_settings', [
    'critical_viewports' => [
        'mobile' => ['width' => 375, 'height' => 667],
        'tablet' => ['width' => 768, 'height' => 1024],
        'desktop' => ['width' => 1920, 'height' => 1080],
    ]
]);
```

### Selector Customization

```php
add_filter('speedmate_critical_selectors', function($selectors) {
    // Always include these selectors
    $selectors[] = '.header';
    $selectors[] = '.hero';
    $selectors[] = '.nav';
    
    // Exclude these
    $exclude = ['.footer', '.sidebar'];
    
    return array_diff($selectors, $exclude);
});
```

### CSS Minification

```php
update_option('speedmate_settings', [
    'critical_minify' => true,
    'critical_remove_comments' => true,
    'critical_remove_whitespace' => true,
]);
```

## Performance Impact

### Lighthouse Scores

**Before Critical CSS:**
```
First Contentful Paint: 2.1s
Largest Contentful Paint: 3.8s
Performance Score: 65
```

**After Critical CSS:**
```
First Contentful Paint: 0.8s
Largest Contentful Paint: 1.2s
Performance Score: 92
```

### Metrics Improvement

| Metric | Before | After | Improvement |
|--------|--------|-------|-------------|
| FCP    | 2.1s   | 0.8s  | 62% faster  |
| LCP    | 3.8s   | 1.2s  | 68% faster  |
| TTI    | 4.5s   | 2.1s  | 53% faster  |
| Speed Index | 3.2s | 1.4s | 56% faster |

## Best Practices

1. **Regenerate Regularly**: Update after theme/plugin changes
2. **Test Across Devices**: Verify mobile and desktop
3. **Monitor Size**: Keep critical CSS < 14KB
4. **Use Preload**: Preload non-critical stylesheets
5. **Validate Output**: Check for missing styles

### Size Optimization

Keep critical CSS under 14KB (initial TCP slow start):

```php
add_filter('speedmate_critical_css', function($css) {
    // Check size
    $size = strlen($css);
    
    if ($size > 14336) { // 14KB
        error_log("Critical CSS too large: {$size} bytes");
    }
    
    return $css;
});
```

## Troubleshooting

### Missing Styles

**Issue**: Some above-the-fold elements unstyled

**Solution**:
```php
// Add missing selectors
add_filter('speedmate_critical_selectors', function($selectors) {
    $selectors[] = '.missing-element';
    return $selectors;
});
```

### Flash of Unstyled Content (FOUC)

**Issue**: Content flashes before styles load

**Solution**:
```php
// Increase viewport height
update_option('speedmate_settings', [
    'critical_viewport_height' => 1200 // Was 1080
]);
```

### Large Critical CSS

**Issue**: Critical CSS > 14KB

**Solution**:
1. Simplify above-the-fold design
2. Remove unnecessary selectors
3. Minify more aggressively
4. Split by template

## Async CSS Loading

### Recommended Pattern

```html
<link rel="preload" href="style.css" as="style" 
      onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="style.css"></noscript>
```

### Custom Implementation

```php
add_filter('style_loader_tag', function($html, $handle) {
    if ($handle === 'theme-style') {
        $html = str_replace(
            "rel='stylesheet'",
            "rel='preload' as='style' onload=\"this.onload=null;this.rel='stylesheet'\"",
            $html
        );
        $html .= "<noscript>" . str_replace(' onload=', ' data-onload=', $html) . "</noscript>";
    }
    return $html;
}, 10, 2);
```

## Integration with Page Builders

### Elementor

```php
add_filter('speedmate_critical_extraction', function($enabled) {
    // Don't extract for Elementor preview
    if (isset($_GET['elementor-preview'])) {
        return false;
    }
    return $enabled;
});
```

### Gutenberg

Works automatically with block editor.

### Divi

```php
add_filter('speedmate_critical_template', function($template) {
    if (function_exists('et_pb_is_pagebuilder_used')) {
        return 'divi-builder';
    }
    return $template;
});
```

## Next Steps

- [Preload Hints](/features/preload-hints)
- [Media Optimization](/features/media-optimization)
- [Performance Testing](/dev/testing)
