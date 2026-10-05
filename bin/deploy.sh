#!/bin/bash
# Deploy origin/main to production (mini-pc, /var/www/prod/teutonia).
# Usage: ./bin/deploy.sh
# Replaces the old GitHub Actions workflow, which SSHed into the Hetzner server.

set -e

cd "$(dirname "$0")/.."

git pull --ff-only
docker compose build php
docker compose up -d

# Nothing else in the stack runs migrations. Wait for the database first,
# and run as www-data to avoid root-owning var/cache (breaks the site).
for i in $(seq 1 30); do
  docker exec -u www-data teutonia-php-prod php bin/console doctrine:query:sql "SELECT 1" >/dev/null 2>&1 && break
  echo "Waiting for database... ($i)"
  sleep 2
done
docker exec -u www-data teutonia-php-prod php bin/console doctrine:migrations:migrate -n

echo "Deployed $(git rev-parse --short HEAD)."
