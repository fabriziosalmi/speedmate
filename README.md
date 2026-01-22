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

## Install (release ZIP)
1. Download the latest ZIP from https://github.com/fabriziosalmi/speedmate/releases
2. Upload in WordPress → Plugins → Add New → Upload Plugin
3. Activate and select Safe/Beast Mode

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

### Hardening options
- Structured JSON logging (opt‑in)
- CSP nonce for inline scripts (opt‑in)
- REST rate limiting and idempotency protection are enabled by default

## 🌟 Why SpeedMate is Different (The "Zero Anxiety" Promise)
Unlike other plugins that require a PhD to configure or can break your site unexpectedly, SpeedMate is built on **trust mechanics**:

1. **Safety First (Preview Mode):** Test Beast Mode safely as an admin before enabling it for visitors. Zero downtime risk.
2. **Self‑Healing Cache:** Automatically detects plugin updates or theme changes and flushes only the necessary cache fragments. No more broken CSS after updates.
3. **The "Time Machine" Dashboard:** It don’t just show milliseconds. SpeedMate calculates the cumulative **human time saved** for your visitors.
4. **Database Self‑Cleaning:** A silent weekly housekeeper that removes expired transients and safe bloat, keeping your site fast long‑term.

## Testing
- Docker WordPress stack: see [tests/README.md](tests/README.md)
- PHPUnit integration tests: [tests/phpunit.xml](tests/phpunit.xml)
- Playwright E2E tests: [tests/e2e](tests/e2e)

## Static analysis
Run locally with Composer:
- `composer install`
- `composer phpcs`
- `composer phpstan`

### Git pre-push hook
Enable the hook to run tests before every push:
- `git config core.hooksPath .githooks`

## Status
Active development. Current version: v0.1.0. PRs and issues welcome.

## Versioning
See [VERSION](VERSION) and [CHANGELOG.md](CHANGELOG.md).

## Repository
https://github.com/fabriziosalmi/speedmate

## Security
Report vulnerabilities via private disclosure (fabrizio.salmi@gmail.com).

## License
MIT License. See [LICENSE](LICENSE).
