#!/usr/bin/env sh
set -eu

WP_VERSION="${WP_VERSION:-6.5.4}"
WP_TESTS_DIR="${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}"
WP_CORE_DIR="${WP_CORE_DIR:-/var/www/html}"
DB_NAME="${WORDPRESS_DB_NAME:-wordpress}"
DB_USER="${WORDPRESS_DB_USER:-wordpress}"
DB_PASS="${WORDPRESS_DB_PASSWORD:-wordpress}"
DB_HOST="${WORDPRESS_DB_HOST:-db:3306}"

if ! command -v svn >/dev/null 2>&1; then
  if command -v apk >/dev/null 2>&1; then
    apk add --no-cache subversion curl
  elif command -v apt-get >/dev/null 2>&1; then
    apt-get update && apt-get install -y subversion curl
  fi
fi

if [ ! -d "$WP_TESTS_DIR" ]; then
  mkdir -p "$WP_TESTS_DIR"
  svn export --quiet "https://develop.svn.wordpress.org/tags/${WP_VERSION}/tests/phpunit/includes" "$WP_TESTS_DIR/includes"
  svn export --quiet "https://develop.svn.wordpress.org/tags/${WP_VERSION}/tests/phpunit/data" "$WP_TESTS_DIR/data"
fi

# Install PHPUnit Polyfills
POLYFILLS_VERSION="2.0.0"
POLYFILLS_DIR="/tmp/phpunit-polyfills"
if [ ! -d "$POLYFILLS_DIR/PHPUnit-Polyfills-${POLYFILLS_VERSION}" ]; then
  mkdir -p "$POLYFILLS_DIR"
  curl -L -o "$POLYFILLS_DIR/polyfills.tar.gz" "https://github.com/Yoast/PHPUnit-Polyfills/archive/refs/tags/${POLYFILLS_VERSION}.tar.gz"
  tar -xzf "$POLYFILLS_DIR/polyfills.tar.gz" -C "$POLYFILLS_DIR"
fi

cat > "$WP_TESTS_DIR/wp-tests-config.php" <<EOF
<?php

define('DB_NAME', '${DB_NAME}');
define('DB_USER', '${DB_USER}');
define('DB_PASSWORD', '${DB_PASS}');
define('DB_HOST', '${DB_HOST}');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');

$table_prefix = 'wptests_';

define('WP_TESTS_DOMAIN', 'localhost');
define('WP_TESTS_EMAIL', 'admin@example.com');
define('WP_TESTS_TITLE', 'SpeedMate Tests');
define('WP_PHP_BINARY', 'php');
define('WPLANG', '');
define('WP_TESTS_DIR', '${WP_TESTS_DIR}');
define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', '${POLYFILLS_DIR}/PHPUnit-Polyfills-${POLYFILLS_VERSION}');

define('ABSPATH', '${WP_CORE_DIR}/');
EOF

# Download phpunit phar if missing
if [ ! -f /tmp/phpunit.phar ]; then
  curl -L -o /tmp/phpunit.phar https://phar.phpunit.de/phpunit-9.6.22.phar
  chmod +x /tmp/phpunit.phar
fi
