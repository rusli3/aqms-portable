#!/usr/bin/env bash
set -Eeuo pipefail

project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
test_dir="$(mktemp -d)"
trap 'rm -rf -- "${test_dir}"' EXIT

cat >"${test_dir}/curl" <<'EOF'
#!/usr/bin/env bash
count_file="${AQMS_TEST_COUNT_FILE:?}"
count=0
[[ -f "${count_file}" ]] && read -r count <"${count_file}"
count=$((count + 1))
printf '%s\n' "${count}" >"${count_file}"
((count >= 3))
EOF

cat >"${test_dir}/systemctl" <<'EOF'
#!/usr/bin/env bash
printf '%s\n' "$*" >>"${AQMS_TEST_SYSTEMCTL_LOG:?}"
EOF

cat >"${test_dir}/noop" <<'EOF'
#!/usr/bin/env bash
exit 0
EOF

chmod 0755 "${test_dir}/curl" "${test_dir}/systemctl" "${test_dir}/noop"

AQMS_TEST_COUNT_FILE="${test_dir}/attempts" \
AQMS_TEST_SYSTEMCTL_LOG="${test_dir}/systemctl.log" \
AQMS_CURL_BIN="${test_dir}/curl" \
AQMS_SYSTEMCTL_BIN="${test_dir}/systemctl" \
AQMS_SLEEP_BIN="${test_dir}/noop" \
AQMS_LOGGER_BIN="${test_dir}/noop" \
AQMS_KIOSK_READY_ATTEMPTS=4 \
    "${project_dir}/deploy/systemd/aqms-kiosk-ready"

[[ "$(<"${test_dir}/attempts")" == "3" ]]
diff -u <(printf '%s\n' \
    'restart snap.wpe-webkit-mir-kiosk.daemon.service') \
    "${test_dir}/systemctl.log"

bash -n "${project_dir}/scripts/install-display-resilience.sh"
echo "kiosk readiness test passed"
