#!/usr/bin/env bash
set -Eeuo pipefail

export COMPOSE_PROJECT_NAME="aqmsci$$"
export AQMS_HTTP_BIND=127.0.0.1
export AQMS_HTTP_PORT="${AQMS_TEST_PORT:-18081}"
export AQMS_INGEST_MIN_INTERVAL_SECONDS=0
export AQMS_INGEST_TOKEN=ci-test-token
export AQMS_INGEST_ALLOWED_CIDRS=172.16.0.0/12
export AQMS_DB_PASSWORD="ci-app-${RANDOM}-${RANDOM}"
export AQMS_DB_ROOT_PASSWORD="ci-root-${RANDOM}-${RANDOM}"
export AQMS_ADMIN_PIN_HASH='$2y$04$9Dz3NPa3FLI7Uz7xs5sqrO0QAvyEcKmeGtG4dSu5WWmLGj/ZR1rDm'
export AQMS_WIFI_SSID=PARTIKULAT02-TEST
export AQMS_WIFI_PSK=ci-wifi-password
base_url="http://127.0.0.1:${AQMS_HTTP_PORT}"
export AQMS_DATA_URL="${base_url}/display/"
backup_path="/tmp/${COMPOSE_PROJECT_NAME}-restore-test.sql.gz"
test_dir="$(mktemp -d)"

cleanup() {
    docker compose down -v --remove-orphans >/dev/null 2>&1 || true
    rm -f -- "$backup_path"
    rm -rf -- "$test_dir"
}
trap cleanup EXIT

wait_for_health() {
    local first_response second_response
    for _ in $(seq 1 60); do
        first_response="$(curl -fsS "${base_url}/health.php" 2>/dev/null || true)"
        if [[ "$first_response" == "ok" ]]; then
            sleep 1
            second_response="$(curl -fsS "${base_url}/health.php" 2>/dev/null || true)"
            [[ "$second_response" == "ok" ]] && return 0
        fi
        sleep 1
    done
    echo "AQMS test stack did not reach stable health" >&2
    docker compose ps >&2 || true
    docker compose logs --tail=80 web >&2 || true
    return 1
}

docker compose up -d --build
wait_for_health

query='pm1=12&pm25=18&pm10=24&temp=29.5&humd=72&ampere=1.2&baterai=85&pompa=1023&volt=12.4&press=1008'
[[ "$(curl -sS -o /dev/null -w '%{http_code}' "${base_url}/insert.php?${query}")" == 401 ]]
curl -fsS -H 'X-AQMS-Token: ci-test-token' "${base_url}/partikulat/insert.php?${query}" | grep -qx received
[[ "$(curl -sS -H 'X-AQMS-Token: ci-test-token' -o /dev/null -w '%{http_code}' "${base_url}/insert.php?${query}&extra=1")" == 400 ]]
[[ "$(curl -sS -H 'X-AQMS-Token: ci-test-token' -o /dev/null -w '%{http_code}' "${base_url}/insert.php?${query/pm1=12/pm1=9000}")" == 422 ]]
[[ "$(curl -sS -o /dev/null -w '%{http_code}' -X POST "${base_url}/insert.php")" == 405 ]]

headers="$(curl -fsSI "${base_url}/dashboard/")"
dashboard_page="$(curl -fsS "${base_url}/dashboard/")"
grep -qi '^content-security-policy:' <<<"$headers"
! grep -qi '^x-powered-by:' <<<"$headers"
! grep -Eqi '^server:.*[0-9]' <<<"$headers"
grep -q 'chart.umd.min.js' <<<"$dashboard_page"
grep -q 'qrcode-generator/qrcode.js' <<<"$dashboard_page"
grep -q 'id="powerMenuButton"' <<<"$dashboard_page"
grep -q 'id="dataAccessButton"' <<<"$dashboard_page"
! grep -q 'id="fullscreenButton"' <<<"$dashboard_page"
grep -q 'DINAS LINGKUNGAN HIDUP KABUPATEN SANGGAU' <<<"$dashboard_page"
grep -q 'class="footer-organization">DINAS LINGKUNGAN HIDUP KABUPATEN SANGGAU' <<<"$dashboard_page"
grep -q 'class="footer-status"' <<<"$dashboard_page"
! grep -q 'LOCAL MONITOR' <<<"$dashboard_page"
grep -q '>KONTROL DAYA</h2>' <<<"$dashboard_page"
! grep -q 'Pilih tindakan, masukkan PIN' <<<"$dashboard_page"
grep -Fq 'grid-template-columns: repeat(3, 1fr)' app/dashboard/css/dashboard-7.css
grep -Fq "'Diperbarui ' + formatTime(new Date(), true)" app/dashboard/js/dashboard-7.js
[[ "$(curl -sS -o /dev/null -w '%{http_code}' "${base_url}/admin/power.php")" == 405 ]]
[[ "$(curl -sS -H 'Content-Type: application/json' -d '{"action":"reboot","pin":"0000"}' \
  -o /dev/null -w '%{http_code}' "${base_url}/admin/power.php")" == 503 ]]
[[ "$(curl -sS -o /dev/null -w '%{http_code}' "${base_url}/display/")" == 403 ]]

dashboard_html="${test_dir}/dashboard.html"
kiosk_cookies="${test_dir}/kiosk.cookies"
phone_cookies="${test_dir}/phone.cookies"
curl -fsS -c "$kiosk_cookies" "${base_url}/dashboard/" >"$dashboard_html"
csrf="$(sed -n 's/.*data-csrf="\([^"]*\)".*/\1/p' "$dashboard_html" | head -n 1)"
[[ "$csrf" =~ ^[a-f0-9]{48}$ ]]

access_response="$(curl -fsS -b "$kiosk_cookies" -c "$kiosk_cookies" \
  -H 'Content-Type: application/json' -H "X-AQMS-CSRF: ${csrf}" \
  -d '{"pin":"2468"}' "${base_url}/admin/data-access.php")"
grep -q 'WIFI:T:WPA;S:PARTIKULAT02-TEST' <<<"$access_response"
access_url="$(sed -n 's/.*"accessUrl":"\([^"]*\)".*/\1/p' <<<"$access_response")"
[[ "$access_url" == "${base_url}/display/?access="* ]]

access_page="$(curl -fsS -L -c "$phone_cookies" "$access_url")"
grep -q '>Data mentah</h1>' <<<"$access_page"
[[ "$(curl -sS -o /dev/null -w '%{http_code}' "$access_url")" == 403 ]]

docker compose exec -T database sh -c 'exec mysql -uaqms -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' <<'SQL'
INSERT INTO maintb (waktu,pm1,pm25,pm10,temp,humd,ampere,baterai,pompa,volt,press)
VALUES (
  DATE_SUB(DATE_FORMAT(NOW(), "%Y-%m-%d %H:%i:00"), INTERVAL MOD(MINUTE(NOW()),5)+1 MINUTE),
  10,20,30,29,70,1,80,1,12,1000
);
SQL
curl -fsS -b "$phone_cookies" "${base_url}/display/?all=1&format=csv" >"${test_dir}/raw.csv"
grep -q 'waktu,pm1,pm25,pm10,temp,humd,ampere,baterai,pompa,volt,press' "${test_dir}/raw.csv"
grep -q ',10,20,30,29,70,1,80,1,12,1000' "${test_dir}/raw.csv"
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
wait_for_health
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
