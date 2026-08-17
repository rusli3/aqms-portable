#!/usr/bin/env bash
set -Eeuo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    echo "jalankan dengan sudo: sudo scripts/configure-data-access.sh" >&2
    exit 1
fi

wifi_ssid="${1:-PARTIKULAT02}"
data_url="${2:-http://192.168.100.135/display/}"
project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
env_file="${project_dir}/.env"

for command_name in awk curl docker nmcli; do
    command -v "${command_name}" >/dev/null || {
        echo "perintah wajib tidak ditemukan: ${command_name}" >&2
        exit 1
    }
done

[[ -f "${env_file}" ]] || {
    echo ".env tidak ditemukan di ${project_dir}" >&2
    exit 1
}

[[ "${wifi_ssid}" =~ ^[^[:cntrl:]]{1,32}$ ]] || {
    echo "SSID tidak valid" >&2
    exit 64
}

[[ "${data_url}" =~ ^http://192\.168\.100\.135(/|$) ]] || {
    echo "URL data harus memakai alamat lokal AQMS 192.168.100.135" >&2
    exit 64
}

umask 077
secret_file="$(mktemp /tmp/aqms-wifi-psk.XXXXXX)"
env_tmp="$(mktemp "${project_dir}/.env.tmp.XXXXXX")"

cleanup() {
    rm -f -- "${secret_file}" "${env_tmp}"
}
trap cleanup EXIT

nmcli --show-secrets --escape no \
    -g 802-11-wireless-security.psk \
    connection show "${wifi_ssid}" >"${secret_file}"
chmod 0600 "${secret_file}"

if ! LC_ALL=C awk '
NR == 1 {
    valid = ((length($0) >= 8 && length($0) <= 63 && $0 !~ /[[:cntrl:]]/) ||
             (length($0) == 64 && $0 ~ /^[0-9A-Fa-f]+$/))
}
END { exit !(NR == 1 && valid) }
' "${secret_file}"; then
    echo "PSK Wi-Fi tidak ditemukan atau formatnya tidak valid" >&2
    exit 65
fi

env_owner="$(stat -c '%u' "${env_file}")"
env_group="$(stat -c '%g' "${env_file}")"
backup_file="${env_file}.pre-data-access-$(date +%Y%m%d-%H%M%S)"
cp -p -- "${env_file}" "${backup_file}"
chmod 0600 "${backup_file}"

LC_ALL=C awk \
    -v secret_file="${secret_file}" \
    -v wifi_ssid="${wifi_ssid}" \
    -v data_url="${data_url}" '
BEGIN {
    if ((getline wifi_psk < secret_file) <= 0) exit 66
    close(secret_file)
}
{
    key = $0
    sub(/=.*/, "", key)
    if (key == "AQMS_WIFI_SSID") {
        print "AQMS_WIFI_SSID=" wifi_ssid; seen_ssid = 1
    } else if (key == "AQMS_WIFI_PSK") {
        print "AQMS_WIFI_PSK=" wifi_psk; seen_psk = 1
    } else if (key == "AQMS_WIFI_HIDDEN") {
        print "AQMS_WIFI_HIDDEN=false"; seen_hidden = 1
    } else if (key == "AQMS_DATA_URL") {
        print "AQMS_DATA_URL=" data_url; seen_url = 1
    } else {
        print
    }
}
END {
    if (!seen_ssid) print "AQMS_WIFI_SSID=" wifi_ssid
    if (!seen_psk) print "AQMS_WIFI_PSK=" wifi_psk
    if (!seen_hidden) print "AQMS_WIFI_HIDDEN=false"
    if (!seen_url) print "AQMS_DATA_URL=" data_url
}
' "${env_file}" >"${env_tmp}"

chown "${env_owner}:${env_group}" "${env_tmp}" "${backup_file}"
chmod 0600 "${env_tmp}" "${backup_file}"
mv -- "${env_tmp}" "${env_file}"

cd "${project_dir}"
compose=(docker compose -f compose.yaml -f compose.production.yaml)
"${compose[@]}" config --quiet
"${compose[@]}" up -d --force-recreate web

for attempt in $(seq 1 60); do
    first_response="$(curl -fsS http://127.0.0.1/health.php 2>/dev/null || true)"
    if [[ "${first_response}" == "ok" ]]; then
        sleep 1
        second_response="$(curl -fsS http://127.0.0.1/health.php 2>/dev/null || true)"
        [[ "${second_response}" == "ok" ]] && break
    fi
    if [[ "${attempt}" -eq 60 ]]; then
        echo "web tidak mencapai kondisi sehat" >&2
        "${compose[@]}" logs --tail=80 web >&2 || true
        exit 1
    fi
    sleep 1
done

"${compose[@]}" exec -T web sh -c \
    'test -n "$AQMS_WIFI_SSID" && test -n "$AQMS_WIFI_PSK" && test -n "$AQMS_DATA_URL"'

if command -v snap >/dev/null && snap services wpe-webkit-mir-kiosk >/dev/null 2>&1; then
    snap restart wpe-webkit-mir-kiosk >/dev/null
fi

echo "akses data HP aktif; PSK tersimpan tanpa ditampilkan"
echo "backup konfigurasi: ${backup_file}"
echo "health: $(curl -fsS http://127.0.0.1/health.php)"
