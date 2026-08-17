#!/usr/bin/env bash
set -Eeuo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    echo "jalankan dengan sudo: sudo scripts/install-display-resilience.sh" >&2
    exit 1
fi

ethernet_interface="${1:-enp1s0}"
project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
source_dir="${project_dir}/deploy/systemd"

if [[ ! "${ethernet_interface}" =~ ^[[:alnum:]_.:-]+$ ]]; then
    echo "nama interface tidak valid: ${ethernet_interface}" >&2
    exit 64
fi

if [[ ! -d "/sys/class/net/${ethernet_interface}" ]]; then
    echo "interface tidak ditemukan: ${ethernet_interface}" >&2
    exit 1
fi

for command_name in netplan systemctl curl snap; do
    command -v "${command_name}" >/dev/null || {
        echo "perintah wajib tidak ditemukan: ${command_name}" >&2
        exit 1
    }
done

netplan get "ethernets.${ethernet_interface}" >/dev/null 2>&1 || {
    echo "interface ${ethernet_interface} tidak dikelola sebagai Ethernet Netplan" >&2
    exit 1
}

# Ethernet hanya dipakai untuk administrasi/internet. Dashboard dan sensor tetap
# harus menyala ketika kabel LAN tidak terpasang.
netplan set --origin-hint 99-aqms-resilience \
    "ethernets.${ethernet_interface}.optional=true"
chmod 0600 /etc/netplan/99-aqms-resilience.yaml
netplan generate

if ! grep -Rqs '^RequiredForOnline=no$' \
    /run/systemd/network/*"${ethernet_interface}"*.network; then
    echo "Netplan belum menghasilkan RequiredForOnline=no untuk ${ethernet_interface}" >&2
    exit 1
fi

install -d -o root -g root -m 0755 /usr/local/libexec
install -o root -g root -m 0755 \
    "${source_dir}/aqms-kiosk-ready" \
    /usr/local/libexec/aqms-kiosk-ready
install -o root -g root -m 0644 \
    "${source_dir}/aqms-kiosk-ready.service" \
    /etc/systemd/system/aqms-kiosk-ready.service

systemctl daemon-reload
systemctl enable --now aqms-kiosk-ready.service

echo "ketahanan startup display terpasang untuk ${ethernet_interface}"
echo "reboot diperlukan untuk menguji boot tanpa kabel LAN"
systemctl --no-pager --full status aqms-kiosk-ready.service
