#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

echo "[1/4] Starting WordPress stack..."
/bin/bash "$ROOT_DIR/scripts/stack-up.sh"

echo "[2/4] Installing WP test suite + PHPUnit Polyfills..."
# Install WP test suite in container
mkdir -p "$ROOT_DIR/tests/tmp"
docker compose exec -T -u root wpcli /bin/sh -c "chmod +x /var/www/html/wp-content/plugins/speedmate/tests/bin/install-wp-tests.sh"
docker compose exec -T -u root wpcli /bin/sh -c "/var/www/html/wp-content/plugins/speedmate/tests/bin/install-wp-tests.sh"

echo "[3/4] Running PHPUnit..."
# Run PHPUnit (phar) inside container
docker compose exec -T -u root wpcli /bin/sh -c "mkdir -p /var/www/html/wp-content/uploads"
docker compose exec -T wpcli /bin/sh -c "php /tmp/phpunit.phar -c /var/www/html/wp-content/plugins/speedmate/tests/phpunit.xml"

echo "[4/4] Running Playwright E2E..."
# Run Playwright E2E in container
docker compose exec -T wpcli wp option update home http://wordpress >/dev/null
docker compose exec -T wpcli wp option update siteurl http://wordpress >/dev/null
docker compose run --rm playwright
docker compose exec -T wpcli wp option update home http://localhost:8080 >/dev/null
docker compose exec -T wpcli wp option update siteurl http://localhost:8080 >/dev/null
