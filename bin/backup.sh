#!/bin/bash
# Nightly backup of production: database dump + the upload volumes.
# Usage: ./bin/backup.sh   (run from cron, see crontab -l)
#
# Writes $BACKUP_DIR/<timestamp>/ with db.sql.gz and one <volume>.tar.gz per
# volume, and keeps the last $KEEP_DAYS days.
#
# Restore:
#   gunzip -c db.sql.gz | docker exec -i teutonia-db-prod sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" teutonia'
#   docker run --rm -i -v teutonia_<volume>:/v alpine tar -C /v -xzf - < <volume>.tar.gz

set -euo pipefail

BACKUP_DIR="${BACKUP_DIR:-$HOME/backups/teutonia}"
KEEP_DAYS="${KEEP_DAYS:-14}"
VOLUMES=(uploads style_images assets pdfs liederlisten)

umask 077
target="$BACKUP_DIR/$(date +%Y-%m-%d_%H%M)"
mkdir -p "$target"

docker exec teutonia-db-prod sh -c 'mariadb-dump --single-transaction --routines -uroot -p"$MARIADB_ROOT_PASSWORD" teutonia' \
  | gzip > "$target/db.sql.gz"
# A dump cut short has no completion marker; fail loudly rather than keep it.
gunzip -c "$target/db.sql.gz" | tail -1 | grep -q '^-- Dump completed'

for v in "${VOLUMES[@]}"; do
  docker run --rm -v "teutonia_$v:/v:ro" alpine tar -C /v -czf - . > "$target/$v.tar.gz"
done

find "$BACKUP_DIR" -mindepth 1 -maxdepth 1 -type d -mtime +"$KEEP_DAYS" -exec rm -rf {} +

echo "$(date -Is) backup OK: $target ($(du -sh "$target" | cut -f1))"
