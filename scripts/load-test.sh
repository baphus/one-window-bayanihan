#!/usr/bin/env bash
set -euo pipefail

# ═══════════════════════════════════════════════════════════════════════
# load-test.sh — Basic load test for One Window Bayanihan endpoints
# ═══════════════════════════════════════════════════════════════════════
#
# Usage:
#   APP_URL=https://staging.example.com ./scripts/load-test.sh
#   APP_URL=http://localhost:8000 ./scripts/load-test.sh
# ═══════════════════════════════════════════════════════════════════════

APP_URL="${APP_URL:-http://localhost:8000}"
CONCURRENCY="${CONCURRENCY:-10}"
REQUESTS="${REQUESTS:-50}"

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo "========================================"
echo "  Load Test — One Window Bayanihan"
echo "========================================"
echo "Target:      ${APP_URL}"
echo "Concurrency: ${CONCURRENCY}"
echo "Requests:    ${REQUESTS}"
echo ""

# Check if hey or ab is available
TOOL=""
if command -v hey &> /dev/null; then
    TOOL="hey"
elif command -v ab &> /dev/null; then
    TOOL="ab"
else
    echo -e "${YELLOW}⚠️  Neither 'hey' nor 'ab' (Apache Bench) found.${NC}"
    echo "   Install hey: brew install hey / apt install hey / scoop install hey"
    echo "   Install ab:  apt install apache2-utils"
    echo ""
    echo "   Falling back to sequential curl requests..."
    TOOL="curl"
fi

run_test() {
    local name="$1"
    local endpoint="$2"
    
    echo -e "  ${name}: ${endpoint}"
    
    case "${TOOL}" in
        hey)
            hey -n "${REQUESTS}" -c "${CONCURRENCY}" -q 10 "${APP_URL}${endpoint}" 2>/dev/null | grep -E '(Requests/sec|Average|latency|Total|Status)' | head -6
            ;;
        ab)
            ab -n "${REQUESTS}" -c "${CONCURRENCY}" "${APP_URL}${endpoint}" 2>/dev/null | grep -E '(Requests per second|Time per request|Transfer rate|Failed requests|Non-2xx)' | head -5
            ;;
        curl)
            local total=0
            local success=0
            local fail=0
            local max_time=0
            
            for i in $(seq 1 "${REQUESTS}"); do
                start=$(date +%s%N)
                code=$(curl -s -o /dev/null -w "%{http_code}" --max-time 5 "${APP_URL}${endpoint}" 2>/dev/null || echo "000")
                end=$(date +%s%N)
                elapsed=$(( (end - start) / 1000000 ))  # ms
                
                total=$((total + elapsed))
                if [ "${code}" -ge 200 ] && [ "${code}" -lt 400 ]; then
                    success=$((success + 1))
                else
                    fail=$((fail + 1))
                fi
                if [ "${elapsed}" -gt "${max_time}" ]; then
                    max_time="${elapsed}"
                fi
            done
            
            avg=$((total / REQUESTS))
            echo "    Success: ${success}, Failed: ${fail}"
            echo "    Avg time: ${avg}ms, Max time: ${max_time}ms"
            ;;
    esac
    echo ""
}

# Run endpoint tests
echo "--- Endpoint Tests ---"
run_test "Home/Landing"    "/"
run_test "Login page"      "/login"
run_test "Public API"      "/api/addresses/provinces"

echo "--- Results Summary ---"
echo "Test completed at $(date)"
