# Contributing

Thank you for considering contributing to SpeedMate! This guide will help you get started.

## Code of Conduct

Be respectful, inclusive, and professional. We're all here to make WordPress faster.

## Getting Started

### Fork & Clone

```bash
# Fork on GitHub, then:
git clone https://github.com/YOUR_USERNAME/speedmate.git
cd speedmate
git remote add upstream https://github.com/fabriziosalmi/speedmate.git
```

### Install Dependencies

```bash
composer install
npm install
```

### Create Branch

```bash
git checkout -b feature/your-feature-name
# or
git checkout -b fix/issue-number-description
```

## Development Workflow

### 1. Make Changes

Follow coding standards:
- WordPress Coding Standards
- PSR-4 autoloading
- Type hints where possible
- DocBlocks for all public methods

### 2. Test Your Changes

```bash
# PHP tests
vendor/bin/phpunit

# Coding standards
vendor/bin/phpcs

# Static analysis
vendor/bin/phpstan analyse

# E2E tests
npx playwright test
```

### 3. Commit Changes

```bash
git add .
git commit -m "feat: add new feature"
# or
git commit -m "fix: resolve issue #123"
```

**Commit message format:**
- `feat:` New feature
- `fix:` Bug fix
- `docs:` Documentation only
- `style:` Code style (formatting, etc.)
- `refactor:` Code refactoring
- `test:` Adding tests
- `chore:` Maintenance tasks

### 4. Push & Create PR

```bash
git push origin feature/your-feature-name
```

Then create Pull Request on GitHub.

## Coding Standards

### PHP Standards

**Follow WordPress Coding Standards:**

```php
<?php
/**
 * Class description
 *
 * @package SpeedMate
 */

declare(strict_types=1);

namespace SpeedMate\Cache;

/**
 * StaticCache class
 */
final class StaticCache {
    /**
     * Instance
     *
     * @var StaticCache|null
     */
    private static ?StaticCache $instance = null;

    /**
     * Get instance
     *
     * @return StaticCache
     */
    public static function instance(): StaticCache {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
```

**Key points:**
- Tabs for indentation
- Yoda conditions: `null === $var`
- `array()` not `[]`
- Spaces around operators
- Curly braces on same line

### JavaScript Standards

```javascript
// Use ES6+
const cacheKey = generateKey(url);

// Descriptive names
function generateCacheKey(url) {
  return url.split('?')[0];
}

// JSDoc comments
/**
 * Generate cache key from URL
 * @param {string} url - The URL to process
 * @return {string} Cache key
 */
```

### Documentation

**All public methods need DocBlocks:**

```php
/**
 * Flush cache by pattern
 *
 * @param string $pattern URL pattern with wildcards
 * @return bool True on success, false on failure
 */
public function flush_pattern( string $pattern ): bool {
    // Implementation
}
```

## Testing Requirements

### Unit Tests Required

```php
<?php
namespace SpeedMate\Tests\Unit;

use PHPUnit\Framework\TestCase;

class YourFeatureTest extends TestCase {
    public function test_your_feature() {
        // Arrange
        $input = 'test';
        
        // Act
        $result = your_function($input);
        
        // Assert
        $this->assertEquals('expected', $result);
    }
}
```

### Coverage Goal

Aim for 80%+ code coverage for new features.

## Pull Request Guidelines

### PR Checklist

- [ ] Tests added/updated
- [ ] Documentation updated
- [ ] CHANGELOG.md updated
- [ ] Coding standards pass
- [ ] PHPStan passes
- [ ] No merge conflicts
- [ ] Descriptive PR title

### PR Template

```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation

## Testing
How to test these changes

## Checklist
- [ ] Tests pass
- [ ] PHPCS passes
- [ ] PHPStan passes
- [ ] Documentation updated
```

### Review Process

1. Automated checks run (CI)
2. Maintainer reviews code
3. Changes requested if needed
4. Approved and merged

## Issue Guidelines

### Bug Reports

```markdown
## Bug Description
Clear description of the bug

## Steps to Reproduce
1. Go to...
2. Click...
3. See error

## Expected Behavior
What should happen

## Actual Behavior
What actually happens

## Environment
- WordPress version: 6.4
- PHP version: 8.1
- SpeedMate version: 0.4.1
- Browser: Chrome 120
```

### Feature Requests

```markdown
## Feature Description
Clear description of the feature

## Use Case
Why is this feature needed?

## Proposed Solution
How should it work?

## Alternatives Considered
Other approaches you've thought about
```

## Development Environment

### Docker Setup

```bash
# Start WordPress
docker-compose up -d

# Access WordPress
open http://localhost:8080

# WP-CLI
docker-compose exec wordpress wp --info
```

### Local Testing

```bash
# Install in local WordPress
cd wp-content/plugins/
git clone https://github.com/YOUR_USERNAME/speedmate.git
cd speedmate
composer install

# Activate
wp plugin activate speedmate
```

## Code Review Tips

### What We Look For

**Good:**
- Clear, descriptive names
- Small, focused functions
- Proper error handling
- Comprehensive tests
- Updated documentation

**Avoid:**
- Large, complex functions
- Unclear variable names
- Missing error handling
- No tests
- Outdated documentation

### Example Good PR

```php
/**
 * Generate WebP image from source
 *
 * @param string $source Path to source image
 * @param int    $quality WebP quality (0-100)
 * @return string|false Path to WebP image, or false on failure
 */
public function generate_webp( string $source, int $quality = 85 ) {
    if ( ! file_exists( $source ) ) {
        return false;
    }

    $webp_path = $this->get_webp_path( $source );
    
    try {
        $image = $this->load_image( $source );
        imagewebp( $image, $webp_path, $quality );
        imagedestroy( $image );
        
        return $webp_path;
    } catch ( \Exception $e ) {
        Logger::log( 'error', 'webp_generation_failed', [
            'source' => $source,
            'error'  => $e->getMessage(),
        ] );
        
        return false;
    }
}
```

## Release Process

### Version Numbering

We use Semantic Versioning (SemVer):

- `MAJOR.MINOR.PATCH` (e.g., `1.2.3`)
- **MAJOR**: Breaking changes
- **MINOR**: New features (backward-compatible)
- **PATCH**: Bug fixes

### Release Checklist

1. Update VERSION file
2. Update version in speedmate.php
3. Update CHANGELOG.md
4. Run all tests
5. Create git tag
6. Create GitHub release
7. Build ZIP file

## Community

### Get Help

- **GitHub Discussions**: Ask questions
- **Issues**: Report bugs
- **Pull Requests**: Contribute code

### Stay Updated

- Watch repository for updates
- Follow changelog
- Join discussions

## Resources

### Documentation

- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Playwright Documentation](https://playwright.dev/)

### Tools

- [PHPStan](https://phpstan.org/)
- [PHPCS](https://github.com/squizlabs/PHP_CodeSniffer)
- [Composer](https://getcomposer.org/)

## License

By contributing, you agree that your contributions will be licensed under GPL-3.0.

## Thank You!

Every contribution helps make SpeedMate better. Thank you for your time and effort!

## Next Steps

- [Architecture Guide](/dev/architecture)
- [Testing Guide](/dev/testing)
- [GitHub Repository](https://github.com/fabriziosalmi/speedmate)
