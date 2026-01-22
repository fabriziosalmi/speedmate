#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

docker compose up -d

# Wait for DB
for i in $(seq 1 30); do
  if docker compose exec -T wpcli wp db check >/dev/null 2>&1; then
    break
  fi
  sleep 2
done

# Install WordPress if not installed
if ! docker compose exec -T wpcli wp core is-installed >/dev/null 2>&1; then
  docker compose exec -T wpcli wp core install \
    --url=http://localhost:8080 \
    --title=SpeedMate \
    --admin_user=admin \
    --admin_password=admin \
    --admin_email=admin@example.com
fi

# Activate plugin
if ! docker compose exec -T wpcli wp plugin is-active speedmate >/dev/null 2>&1; then
  docker compose exec -T wpcli wp plugin activate speedmate
fi
