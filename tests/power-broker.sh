#!/usr/bin/env bash
set -Eeuo pipefail

project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
broker="${project_dir}/deploy/systemd/aqms-power-control"
test_dir="$(mktemp -d)"

cleanup() { rm -rf -- "$test_dir"; }
trap cleanup EXIT

run_valid_request() {
    local action="$1"
    local request_path="${test_dir}/request"

    printf 'v1 %s %s 0123456789abcdef0123456789abcdef\n' \
        "$action" "$(date +%s)" > "$request_path"
    result="$(AQMS_CONTROL_REQUEST_PATH="$request_path" \
        AQMS_POWER_CONTROL_DRY_RUN=1 "$broker")"
    [[ "$result" == "$action" ]]
    [[ ! -e "$request_path" ]]
}

run_invalid_request() {
    local request="$1"
    local request_path="${test_dir}/request"

    printf '%s\n' "$request" > "$request_path"
    if AQMS_CONTROL_REQUEST_PATH="$request_path" \
        AQMS_POWER_CONTROL_DRY_RUN=1 "$broker"; then
        echo "invalid request unexpectedly accepted" >&2
        exit 1
    fi
    [[ ! -e "$request_path" ]]
}

run_valid_request reboot
run_valid_request shutdown
run_invalid_request 'v1 arbitrary 1000000000 0123456789abcdef0123456789abcdef'
run_invalid_request 'v1 reboot 1000000000 0123456789abcdef0123456789abcdef'
run_invalid_request 'v1 reboot 9999999999 not-a-valid-nonce'

echo "power broker validation tests passed"
