#!/usr/bin/env bash
set -Eeuo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    echo "jalankan dengan sudo: sudo scripts/install-power-control.sh" >&2
    exit 1
fi

project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
source_dir="${project_dir}/deploy/systemd"

getent group www-data >/dev/null || {
    echo "group host www-data tidak ditemukan" >&2
    exit 1
}

install -d -o root -g root -m 0755 /usr/local/libexec
install -o root -g root -m 0755 \
    "${source_dir}/aqms-power-control" \
    /usr/local/libexec/aqms-power-control
install -o root -g root -m 0644 \
    "${source_dir}/aqms-power-control.service" \
    /etc/systemd/system/aqms-power-control.service
install -o root -g root -m 0644 \
    "${source_dir}/aqms-power-control.path" \
    /etc/systemd/system/aqms-power-control.path
install -o root -g root -m 0644 \
    "${source_dir}/aqms-control.tmpfiles" \
    /etc/tmpfiles.d/aqms-control.conf

systemd-tmpfiles --create /etc/tmpfiles.d/aqms-control.conf
rm -f -- /run/aqms-control/request
systemctl daemon-reload
systemctl enable --now aqms-power-control.path

echo "broker kontrol daya terpasang"
systemctl --no-pager --full status aqms-power-control.path
