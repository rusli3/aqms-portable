#!/usr/bin/env bash
set -Eeuo pipefail

project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
env_file="${project_dir}/.env"

[[ -f "$env_file" ]] || {
    echo "file .env tidak ditemukan di ${project_dir}" >&2
    exit 1
}
command -v docker >/dev/null || {
    echo "Docker diperlukan untuk membuat hash PIN" >&2
    exit 1
}

read -r -s -p "PIN admin baru (4-8 digit): " pin
printf '\n'
[[ "$pin" =~ ^[0-9]{4,8}$ ]] || {
    echo "PIN harus terdiri dari 4-8 digit" >&2
    exit 1
}

read -r -s -p "Ulangi PIN: " confirmation
printf '\n'
[[ "$pin" == "$confirmation" ]] || {
    echo "konfirmasi PIN tidak cocok" >&2
    exit 1
}

pin_hash="$(printf '%s' "$pin" | docker compose \
    -f "${project_dir}/compose.yaml" \
    -f "${project_dir}/compose.production.yaml" \
    exec -T web \
    php -r '$pin = stream_get_contents(STDIN); echo password_hash($pin, PASSWORD_DEFAULT);')"
unset pin confirmation

temporary_file="$(mktemp "${env_file}.XXXXXX")"
cleanup() { rm -f -- "$temporary_file"; }
trap cleanup EXIT

AQMS_NEW_PIN_HASH="$pin_hash" awk '
BEGIN { enabled = 0; hash = 0 }
/^AQMS_POWER_CONTROLS_ENABLED=/ {
    print "AQMS_POWER_CONTROLS_ENABLED=true"
    enabled = 1
    next
}
/^AQMS_ADMIN_PIN_HASH=/ {
    print "AQMS_ADMIN_PIN_HASH=\047" ENVIRON["AQMS_NEW_PIN_HASH"] "\047"
    hash = 1
    next
}
{ print }
END {
    if (!enabled) print "AQMS_POWER_CONTROLS_ENABLED=true"
    if (!hash) print "AQMS_ADMIN_PIN_HASH=\047" ENVIRON["AQMS_NEW_PIN_HASH"] "\047"
}
' "$env_file" > "$temporary_file"

chmod 0600 "$temporary_file"
mv -- "$temporary_file" "$env_file"
trap - EXIT

echo "PIN tersimpan sebagai password hash; PIN polos tidak ditulis ke disk"
echo "recreate service web untuk menerapkan konfigurasi"
