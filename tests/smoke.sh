#!/usr/bin/env bash
set -Eeuo pipefail

export COMPOSE_PROJECT_NAME="aqmsci$$"
export AQMS_HTTP_BIND=127.0.0.1
export AQMS_HTTP_PORT="${AQMS_TEST_PORT:-18081}"
export AQMS_INGEST_MIN_INTERVAL_SECONDS=0
export AQMS_INGEST_TOKEN=ci-test-token
export AQMS_DB_PASSWORD="ci-app-${RANDOM}-${RANDOM}"
export AQMS_DB_ROOT_PASSWORD="ci-root-${RANDOM}-${RANDOM}"
base_url="http://127.0.0.1:${AQMS_HTTP_PORT}"
backup_path="/tmp/${COMPOSE_PROJECT_NAME}-restore-test.sql.gz"

cleanup() {
    docker compose down -v --remove-orphans >/dev/null 2>&1 || true
    rm -f -- "$backup_path"
}
trap cleanup EXIT

docker compose up -d --build
for _ in $(seq 1 60); do
    if curl -fsS "${base_url}/health.php" >/dev/null; then break; fi
    sleep 1
done
curl -fsS "${base_url}/health.php" | grep -qx ok

query='pm1=12&pm25=18&pm10=24&temp=29.5&humd=72&ampere=1.2&baterai=85&pompa=1023&volt=12.4&press=1008'
[[ "$(curl -sS -o /dev/null -w '%{http_code}' "${base_url}/insert.php?${query}")" == 401 ]]
curl -fsS -H 'X-AQMS-Token: ci-test-token' "${base_url}/partikulat/insert.php?${query}" | grep -qx received
[[ "$(curl -sS -H 'X-AQMS-Token: ci-test-token' -o /dev/null -w '%{http_code}' "${base_url}/insert.php?${query}&extra=1")" == 400 ]]
[[ "$(curl -sS -H 'X-AQMS-Token: ci-test-token' -o /dev/null -w '%{http_code}' "${base_url}/insert.php?${query/pm1=12/pm1=9000}")" == 422 ]]
[[ "$(curl -sS -o /dev/null -w '%{http_code}' -X POST "${base_url}/insert.php")" == 405 ]]

headers="$(curl -fsSI "${base_url}/dashboard/")"
grep -qi '^content-security-policy:' <<<"$headers"
! grep -qi '^x-powered-by:' <<<"$headers"
! grep -Eqi '^server:.*[0-9]' <<<"$headers"
curl -fsS "${base_url}/dashboard/" | grep -q 'chart.umd.min.js'
! curl -fsS "${base_url}/display/" | grep -Eq 'https?://|jquery|DataTable'

docker compose exec -T database sh -c 'exec mysql -uaqms -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' <<'SQL'
INSERT INTO maintb (waktu,pm1,pm25,pm10,temp,humd,ampere,baterai,pompa,volt,press)
VALUES (
  DATE_SUB(DATE_FORMAT(NOW(), "%Y-%m-%d %H:%i:00"), INTERVAL MOD(MINUTE(NOW()),5)+1 MINUTE),
  10,20,30,29,70,1,80,1,12,1000
);
SQL
core_before="$(docker compose exec -T database sh -c 'mysql -N -uaqms -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "SELECT COUNT(*) FROM coretb"')"
docker compose exec -T scheduler php /var/www/html/scheduler/main.php
core_after_first="$(docker compose exec -T database sh -c 'mysql -N -uaqms -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "SELECT COUNT(*) FROM coretb"')"
docker compose exec -T scheduler php /var/www/html/scheduler/main.php
core_after_second="$(docker compose exec -T database sh -c 'mysql -N -uaqms -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "SELECT COUNT(*) FROM coretb"')"
[[ "$core_after_first" -eq $((core_before + 1)) ]]
[[ "$core_after_second" == "$core_after_first" ]]

before="$(docker compose exec -T database sh -c 'mysql -N -uaqms -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "SELECT COUNT(*) FROM maintb"')"
docker compose down
docker compose up -d
for _ in $(seq 1 60); do
    if curl -fsS "${base_url}/health.php" >/dev/null; then break; fi
    sleep 1
done
after="$(docker compose exec -T database sh -c 'mysql -N -uaqms -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "SELECT COUNT(*) FROM maintb"')"
[[ "$before" == "$after" && "$after" -ge 1 ]]

scripts/backup.sh "$backup_path"
docker compose exec -T database sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD"' <<'SQL'
DROP DATABASE partikulat;
CREATE DATABASE partikulat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
SQL
scripts/restore.sh "$backup_path" --confirm-import
restored="$(docker compose exec -T database sh -c 'mysql -N -uaqms -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "SELECT COUNT(*) FROM maintb"')"
[[ "$restored" == "$before" ]]

echo "smoke, persistence, backup, and restore tests passed"
