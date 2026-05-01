#!/usr/bin/env bash
#
# bin/check-headers.sh — runtime security tests for the Lamixtape theme.
#
# Last update : 2026-05-01 (Phase A2 post-Phase-7).
#
# Verifies the 5 Phase 3 security headers + absence of X-Powered-By
# on (a) the home page and (b) the custom REST endpoint
# /wp-json/lamixtape/v1/posts. Then runs a 10-request burst stability
# check on the REST endpoint to ensure no 500/timeout under burst.
#
# IMPORTANT — this script does NOT test the rate-limit (100/h/IP).
# Triggering it would require 100+ sequential requests, which can
# trip Cloudflare WAF in prod or hammer the server. To validate the
# rate-limit manually post-deploy :
#
#     for i in {1..105}; do
#         curl -s -o /dev/null -w '%{http_code}\n' \
#             https://lamixtape.fr/wp-json/lamixtape/v1/posts?context=home
#     done
#
# The last 5 should be 429.
#
# Usage :
#     bash bin/check-headers.sh <BASE_URL>
#
# Examples :
#     bash bin/check-headers.sh https://lamixtape.local  # Local
#     bash bin/check-headers.sh https://lamixtape.fr     # Prod post-deploy
#
# URLs containing "local" auto-add the curl `-k` flag (skip cert
# validation for self-signed Local SSL).
#
# Exit codes :
#     0 — all checks passed
#     1 — at least one check failed (header missing, status mismatch,
#         burst instability, etc.)
#     2 — usage error (missing or invalid argument)
#
# Dependencies : curl + grep (standard on macOS / Linux). No external
# tools or runtime (no PHP / Node).

set -u

# -----------------------------------------------------------------------------
# Setup
# -----------------------------------------------------------------------------

if [ $# -ne 1 ]; then
    echo "Usage: bash $0 <BASE_URL>"
    echo "Example: bash $0 https://lamixtape.local"
    exit 2
fi

BASE_URL="$1"

# Strip trailing slash for consistent URL building below
BASE_URL="${BASE_URL%/}"

if [[ ! "$BASE_URL" =~ ^https?:// ]]; then
    echo "ERROR: BASE_URL must start with http:// or https://" >&2
    exit 2
fi

# Auto-detect Local for SSL bypass
CURL_OPTS=("-s" "-S")
if [[ "$BASE_URL" == *"local"* ]]; then
    CURL_OPTS+=("-k")
    echo "[info] Local URL detected, adding -k (skip cert validation)"
fi

REST_PATH="/wp-json/lamixtape/v1/posts?context=home&offset=0"
REST_URL="${BASE_URL}${REST_PATH}"

# Counters
PASS=0
FAIL=0

# Visual marks
OK="\033[0;32m✓\033[0m"
KO="\033[0;31m✗\033[0m"

# Helper : check that a header is present in the headers string passed
# as $1 (case-insensitive). $2 is the header name. $3 is an optional
# expected value (substring match).
check_header_present() {
    local headers="$1"
    local name="$2"
    local expected="${3:-}"

    local matching
    matching=$(printf '%s\n' "$headers" | grep -i "^${name}:" || true)

    if [ -z "$matching" ]; then
        printf "  ${KO} %-32s (missing)\n" "$name"
        FAIL=$((FAIL + 1))
        return 1
    fi

    if [ -n "$expected" ]; then
        if printf '%s' "$matching" | grep -qi "$expected"; then
            printf "  ${OK} %-32s (matches \"%s\")\n" "$name" "$expected"
            PASS=$((PASS + 1))
            return 0
        else
            printf "  ${KO} %-32s (present but value mismatch ; expected \"%s\")\n" "$name" "$expected"
            printf "       got: %s\n" "$matching"
            FAIL=$((FAIL + 1))
            return 1
        fi
    fi

    printf "  ${OK} %-32s (present)\n" "$name"
    PASS=$((PASS + 1))
    return 0
}

# Helper : check that a header is ABSENT
check_header_absent() {
    local headers="$1"
    local name="$2"

    if printf '%s\n' "$headers" | grep -qi "^${name}:"; then
        local matching
        matching=$(printf '%s\n' "$headers" | grep -i "^${name}:")
        printf "  ${KO} %-32s (LEAKED ; got: %s)\n" "$name" "$matching"
        FAIL=$((FAIL + 1))
        return 1
    fi

    printf "  ${OK} %-32s (absent ✓)\n" "$name"
    PASS=$((PASS + 1))
    return 0
}

# -----------------------------------------------------------------------------
# CHECK 1 — Home page headers
# -----------------------------------------------------------------------------

echo
echo "═══ Check 1 — Home page headers ($BASE_URL/) ═══"

HOME_HEADERS=$(curl "${CURL_OPTS[@]}" -I "$BASE_URL/" 2>&1)
if [ $? -ne 0 ] || [ -z "$HOME_HEADERS" ]; then
    echo "  ${KO} curl failed or empty response"
    FAIL=$((FAIL + 1))
else
    check_header_present "$HOME_HEADERS" "X-Content-Type-Options"     "nosniff"
    check_header_present "$HOME_HEADERS" "Referrer-Policy"            "strict-origin-when-cross-origin"
    check_header_present "$HOME_HEADERS" "Strict-Transport-Security"  "max-age=31536000"
    check_header_present "$HOME_HEADERS" "X-Frame-Options"            "SAMEORIGIN"
    check_header_present "$HOME_HEADERS" "Permissions-Policy"         ""
    check_header_absent  "$HOME_HEADERS" "X-Powered-By"
fi

# -----------------------------------------------------------------------------
# CHECK 2 — REST endpoint headers (without nonce → expect 403 + headers)
# -----------------------------------------------------------------------------

echo
echo "═══ Check 2 — REST endpoint headers + 403 ($REST_PATH) ═══"

REST_HEADERS=$(curl "${CURL_OPTS[@]}" -I "$REST_URL" 2>&1)
if [ $? -ne 0 ] || [ -z "$REST_HEADERS" ]; then
    echo "  ${KO} curl failed or empty response"
    FAIL=$((FAIL + 1))
else
    # First line of headers should be HTTP/N 403
    REST_STATUS=$(printf '%s\n' "$REST_HEADERS" | head -1 | grep -oE '[0-9]{3}' | head -1)
    if [ "$REST_STATUS" = "403" ]; then
        printf "  ${OK} %-32s (403 forbidden as expected without nonce)\n" "HTTP status"
        PASS=$((PASS + 1))
    else
        printf "  ${KO} %-32s (expected 403, got %s)\n" "HTTP status" "$REST_STATUS"
        FAIL=$((FAIL + 1))
    fi

    # All 5 Phase 3 headers + absence X-Powered-By, even on 403 reject
    check_header_present "$REST_HEADERS" "X-Content-Type-Options"     "nosniff"
    check_header_present "$REST_HEADERS" "Referrer-Policy"            "strict-origin-when-cross-origin"
    check_header_present "$REST_HEADERS" "Strict-Transport-Security"  "max-age=31536000"
    check_header_present "$REST_HEADERS" "X-Frame-Options"            "SAMEORIGIN"
    check_header_present "$REST_HEADERS" "Permissions-Policy"         ""
    check_header_absent  "$REST_HEADERS" "X-Powered-By"
fi

# -----------------------------------------------------------------------------
# CHECK 3 — Burst stability (10 reqs sequential, NOT a rate-limit test)
# -----------------------------------------------------------------------------

echo
echo "═══ Check 3 — Burst stability (10 reqs sequential) ═══"
echo "  ⚠ Rate-limit (100/h/IP) NOT tested by this script."
echo "    To test rate-limit manually post-deploy:"
echo "      for i in {1..105}; do curl -s -o /dev/null -w '%{http_code}\\n' \\"
echo "        ${REST_URL}; done"
echo "    Last 5 should be 429."
echo

BURST_FAILED=0
BURST_NON_403=0

for i in 1 2 3 4 5 6 7 8 9 10; do
    STATUS=$(curl "${CURL_OPTS[@]}" -o /dev/null -w "%{http_code}" --max-time 5 "$REST_URL" 2>/dev/null || echo "TIMEOUT")
    if [ "$STATUS" = "TIMEOUT" ] || [ -z "$STATUS" ]; then
        printf "  ${KO} req %2d : TIMEOUT or curl failure\n" "$i"
        BURST_FAILED=$((BURST_FAILED + 1))
    elif [[ "$STATUS" =~ ^5[0-9]{2}$ ]]; then
        printf "  ${KO} req %2d : %s (server error)\n" "$i" "$STATUS"
        BURST_FAILED=$((BURST_FAILED + 1))
    elif [ "$STATUS" != "403" ]; then
        printf "  ⚠ req %2d : %s (expected 403 ; not necessarily a failure but unusual)\n" "$i" "$STATUS"
        BURST_NON_403=$((BURST_NON_403 + 1))
    else
        printf "  ${OK} req %2d : %s\n" "$i" "$STATUS"
    fi
done

if [ "$BURST_FAILED" -eq 0 ]; then
    printf "\n  ${OK} %-32s (10/10 reqs survived burst)\n" "Burst stability"
    PASS=$((PASS + 1))
    if [ "$BURST_NON_403" -gt 0 ]; then
        printf "  ⚠ %d non-403 responses noted above (informational)\n" "$BURST_NON_403"
    fi
else
    printf "\n  ${KO} %-32s (%d/10 reqs failed)\n" "Burst stability" "$BURST_FAILED"
    FAIL=$((FAIL + 1))
fi

# -----------------------------------------------------------------------------
# Summary
# -----------------------------------------------------------------------------

echo
echo "═══ Summary ═══"
TOTAL=$((PASS + FAIL))
printf "  Checks passed : %d / %d\n" "$PASS" "$TOTAL"
printf "  Checks failed : %d / %d\n" "$FAIL" "$TOTAL"
echo

if [ "$FAIL" -eq 0 ]; then
    printf "${OK} All checks passed.\n"
    exit 0
else
    printf "${KO} ${FAIL} check(s) failed. See output above for details.\n"
    exit 1
fi
