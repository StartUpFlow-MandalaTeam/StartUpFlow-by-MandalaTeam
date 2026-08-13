#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
HOST="127.0.0.1"
PORT="8000"
PHP_BIN="${PHP_BIN:-php}"
MAILHOG_IMAGE="${MAILHOG_IMAGE:-mailhog/mailhog}"

mailhog_pid=""

cleanup() {
    if [[ -n "${mailhog_pid}" ]]; then
        kill "${mailhog_pid}" >/dev/null 2>&1 || true
    fi
}

trap cleanup EXIT

docker run --rm -p 1025:1025 -p 8025:8025 "${MAILHOG_IMAGE}" &
mailhog_pid="$!"

"${PHP_BIN}" -S "${HOST}:${PORT}" -t "${ROOT_DIR}"