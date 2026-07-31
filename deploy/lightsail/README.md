# Lightsail deployment inputs

`app-deployment.template.json` is the shape of the payload for
`aws lightsail create-container-service-deployment`. Every `REPLACE_*` value is a
placeholder.

## Never commit a filled-in copy

Lightsail container environment variables are not a secret store. They hold the
database password, the mail API key, and the object-storage credentials in
plaintext, and anyone who can call `lightsail get-container-services` can read
them back. A filled-in copy of this file is therefore a credential dump.

`.gitignore` excludes `deploy/lightsail/*.local.json`. Use that suffix, or keep
the real file outside the repository entirely:

```powershell
Copy-Item deploy/lightsail/app-deployment.template.json `
          "$env:USERPROFILE/.owb-secrets/staging-deployment.local.json"
```

## Values that must not drift

| Key | Constraint |
|---|---|
| `APP_KEY` | **Never rotate.** `EncryptedString` and `EncryptedDate` casts mean a new key makes existing encrypted columns permanently unreadable. |
| `RUN_MIGRATIONS` | `true`. The database is not publicly reachable, so no external runner can migrate it — migrations run in the container at start via `migrate --force --isolated`, before nginx accepts traffic, and a failure refuses the boot so the previous deployment keeps serving. |
| `RUN_SCHEDULER` | `true` on exactly **one** service. Two schedulers double-run retention and audit archiving. |
| `STORAGE_USE_PATH_STYLE` | `true` for Cloudflare R2, MinIO, and Supabase S3. `false` for Amazon S3 (virtual-hosted addressing). The application default is `true`. |
| `DB_SSLMODE` | `require`. The RDS parameter group sets `rds.force_ssl=1`, so a plaintext connection is rejected by the server. |
| `APP_URL` | Must match the hostname actually served. Inertia and Ziggy generate absolute URLs from it, so a stale value sends users back to the previous hostname. |

## Deploying

```powershell
$REGION = "ap-southeast-1"
$JSON   = "$env:USERPROFILE/.owb-secrets/staging-deployment.local.json"

# 1. Snapshot first. `migrate:rollback` is a schema tool, not an application
#    rollback, and it cannot undo a backfilling migration.
aws lightsail create-relational-database-snapshot `
  --relational-database-name bayanihan-staging-db `
  --relational-database-snapshot-name "bayanihan-staging-db-predeploy-$(Get-Date -Format yyyyMMddHHmmss)" `
  --region $REGION

# 2. Deploy. Migrations run inside the container before it accepts traffic; a
#    failure refuses the boot, so the previous deployment keeps serving.
aws lightsail create-container-service-deployment --cli-input-json "file://$JSON" --region $REGION

# 3. Health-gate before announcing.
Invoke-WebRequest -Uri "https://REPLACE_HOSTNAME/up" -UseBasicParsing

# 4. Deep check — needs the monitoring token.
Invoke-WebRequest -Uri "https://REPLACE_HOSTNAME/api/readyz" -UseBasicParsing `
  -Headers @{ "X-Monitoring-Token" = "REPLACE_READINESS_TOKEN" }
```

Step 4 is the one that catches the failure mode `/up` cannot see: `/up` never
touches the database, so it answers 200 while the queue worker and scheduler are
unable to connect.

## Running a one-off artisan command

Lightsail has no `exec`, and the database is not reachable from outside the
platform, so a one-off command has to run as a temporary deployment: copy the
payload, drop the `publicEndpoint` block, and set

```json
"command": ["sh","-c","php artisan <command> && echo TASK_DONE && sleep 600"]
```

Deploy it, read the log, then **redeploy the real application** — a container
service has only one active deployment, so the app is down while this runs.
