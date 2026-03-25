#!/bin/bash
# Sync production database (Hetzner) → local dev database (Pi)
# Usage: ./bin/sync-db-from-prod.sh

set -e

PROD_HOST="root@teutonia-willstaett.de"
PROD_CONTAINER="teutonia-database-1"

# Load local root password from .env.local
LOCAL_ROOT_PASSWORD=$(grep MARIADB_ROOT_PASSWORD "$(dirname "$0")/../.env.local" | cut -d= -f2)

# Prompt for prod root password
read -rsp "Prod DB root password: " PROD_ROOT_PASSWORD
echo

echo "Syncing database from prod..."
ssh "$PROD_HOST" "docker exec $PROD_CONTAINER mariadb-dump -u root -p'$PROD_ROOT_PASSWORD' teutonia" \
  | docker exec -i teutonia-database-1 mariadb -u root -p"$LOCAL_ROOT_PASSWORD" teutonia

echo "Done. Local database updated from prod."
