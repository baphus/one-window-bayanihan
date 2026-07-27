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
| `RUN_MIGRATIONS` | Keep `false`. Migrations are a deliberate pre-deploy step — a schema change must land before the code that needs it. |
| `RUN_SCHEDULER` | `true` on exactly **one** service. Two schedulers double-run retention and audit archiving. |
| `STORAGE_USE_PATH_STYLE` | `false` for Amazon S3. The application default is `true`, which suits MinIO and Supabase but breaks S3 virtual-hosted addressing. |
| `DB_SSLMODE` | `require`. The RDS parameter group sets `rds.force_ssl=1`, so a plaintext connection is rejected by the server. |
| `APP_URL` | Must match the hostname actually served. Inertia and Ziggy generate absolute URLs from it, so a stale value sends users back to the previous hostname. |

## Deploying

```powershell
$REGION = "ap-southeast-1"
$JSON   = "$env:USERPROFILE/.owb-secrets/staging-deployment.local.json"

# 1. Migrations FIRST, and read the exit code — never rely on the entrypoint.
php artisan migrate --force --no-interaction
if (-not $?) { throw "migrations failed — aborting deploy" }

# 2. Deploy.
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
