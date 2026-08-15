#!/usr/bin/env bash
set -Eeuo pipefail

output_path="${1:-}"
if [[ -z "$output_path" ]]; then
    echo "usage: scripts/backup.sh PATH.sql.gz" >&2
    exit 64
fi
if [[ -e "$output_path" ]]; then
    echo "refusing to overwrite existing file: $output_path" >&2
    exit 65
fi

partial_path="${output_path}.partial.$$"
trap 'rm -f -- "$partial_path"' EXIT
docker compose exec -T database sh -c \
    'exec mysqldump --single-transaction --routines --triggers -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' \
    | gzip -9 > "$partial_path"
gzip -t "$partial_path"
mv -- "$partial_path" "$output_path"
trap - EXIT
echo "backup verified: $output_path"
