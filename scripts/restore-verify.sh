#!/usr/bin/env bash
set -euo pipefail

# ═══════════════════════════════════════════════════════════════════════
# restore-test.sh — Test restore of a PostgreSQL backup
# ═══════════════════════════════════════════════════════════════════════
#
# Usage:
#   ./scripts/restore-test.sh <backup-file>
#
# Restores a backup to a temporary test database to verify integrity.
# The test DB is dropped after verification.
#
# Requires:
#   - PostgreSQL client (psql, pg_restore, createdb, dropdb)
#   - gpg (if backup is encrypted)
# ═══════════════════════════════════════════════════════════════════════

if [ $# -lt 1 ]; then
    echo "Usage: $0 <backup-file>"
    echo "Example: $0 storage/backups/bayanihan-db-20250101-120000.sql.gz.enc"
    exit 1
fi

BACKUP_FILE="$1"
TEST_DB="bayanihan_restore_test_$(date +%s)"

if [ ! -f "${BACKUP_FILE}" ]; then
    echo "❌ Backup file not found: ${BACKUP_FILE}"
    exit 1
fi

echo "🔍 Testing restore from: ${BACKUP_FILE}"

# 1. Decrypt if encrypted
DECRYPTED="/tmp/restore-test-$$.sql.gz"
if [[ "${BACKUP_FILE}" == *.enc ]]; then
    echo "🔐 Decrypting..."
    gpg --yes --batch --decrypt "${BACKUP_FILE}" > "${DECRYPTED}" 2>/dev/null || {
        echo "❌ Decryption failed — wrong key?"
        rm -f "${DECRYPTED}"
        exit 1
    }
else
    cp "${BACKUP_FILE}" "${DECRYPTED}"
fi

# 2. Create test database
echo "🗄️  Creating test database: ${TEST_DB}"
createdb "${TEST_DB}"

# 3. Restore
echo "⏳ Restoring..."
if gunzip -c "${DECRYPTED}" | psql -d "${TEST_DB}" > /dev/null 2>&1; then
    echo "✅ Restore successful"
    
    # 4. Verify — count tables
    TABLE_COUNT=$(psql -d "${TEST_DB}" -t -c "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public';" | tr -d ' ')
    echo "📊 Tables restored: ${TABLE_COUNT}"
    
    # 5. Verify — count rows across key tables
    echo "📊 Row counts:"
    for table in cases clients users referrals milestones; do
        COUNT=$(psql -d "${TEST_DB}" -t -c "SELECT count(*) FROM ${table};" 2>/dev/null || echo "0")
        echo "   ${table}: ${COUNT}"
    done
else
    echo "❌ Restore FAILED"
    dropdb "${TEST_DB}" 2>/dev/null || true
    rm -f "${DECRYPTED}"
    exit 1
fi

# 6. Clean up
echo "🧹 Dropping test database..."
dropdb "${TEST_DB}"
rm -f "${DECRYPTED}"

echo "🎉 Restore test passed — backup integrity confirmed"
