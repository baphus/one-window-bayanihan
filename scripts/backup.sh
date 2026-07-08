#!/usr/bin/env bash
set -euo pipefail

# ═══════════════════════════════════════════════════════════════════════
# backup.sh — PostgreSQL backup with encryption and offsite storage
# ═══════════════════════════════════════════════════════════════════════
#
# Usage:
#   ./scripts/backup.sh                        # uses .env DATABASE_URL
#   DATABASE_URL="postgresql://..." ./scripts/backup.sh
#
# Requires:
#   - pg_dump (PostgreSQL client)
#   - gpg       (for encryption, optional — set ENCRYPT_KEY)
#   - curl      (for offsite upload, optional — set UPLOAD_URL + UPLOAD_TOKEN)
#
# Environment variables:
#   DATABASE_URL    — PostgreSQL connection string
#   BACKUP_DIR      — local backup directory (default: ./storage/backups)
#   ENCRYPT_KEY     — GPG key ID for encryption (optional)
#   UPLOAD_URL      — S3-compatible upload endpoint (optional)
#   UPLOAD_TOKEN    — Bearer token for upload (optional)
#   RETENTION_DAYS  — days to keep local backups (default: 7)
# ═══════════════════════════════════════════════════════════════════════

BACKUP_DIR="${BACKUP_DIR:-./storage/backups}"
RETENTION_DAYS="${RETENTION_DAYS:-7}"
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
FILENAME="bayanihan-db-${TIMESTAMP}.sql.gz.enc"
LOCAL_PATH="${BACKUP_DIR}/${FILENAME}"

# Ensure backup directory exists
mkdir -p "${BACKUP_DIR}"

echo "📦 Starting database backup..."

# 1. Dump and compress
pg_dump "${DATABASE_URL}" --no-owner --no-acl | gzip > "/tmp/${FILENAME}"

# 2. Encrypt (if ENCRYPT_KEY is set)
if [ -n "${ENCRYPT_KEY:-}" ]; then
    echo "🔐 Encrypting with GPG key: ${ENCRYPT_KEY}"
    gpg --yes --batch --recipient "${ENCRYPT_KEY}" --encrypt "/tmp/${FILENAME}"
    mv "/tmp/${FILENAME}.gpg" "${LOCAL_PATH}"
    rm -f "/tmp/${FILENAME}"
else
    mv "/tmp/${FILENAME}" "${LOCAL_PATH}"
fi

echo "✅ Local backup saved: ${LOCAL_PATH} ($(du -h "${LOCAL_PATH}" | cut -f1))"

# 3. Upload to offsite storage (optional)
if [ -n "${UPLOAD_URL:-}" ] && [ -n "${UPLOAD_TOKEN:-}" ]; then
    echo "☁️  Uploading to offsite storage..."
    HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" \
        -X PUT \
        -H "Authorization: Bearer ${UPLOAD_TOKEN}" \
        -H "Content-Type: application/octet-stream" \
        --data-binary @"${LOCAL_PATH}" \
        "${UPLOAD_URL}/${FILENAME}")
    
    if [ "${HTTP_STATUS}" -ge 200 ] && [ "${HTTP_STATUS}" -lt 300 ]; then
        echo "✅ Offsite upload complete (HTTP ${HTTP_STATUS})"
    else
        echo "⚠️  Offsite upload returned HTTP ${HTTP_STATUS}"
    fi
fi

# 4. Rotate old backups
echo "🧹 Cleaning backups older than ${RETENTION_DAYS} days..."
find "${BACKUP_DIR}" -name "bayanihan-db-*.sql.gz.enc" -mtime "+${RETENTION_DAYS}" -delete
find "${BACKUP_DIR}" -name "bayanihan-db-*.sql.gz" -mtime "+${RETENTION_DAYS}" -delete

echo "🎉 Backup complete"
