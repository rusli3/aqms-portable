#!/usr/bin/env bash
set -Eeuo pipefail

backup_path="${1:-}"
confirmation="${2:-}"
if [[ ! -f "$backup_path" || "$confirmation" != "--confirm-import" ]]; then
    echo "usage: scripts/restore.sh BACKUP.sql.gz --confirm-import" >&2
    echo "the target database must be empty or compatible; create a backup first" >&2
    exit 64
fi

gzip -t "$backup_path"
gzip -dc "$backup_path" | docker compose exec -T database sh -c \
    'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
echo "restore import completed; verify row counts and dashboard"
