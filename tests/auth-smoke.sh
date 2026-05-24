#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
HOST="127.0.0.1"
PORT="8123"
BASE_URL="http://${HOST}:${PORT}"
PHP_BIN="${PHP_BIN:-php}"
SERVER_LOG="$(mktemp)"
TMP_DIR="$(mktemp -d)"
COOKIE_JAR="${TMP_DIR}/cookies.txt"
SERVER_PID=""

cleanup() {
    if [[ -n "${SERVER_PID}" ]]; then
        kill "${SERVER_PID}" >/dev/null 2>&1 || true
    fi

    rm -f "${SERVER_LOG}"
    rm -rf "${TMP_DIR}"
}

trap cleanup EXIT

"${PHP_BIN}" -S "${HOST}:${PORT}" -t "${ROOT_DIR}" >"${SERVER_LOG}" 2>&1 &
SERVER_PID="$!"

wait_for_server() {
    for _ in {1..60}; do
        if curl -fsS "${BASE_URL}/auth/login.php" >/dev/null 2>&1; then
            return 0
        fi
    done

    return 1
}

if ! wait_for_server; then
    echo "Server did not start"
    cat "${SERVER_LOG}"
    exit 1
fi

fetch_page() {
    local path="$1"
    curl -fsS -b "${COOKIE_JAR}" -c "${COOKIE_JAR}" "${BASE_URL}${path}"
}

extract_csrf() {
    sed -n 's/.*name="csrf_token" value="\([^"]*\)".*/\1/p' | head -n1
}

post_headers() {
    local path="$1"
    shift
    curl -sS -D - -o /dev/null -b "${COOKIE_JAR}" -c "${COOKIE_JAR}" -X POST "${BASE_URL}${path}" "$@"
}

assert_contains() {
    local haystack="$1"
    local needle="$2"

    if [[ "${haystack}" != *"${needle}"* ]]; then
        echo "Expected to find '${needle}'"
        exit 1
    fi
}

assert_redirect_contains() {
    local headers="$1"
    local needle="$2"

    if [[ "${headers}" != *"${needle}"* ]]; then
        echo "Expected redirect containing '${needle}'"
        echo "Headers were:"
        printf '%s\n' "${headers}"
        exit 1
    fi
}

login_html="$(fetch_page /auth/login.php)"
register_html="$(fetch_page /auth/register.php)"
reset_html="$(fetch_page /auth/reset-password.php)"

assert_contains "${login_html}" "Remember me"
assert_contains "${register_html}" "Register"
assert_contains "${reset_html}" "Password Reset"

login_csrf="$(printf '%s' "${login_html}" | extract_csrf)"
register_csrf="$(printf '%s' "${register_html}" | extract_csrf)"
reset_csrf="$(printf '%s' "${reset_html}" | extract_csrf)"

if [[ -z "${login_csrf}" || -z "${register_csrf}" || -z "${reset_csrf}" ]]; then
    echo "Failed to extract one or more CSRF tokens"
    exit 1
fi

login_headers="$(post_headers /auth/process-login.php \
    --data-urlencode "csrf_token=${login_csrf}" \
    --data-urlencode "Username=" \
    --data-urlencode "Password=")"
assert_redirect_contains "${login_headers}" "Location: login.php?error=empty"

register_headers="$(post_headers /auth/process-register.php \
    --data-urlencode "csrf_token=${register_csrf}" \
    --data-urlencode "Username=SmokeUser" \
    --data-urlencode "Email=invalid-email" \
    --data-urlencode "Password=Aa1!aaaa" \
    --data-urlencode "CPassword=Aa1!aaaa")"
assert_redirect_contains "${register_headers}" "Location: register.php?error=email_invalid"

reset_headers="$(post_headers /auth/process-reset.php \
    --data-urlencode "csrf_token=${reset_csrf}" \
    --data-urlencode "email=invalid-email")"
assert_redirect_contains "${reset_headers}" "Location: reset-password.php?sent=1"

verify_headers="$(curl -sS -D - -o /dev/null -b "${COOKIE_JAR}" -c "${COOKIE_JAR}" "${BASE_URL}/auth/verify-email.php")"
assert_redirect_contains "${verify_headers}" "Location: login.php?error=verification_invalid"

echo "Auth smoke test passed"