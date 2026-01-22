# SpeedMate

SpeedMate is a 100% free, open‑source WordPress performance plugin focused on automation and a lightweight architecture. It delivers static cache, media optimizations, Beast Mode JS delay, traffic‑driven cache warming, Auto‑LCP, and dynamic fragments for WooCommerce/membership sites.

## Highlights
- Static HTML cache with smart purge
- Auto‑generated Apache rules + Nginx copy/paste snippet
- Safe Mode: lazy‑load + image dimension injection + GZIP
- Beast Mode: delayed JS execution on user interaction
- Traffic‑driven cache warming (cron)
- Auto‑LCP learning + preload injection
- Dynamic fragment wrapper with WooCommerce cache‑busting

## Requirements
- WordPress 5.8+
- PHP 7.4+

## Install (dev)
1. Clone into wp-content/plugins/speedmate
2. Activate in WordPress admin
3. Open SpeedMate → choose Safe or Beast Mode

## Quickstart (local)
Start the local WordPress stack:
- `./scripts/stack-up.sh`

Run the full test suite (PHPUnit + E2E):
- `./scripts/run-tests.sh`

## Usage
- Safe Mode: automatic caching + media optimizations + Auto‑LCP
- Beast Mode: all above + JS delay (configurable whitelist/blacklist)
- Dynamic fragments: wrap PHP content with `[speedmate_dynamic]`

## Admin shortcuts
- Flush cache from the SpeedMate screen or WP Admin Bar

## Configuration
SpeedMate is designed to work out of the box. Advanced users can optionally edit the Beast Mode whitelist/blacklist in the admin screen.

## Testing
- Docker WordPress stack: see [tests/README.md](tests/README.md)
- PHPUnit integration tests: [tests/phpunit.xml](tests/phpunit.xml)
- Playwright E2E tests: [tests/e2e](tests/e2e)

### Git pre-push hook
Enable the hook to run tests before every push:
- `git config core.hooksPath .githooks`

## Status
Active development. PRs and issues welcome.

## Security
Report vulnerabilities via private disclosure (email to be added).

## License
MIT License. See [LICENSE](LICENSE).
