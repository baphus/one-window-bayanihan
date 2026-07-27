# AWS Staging Deployment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deploy One Window Bayanihan and all its features to a publicly reachable AWS staging environment over HTTPS, with no domain name required.

**Architecture:** A single Lightsail container service (power Small, scale 1) runs the existing project Dockerfile image behind Lightsail's free HTTPS endpoint. State lives in Amazon RDS PostgreSQL 17.10 and Amazon S3. Redis is deliberately omitted in favour of the database cache/queue fallback because Lightsail containers have no persistent volumes. Images are built in GitHub Actions and pushed to a private ECR repository, which Lightsail pulls from — no local Docker required.

**Tech Stack:** Laravel 13, Inertia/React 18, PHP 8.4, PostgreSQL 17.10, Amazon S3, AWS Lightsail Containers, Amazon ECR, GitHub Actions, Gmail SMTP.

**Spec:** `docs/superpowers/specs/2026-07-27-aws-staging-deployment-design-v1.0.0.md`

## Global Constraints

Every task's requirements implicitly include this section.

- **AWS account:** `677206905439`. **Region:** `ap-southeast-1` for every resource. ECR and the Lightsail container service **must** be in the same region.
- **Default VPC:** `vpc-0b4021ede69666339`. Subnets: `subnet-0c101b55d135fb364` (1a), `subnet-06e2c9c301e9c04cf` (1b), `subnet-07d49c5f8ef213e8a` (1c).
- **PostgreSQL engine version:** `17.10`.
- **Container service:** name `bayanihan-staging`, power `small`, **scale `1`**.
- **`scale` must never exceed 1.** `docker/supervisord.conf` runs `schedule:work` inside the web container; a second node double-runs retention and archive jobs and corrupts retention evidence.
- **`RUN_MIGRATIONS=false` always.** `docker/php/docker-entrypoint.sh:32` runs migrations with `|| true`, which would serve traffic on a broken schema. Migrations run as a deliberate release step.
- **`APP_KEY` is generated once and never rotated.** The app uses `EncryptedString` and `EncryptedDate` casts; changing `APP_KEY` makes existing encrypted column data permanently unreadable.
- **`MAIL_FROM_ADDRESS` must equal `MAIL_USERNAME`.** Gmail rejects or rewrites mismatched senders.
- **No real OFW data in this environment.** Synthetic and seeded data only. This is the control that makes the publicly-reachable database tolerable (ISO 27001 A.8.31).
- **Never commit secrets.** All JSON files containing credentials are written to `C:\Users\JKsars\.claude\jobs\30a82c39\tmp\` which is outside the repository. Record final values in a password manager.
- **AWS CLI PATH:** every PowerShell command that calls `aws` must first run:
  `$env:Path = [Environment]::GetEnvironmentVariable('Path','Machine') + ';' + [Environment]::GetEnvironmentVariable('Path','User')`
- **AWS CLI UTF-8:** set `$env:PYTHONIOENCODING='utf-8'` before any `aws` command that prints descriptions, or the CLI crashes with `'charmap' codec can't encode character`.
- **Progress reporting:** RDS creation takes 5–15 minutes and Lightsail deployments 3–8 minutes. Report a status check every 15 minutes during any wait, based on actual command output rather than assumption.
- **Bootstrap runs as root; ongoing deployment does not.** Tasks 1–8 create IAM resources, which requires administrative permissions the account currently only has via root. Task 9 Step 3 creates the scoped `bayanihan-deploy` user and verifies that redeployment works without root. Enabling MFA on root and moving human access to IAM Identity Center remain open follow-ups.
- **Every PowerShell step starts a fresh shell.** Environment variables such as `PGPASSWORD`, `AWS_ACCESS_KEY_ID`, and the `PATH` refresh do **not** persist between steps. Each step that needs them must set them again.

## File Structure

| File | Responsibility | Action |
|---|---|---|
| `.github/workflows/build-image.yml` | Build the Docker image in CI and push it to ECR | Create |
| `docs/DEPLOYMENT_STAGING_AWS_v1.0.0.md` | Runbook recording the live resource identifiers and operational commands (no secrets) | Create |
| `$JOB_TMP/s3-policy.json` | Least-privilege S3 IAM policy for the app user | Create (outside repo) |
| `$JOB_TMP/ecr-policy.json` | ECR repository policy granting the Lightsail puller role | Create (outside repo) |
| `$JOB_TMP/probe-deployment.json` | Throwaway deployment that tests SMTP egress | Create (outside repo) |
| `$JOB_TMP/app-deployment.json` | Real application deployment, contains secrets | Create (outside repo) |

Throughout this plan `$JOB_TMP` means `C:\Users\JKsars\.claude\jobs\30a82c39\tmp`.

No application source code changes are expected. Task 1 Step 6 is the one place that may require a `Dockerfile` change.

---

### Task 1: Build the image in CI and push it to ECR

**Files:**
- Create: `.github/workflows/build-image.yml`
- Create: `$JOB_TMP/ecr-ci-policy.json`

**Interfaces:**
- Produces: ECR repository `bayanihan`, image URI `677206905439.dkr.ecr.ap-southeast-1.amazonaws.com/bayanihan:<git-sha>`, consumed by Task 4 and Task 7.

- [ ] **Step 1: Verify the ECR repository does not already exist**

```powershell
$env:Path = [Environment]::GetEnvironmentVariable('Path','Machine') + ';' + [Environment]::GetEnvironmentVariable('Path','User')
$env:PYTHONIOENCODING='utf-8'
aws ecr describe-repositories --repository-names bayanihan --region ap-southeast-1
```

Expected: `RepositoryNotFoundException`. If it already exists, skip Step 2.

- [ ] **Step 2: Create the ECR repository with image scanning enabled**

```powershell
aws ecr create-repository --repository-name bayanihan --region ap-southeast-1 --image-scanning-configuration scanOnPush=true --image-tag-mutability IMMUTABLE
```

Expected: JSON containing `"repositoryUri": "677206905439.dkr.ecr.ap-southeast-1.amazonaws.com/bayanihan"`.

`scanOnPush=true` gives vulnerability scanning of the built image (ISO 27001 A.8.8). `IMMUTABLE` tags prevent a tag being silently repointed, which is what makes "redeploy the previous tag" a trustworthy rollback.

- [ ] **Step 3: Create an IAM user for GitHub Actions with ECR push permission only**

```powershell
aws iam create-user --user-name bayanihan-ci-ecr
```

Write the policy to `$JOB_TMP/ecr-ci-policy.json`:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "EcrAuth",
      "Effect": "Allow",
      "Action": "ecr:GetAuthorizationToken",
      "Resource": "*"
    },
    {
      "Sid": "EcrPushToBayanihanOnly",
      "Effect": "Allow",
      "Action": [
        "ecr:BatchCheckLayerAvailability",
        "ecr:CompleteLayerUpload",
        "ecr:InitiateLayerUpload",
        "ecr:PutImage",
        "ecr:UploadLayerPart",
        "ecr:BatchGetImage",
        "ecr:GetDownloadUrlForLayer"
      ],
      "Resource": "arn:aws:ecr:ap-southeast-1:677206905439:repository/bayanihan"
    }
  ]
}
```

```powershell
aws iam put-user-policy --user-name bayanihan-ci-ecr --policy-name ecr-push-bayanihan --policy-document file://C:\Users\JKsars\.claude\jobs\30a82c39\tmp\ecr-ci-policy.json
aws iam create-access-key --user-name bayanihan-ci-ecr
```

Expected: JSON with `AccessKeyId` and `SecretAccessKey`. **Record both immediately — the secret is shown only once.**

`ecr:GetAuthorizationToken` cannot be scoped to a resource; every other action is scoped to this one repository.

- [ ] **Step 4: Add the credentials as GitHub repository secrets**

```powershell
gh secret set AWS_ECR_ACCESS_KEY_ID --body "<AccessKeyId>"
gh secret set AWS_ECR_SECRET_ACCESS_KEY --body "<SecretAccessKey>"
gh secret list
```

Expected: both `AWS_ECR_ACCESS_KEY_ID` and `AWS_ECR_SECRET_ACCESS_KEY` listed.

- [ ] **Step 5: Create the build workflow**

Create `.github/workflows/build-image.yml`:

```yaml
name: Build and Push Image

on:
  workflow_dispatch:
  push:
    branches: [main]
    paths-ignore:
      - 'docs/**'
      - '**.md'

env:
  AWS_REGION: ap-southeast-1
  ECR_REPOSITORY: bayanihan

jobs:
  build:
    name: Build image and push to ECR
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Configure AWS credentials
        uses: aws-actions/configure-aws-credentials@v4
        with:
          aws-access-key-id: ${{ secrets.AWS_ECR_ACCESS_KEY_ID }}
          aws-secret-access-key: ${{ secrets.AWS_ECR_SECRET_ACCESS_KEY }}
          aws-region: ${{ env.AWS_REGION }}

      - name: Log in to Amazon ECR
        id: ecr
        uses: aws-actions/amazon-ecr-login@v2

      - name: Build image
        run: docker build -t bayanihan:${{ github.sha }} .

      - name: Verify PHP extensions required by features
        run: |
          docker run --rm bayanihan:${{ github.sha }} php -m > /tmp/mods.txt
          cat /tmp/mods.txt
          for ext in pdo_pgsql pdo_sqlite redis bcmath gd intl pcntl exif zip; do
            grep -qx "$ext" /tmp/mods.txt || { echo "MISSING PHP EXTENSION: $ext"; exit 1; }
          done
          docker run --rm bayanihan:${{ github.sha }} php -r \
            '$d = new PDO("sqlite::memory:"); $d->exec("CREATE VIRTUAL TABLE t USING fts5(c)"); echo "FTS5 OK\n";'

      - name: Push image to ECR
        env:
          REGISTRY: ${{ steps.ecr.outputs.registry }}
        run: |
          docker tag bayanihan:${{ github.sha }} $REGISTRY/$ECR_REPOSITORY:${{ github.sha }}
          docker push $REGISTRY/$ECR_REPOSITORY:${{ github.sha }}
          echo "Pushed $REGISTRY/$ECR_REPOSITORY:${{ github.sha }}"
```

The extension check is verification item **V3** from the spec, run in CI so it can never silently regress. `pdo_sqlite` is not explicitly installed by the Dockerfile — it is inherited from the `php:8.4-fpm` base image — and the AI chatbot's FTS5 retrieval depends on it. The `CREATE VIRTUAL TABLE ... USING fts5` probe proves FTS5 is compiled in, not merely that SQLite exists.

- [ ] **Step 6: Commit and run the workflow**

```powershell
git checkout -b deploy/aws-staging
git add .github/workflows/build-image.yml
git commit -m "ci: build container image and push to ECR for AWS staging deployment"
git push -u origin deploy/aws-staging
gh workflow run build-image.yml --ref deploy/aws-staging
```

- [ ] **Step 7: Verify the image is in ECR**

```powershell
gh run watch
aws ecr list-images --repository-name bayanihan --region ap-southeast-1 --query "imageIds[].imageTag" --output text
```

Expected: the workflow succeeds and the git SHA appears as a tag.

**If the extension check fails on `pdo_sqlite`:** add `pdo_sqlite` to the `docker-php-ext-install` list in `Dockerfile:41-56`, commit, and re-run. Do not proceed with `AI_CHATBOT_ENABLED=true` until this passes.

- [ ] **Step 8: Record the image URI**

Note `677206905439.dkr.ecr.ap-southeast-1.amazonaws.com/bayanihan:<sha>`. Tasks 4 and 7 need it.

---

### Task 2: Create the S3 bucket and the application IAM user

**Files:**
- Create: `$JOB_TMP/s3-policy.json`

**Interfaces:**
- Produces: bucket `bayanihan-staging-files`; IAM access key pair for `bayanihan-staging-app`, consumed by Task 7 as `STORAGE_ACCESS_KEY` / `STORAGE_SECRET_KEY`.

- [ ] **Step 1: Verify the bucket name is free**

```powershell
aws s3api head-bucket --bucket bayanihan-staging-files --region ap-southeast-1
```

Expected: `404` / `Not Found`. If it succeeds, the bucket already exists — inspect before reusing.

- [ ] **Step 2: Create the bucket**

```powershell
aws s3api create-bucket --bucket bayanihan-staging-files --region ap-southeast-1 --create-bucket-configuration LocationConstraint=ap-southeast-1
```

Expected: JSON with `"Location"`.

- [ ] **Step 3: Block all public access, enable versioning and encryption**

```powershell
aws s3api put-public-access-block --bucket bayanihan-staging-files --public-access-block-configuration "BlockPublicAcls=true,IgnorePublicAcls=true,BlockPublicPolicy=true,RestrictPublicBuckets=true"
aws s3api put-bucket-versioning --bucket bayanihan-staging-files --versioning-configuration Status=Enabled
aws s3api put-bucket-encryption --bucket bayanihan-staging-files --server-side-encryption-configuration "{\"Rules\":[{\"ApplyServerSideEncryptionByDefault\":{\"SSEAlgorithm\":\"AES256\"},\"BucketKeyEnabled\":true}]}"
```

Versioning is what backs the audit-archive immutability claim. Public access block is mandatory: case documents contain personal data.

- [ ] **Step 4: Verify all three settings applied**

```powershell
aws s3api get-public-access-block --bucket bayanihan-staging-files --query "PublicAccessBlockConfiguration"
aws s3api get-bucket-versioning --bucket bayanihan-staging-files
aws s3api get-bucket-encryption --bucket bayanihan-staging-files --query "ServerSideEncryptionConfiguration.Rules[0]"
```

Expected: all four block flags `true`; `"Status": "Enabled"`; `"SSEAlgorithm": "AES256"`.

- [ ] **Step 5: Create the application IAM user with a least-privilege policy**

Write `$JOB_TMP/s3-policy.json`:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "ListOwnBucketOnly",
      "Effect": "Allow",
      "Action": ["s3:ListBucket", "s3:GetBucketLocation"],
      "Resource": "arn:aws:s3:::bayanihan-staging-files"
    },
    {
      "Sid": "ObjectAccessWithinBucketOnly",
      "Effect": "Allow",
      "Action": ["s3:GetObject", "s3:PutObject", "s3:DeleteObject"],
      "Resource": "arn:aws:s3:::bayanihan-staging-files/*"
    }
  ]
}
```

```powershell
aws iam create-user --user-name bayanihan-staging-app
aws iam put-user-policy --user-name bayanihan-staging-app --policy-name s3-staging-files --policy-document file://C:\Users\JKsars\.claude\jobs\30a82c39\tmp\s3-policy.json
aws iam create-access-key --user-name bayanihan-staging-app
```

Expected: `AccessKeyId` and `SecretAccessKey`. **Record both now.**

No `s3:DeleteObjectVersion` is granted, so the application cannot destroy prior versions of audit archives even if compromised.

- [ ] **Step 6: Verify the credentials work and are correctly scoped**

```powershell
$env:AWS_ACCESS_KEY_ID='<app AccessKeyId>'
$env:AWS_SECRET_ACCESS_KEY='<app SecretAccessKey>'
"probe" | Out-File -FilePath "$env:CLAUDE_JOB_DIR\tmp\probe.txt" -Encoding utf8
aws s3 cp "$env:CLAUDE_JOB_DIR\tmp\probe.txt" s3://bayanihan-staging-files/uploads/probe.txt --region ap-southeast-1
aws s3 ls s3://bayanihan-staging-files/uploads/ --region ap-southeast-1
aws s3 rm s3://bayanihan-staging-files/uploads/probe.txt --region ap-southeast-1
aws s3 ls s3://another-bucket-name-that-does-not-belong --region ap-southeast-1
```

Expected: upload, list, and delete all succeed; the final command fails with `AccessDenied`, proving the policy is scoped.

- [ ] **Step 7: Clear the temporary credentials from the shell**

```powershell
Remove-Item Env:\AWS_ACCESS_KEY_ID, Env:\AWS_SECRET_ACCESS_KEY
aws sts get-caller-identity
```

Expected: back to the account identity, not the app user. Leaving these set would make later steps run as the app user and fail confusingly.

---

### Task 3: Create RDS PostgreSQL 17.10

**Files:** none in the repository.

**Interfaces:**
- Produces: RDS endpoint hostname, master username, master password — consumed by Task 6 and Task 7 as `DB_HOST` / `DB_USERNAME` / `DB_PASSWORD`.

- [ ] **Step 1: Check RDS Free Tier eligibility (spec V5)**

```powershell
aws freetier get-free-tier-usage --region us-east-1 --query "freeTierUsages[?contains(service,'Relational')]" --output table
```

Expected: either usage rows for RDS (account is inside its first 12 months) or an empty result. This is informational only — it changes cost by roughly $13/month, not the design. If the API is unavailable, check the Billing console's Free Tier page instead.

- [ ] **Step 2: Create a security group for the database**

```powershell
aws ec2 create-security-group --group-name bayanihan-staging-db-sg --description "Postgres access for Lightsail container service (staging)" --vpc-id vpc-0b4021ede69666339 --region ap-southeast-1
```

Expected: JSON with `GroupId`. Record it as `<db-sg-id>`.

- [ ] **Step 3: Allow PostgreSQL ingress**

```powershell
aws ec2 authorize-security-group-ingress --group-id <db-sg-id> --protocol tcp --port 5432 --cidr 0.0.0.0/0 --region ap-southeast-1
```

Expected: `"Return": true`.

**This deliberately opens 5432 to the internet.** Lightsail container services cannot join your VPC and their egress IPs are neither static nor published, so no narrower source is possible. The compensating controls are forced TLS (Step 4), a 32+ character password (Step 5), and the no-real-data rule. The spec records this as **blocking for production**, resolved by moving compute to ECS Fargate. Do not carry this security group into production.

- [ ] **Step 4: Create a parameter group that forces TLS**

```powershell
aws rds create-db-parameter-group --db-parameter-group-name bayanihan-staging-pg17 --db-parameter-group-family postgres17 --description "One Window Bayanihan staging - force SSL" --region ap-southeast-1
aws rds modify-db-parameter-group --db-parameter-group-name bayanihan-staging-pg17 --parameters "ParameterName=rds.force_ssl,ParameterValue=1,ApplyMethod=pending-reboot" --region ap-southeast-1
```

Expected: both return JSON without error.

`rds.force_ssl=1` makes the database reject unencrypted connections outright, so a misconfigured `DB_SSLMODE` fails loudly instead of silently sending personal data in clear text.

- [ ] **Step 5: Generate a strong master password**

```powershell
$pw = -join ((48..57) + (65..90) + (97..122) | Get-Random -Count 40 | ForEach-Object { [char]$_ })
Write-Output $pw
```

Expected: a 40-character alphanumeric string. **Record it in a password manager now.** Alphanumeric only — RDS rejects `/`, `@`, `"`, and spaces, and it avoids shell-quoting problems.

- [ ] **Step 6: Create the database instance**

```powershell
aws rds create-db-instance `
  --db-instance-identifier bayanihan-staging-db `
  --db-instance-class db.t4g.micro `
  --engine postgres `
  --engine-version 17.10 `
  --master-username bayanihan_admin `
  --master-user-password "<password from Step 5>" `
  --allocated-storage 20 `
  --storage-type gp3 `
  --storage-encrypted `
  --db-name one_window `
  --vpc-security-group-ids <db-sg-id> `
  --db-parameter-group-name bayanihan-staging-pg17 `
  --backup-retention-period 7 `
  --publicly-accessible `
  --no-multi-az `
  --no-auto-minor-version-upgrade `
  --copy-tags-to-snapshot `
  --region ap-southeast-1
```

Expected: JSON with `"DBInstanceStatus": "creating"`.

`--storage-encrypted` satisfies encryption at rest. `--backup-retention-period 7` enables automated backups and point-in-time recovery (capability C9).

- [ ] **Step 7: Wait for the instance to become available**

```powershell
aws rds wait db-instance-available --db-instance-identifier bayanihan-staging-db --region ap-southeast-1
aws rds describe-db-instances --db-instance-identifier bayanihan-staging-db --region ap-southeast-1 --query "DBInstances[0].[DBInstanceStatus,Endpoint.Address,EngineVersion,StorageEncrypted]" --output text
```

Expected after 5–15 minutes: `available   bayanihan-staging-db.<id>.ap-southeast-1.rds.amazonaws.com   17.10   True`. Record the endpoint as `<rds-endpoint>`. Report a status update every 15 minutes while waiting.

- [ ] **Step 8: Reboot to apply the pending `rds.force_ssl` parameter**

```powershell
aws rds reboot-db-instance --db-instance-identifier bayanihan-staging-db --region ap-southeast-1
aws rds wait db-instance-available --db-instance-identifier bayanihan-staging-db --region ap-southeast-1
```

`ApplyMethod=pending-reboot` means TLS is not actually enforced until this reboot completes.

- [ ] **Step 9: Verify connectivity, version, and create the required extensions (spec V2)**

Confirm local PHP has the PostgreSQL driver first:

```powershell
php -m | Select-String -Pattern "pdo_pgsql"
```

Expected: `pdo_pgsql`. If missing, run this step from a one-off Lightsail deployment instead (see Task 6 Step 5 fallback).

```powershell
$env:PGPASSWORD='<password from Step 5>'
psql "host=<rds-endpoint> port=5432 dbname=one_window user=bayanihan_admin sslmode=require" -c "SELECT version();" -c "CREATE EXTENSION IF NOT EXISTS pgcrypto; CREATE EXTENSION IF NOT EXISTS pg_trgm;" -c "SELECT extname FROM pg_extension ORDER BY extname;"
```

Expected: `PostgreSQL 17.10`, then a list including `pg_trgm`, `pgcrypto`, `plpgsql`.

If `psql` is not installed, use PHP instead:

```powershell
php -r '$d = new PDO("pgsql:host=<rds-endpoint>;port=5432;dbname=one_window;sslmode=require", "bayanihan_admin", getenv("PGPASSWORD")); echo $d->query("SELECT version()")->fetchColumn(), PHP_EOL; $d->exec("CREATE EXTENSION IF NOT EXISTS pgcrypto"); $d->exec("CREATE EXTENSION IF NOT EXISTS pg_trgm"); foreach ($d->query("SELECT extname FROM pg_extension") as $r) { echo $r["extname"], PHP_EOL; }'
```

- [ ] **Step 10: Prove that unencrypted connections are rejected**

```powershell
$env:PGPASSWORD='<rds master password>'
php -r 'try { new PDO("pgsql:host=<rds-endpoint>;port=5432;dbname=one_window;sslmode=disable", "bayanihan_admin", getenv("PGPASSWORD")); echo "FAIL: plaintext connection accepted\n"; } catch (Exception $e) { echo "PASS: plaintext rejected — ", $e->getMessage(), "\n"; }'
Remove-Item Env:\PGPASSWORD
```

`PGPASSWORD` is set again here because every PowerShell tool invocation starts a fresh shell — environment variables do not persist between steps.

Expected: `PASS: plaintext rejected`. If it prints `FAIL`, `rds.force_ssl` did not apply — re-check Steps 4 and 8 before continuing.

---

### Task 4: Create the container service and verify SMTP egress

**Files:**
- Create: `$JOB_TMP/ecr-policy.json`, `$JOB_TMP/probe-deployment.json`

**Interfaces:**
- Consumes: ECR image URI from Task 1.
- Produces: container service `bayanihan-staging`, its public endpoint URL, and a verified answer to spec item **V1** (is outbound TCP 587 permitted).

- [ ] **Step 1: Create the container service**

```powershell
aws lightsail create-container-service --service-name bayanihan-staging --power small --scale 1 --region ap-southeast-1
```

Expected: JSON with `"state": "PENDING"` and a `url` field.

- [ ] **Step 2: Wait until the service is ready**

```powershell
aws lightsail get-container-services --service-name bayanihan-staging --region ap-southeast-1 --query "containerServices[0].[state,url,power,scale]" --output text
```

Repeat until state is `READY` (2–5 minutes). Expected: `READY   https://bayanihan-staging.<guid>.ap-southeast-1.cs.amazonlightsail.com/   small   1`.

Record the URL as `<endpoint-url>` and confirm `scale` is `1`.

- [ ] **Step 3: Activate the ECR image puller role**

```powershell
aws lightsail update-container-service --service-name bayanihan-staging --private-registry-access "ecrImagePullerRole={isActive=true}" --region ap-southeast-1
Start-Sleep -Seconds 45
aws lightsail get-container-services --service-name bayanihan-staging --region ap-southeast-1 --query "containerServices[0].privateRegistryAccess.ecrImagePullerRole" --output json
```

Expected: `{"isActive": true, "principalArn": "arn:aws:iam::677206905439:role/..."}`. Record the ARN as `<puller-role-arn>`. The role ARN does not exist until at least 30 seconds after activation.

- [ ] **Step 4: Grant the puller role access to the ECR repository**

Write `$JOB_TMP/ecr-policy.json`:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "AllowLightsailPullBayanihanStaging",
      "Effect": "Allow",
      "Principal": {
        "AWS": "<puller-role-arn>"
      },
      "Action": [
        "ecr:BatchGetImage",
        "ecr:GetDownloadUrlForLayer"
      ]
    }
  ]
}
```

```powershell
aws ecr set-repository-policy --repository-name bayanihan --policy-text file://C:\Users\JKsars\.claude\jobs\30a82c39\tmp\ecr-policy.json --region ap-southeast-1
aws ecr get-repository-policy --repository-name bayanihan --region ap-southeast-1
```

Expected: the policy is returned and contains the puller role ARN.

- [ ] **Step 5: Deploy an SMTP egress probe**

Write `$JOB_TMP/probe-deployment.json`:

```json
{
  "serviceName": "bayanihan-staging",
  "containers": {
    "probe": {
      "image": "public.ecr.aws/docker/library/alpine:3.20",
      "command": [
        "sh",
        "-c",
        "for p in 587 465 25; do if nc -z -w 8 smtp.gmail.com $p; then echo \"PROBE_RESULT port $p OPEN\"; else echo \"PROBE_RESULT port $p BLOCKED\"; fi; done; echo PROBE_DONE; sleep 3600"
      ],
      "environment": {},
      "ports": {}
    }
  }
}
```

```powershell
aws lightsail create-container-service-deployment --cli-input-json file://C:\Users\JKsars\.claude\jobs\30a82c39\tmp\probe-deployment.json --region ap-southeast-1
```

Expected: JSON with the deployment in `ACTIVATING`.

No `publicEndpoint` is specified, so no health check applies and the probe cannot fail the deployment. `sleep 3600` keeps the container alive so it does not crash-loop while we read its logs.

- [ ] **Step 6: Read the probe result**

```powershell
Start-Sleep -Seconds 90
aws lightsail get-container-log --service-name bayanihan-staging --container-name probe --region ap-southeast-1 --query "logEvents[].message" --output text
```

Expected: three `PROBE_RESULT` lines then `PROBE_DONE`.

**Decision gate:**
- `port 587 OPEN` → continue to Task 5 with Gmail SMTP as planned.
- `port 587 BLOCKED` → **stop.** Gmail SMTP is impossible and login is email-gated. Switch to Amazon SES: verify a single email address as a sending identity (works without a domain), create SES SMTP credentials, and use `MAIL_HOST=email-smtp.ap-southeast-1.amazonaws.com`, `MAIL_PORT=587`. Note SES sandbox restricts recipients to verified addresses, so every test mailbox must be verified first. Record the change in the spec before continuing.

Port 25 is expected to be blocked by AWS regardless; that result is informational.

---

### Task 5: Prepare application secrets

**Files:** none in the repository.

**Interfaces:**
- Produces: `APP_KEY`, Gmail App Password, Sentry DSN, OpenRouter API key — all consumed by Task 6 and Task 7.

- [ ] **Step 1: Generate `APP_KEY`**

```powershell
php -r "echo 'base64:' . base64_encode(random_bytes(32)), PHP_EOL;"
```

Expected: `base64:` followed by 44 characters. **Record this in a password manager immediately.**

This key encrypts `EncryptedString` and `EncryptedDate` columns and signs sessions. If it is lost or changed, all encrypted case data becomes permanently unreadable. It must be identical for Task 6's migration run and Task 7's deployment.

- [ ] **Step 2: Create a Gmail App Password**

Manual, in a browser:
1. Enable 2-Step Verification at `https://myaccount.google.com/signinoptions/two-step-verification`
2. Create an App Password at `https://myaccount.google.com/apppasswords`, naming it "Bayanihan Staging"
3. Record the 16-character password (shown without spaces when copied)

The Google account password will not authenticate SMTP; only an App Password will. Record which Gmail address this is — it becomes both `MAIL_USERNAME` and `MAIL_FROM_ADDRESS`.

- [ ] **Step 3: Obtain a Sentry DSN**

Create a free project at `https://sentry.io` (platform: Laravel) and record the DSN, which looks like `https://<key>@o<org>.ingest.sentry.io/<project>`.

- [ ] **Step 4: Obtain an OpenRouter API key**

Create a key at `https://openrouter.ai/keys`. The configured model `openai/gpt-oss-120b:free` needs no credit balance.

- [ ] **Step 5: Confirm every secret is recorded**

Checklist — all must be in a password manager before continuing:
`APP_KEY`, RDS master password, `bayanihan-staging-app` access key and secret, `bayanihan-ci-ecr` access key and secret, Gmail address and App Password, Sentry DSN, OpenRouter key.

---

### Task 6: Apply migrations and seed reference data

**Files:**
- Create: `$JOB_TMP/.env.staging` (outside the repository — contains secrets)

**Interfaces:**
- Consumes: RDS endpoint and password (Task 3), `APP_KEY` (Task 5).
- Produces: a migrated and seeded database schema.

- [ ] **Step 1: Confirm the schema is empty**

```powershell
$env:PGPASSWORD='<rds master password>'
php -r '$d = new PDO("pgsql:host=<rds-endpoint>;port=5432;dbname=one_window;sslmode=require", "bayanihan_admin", getenv("PGPASSWORD")); echo $d->query("SELECT count(*) FROM information_schema.tables WHERE table_schema = ''public''")->fetchColumn(), PHP_EOL;'
```

Expected: `0`, or a small number if only extensions were created. A non-trivial count means the database is not fresh — investigate before migrating.

Note the doubled single quotes: inside a PowerShell single-quoted string, `''` produces one literal `'`, so PHP receives `'public'`.

- [ ] **Step 2: Write a staging env file outside the repository**

Create `$JOB_TMP/.env.staging` with the full contents below, substituting recorded values:

```env
APP_NAME="One Window Bayanihan"
APP_ENV=staging
APP_DEBUG=false
APP_KEY=<APP_KEY from Task 5>
APP_URL=<endpoint-url without trailing slash>

DB_CONNECTION=pgsql
DB_HOST=<rds-endpoint>
DB_PORT=5432
DB_DATABASE=one_window
DB_USERNAME=bayanihan_admin
DB_PASSWORD=<rds master password>
DB_SSLMODE=require

CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_ENCRYPT=true

FILESYSTEM_DISK=object-storage
STORAGE_DRIVER=s3
STORAGE_ACCESS_KEY=<app AccessKeyId>
STORAGE_SECRET_KEY=<app SecretAccessKey>
STORAGE_REGION=ap-southeast-1
STORAGE_BUCKET=bayanihan-staging-files
STORAGE_ENDPOINT=https://s3.ap-southeast-1.amazonaws.com
STORAGE_ROOT=uploads

AUDIT_ARCHIVE_DISK=audit-archives
AUDIT_ARCHIVE_DRIVER=s3
AUDIT_ARCHIVE_ROOT=audit-archives
AUDIT_RETENTION_DAYS=365

MAIL_MAILER=log
MAIL_FROM_ADDRESS=<gmail address>
MAIL_FROM_NAME="One Window Bayanihan"

BROADCAST_CONNECTION=log
MALWARE_SCANNER=null
AI_CHATBOT_ENABLED=false
TURNSTILE_ENABLED=false
```

`MAIL_MAILER=log` and the two feature flags are off deliberately: seeding must not send real mail or rebuild the chatbot index. Task 7 sets the real values for the running service.

- [ ] **Step 3: Run migrations and read the exit code**

```powershell
Copy-Item .env .env.local.backup -ErrorAction SilentlyContinue
Copy-Item "$env:CLAUDE_JOB_DIR\tmp\.env.staging" .env -Force
php artisan config:clear
php artisan migrate --force --no-interaction
Write-Output "EXITCODE=$LASTEXITCODE"
```

Expected: migration table output ending in `DONE`, and `EXITCODE=0`.

**If `EXITCODE` is not 0, stop.** Do not deploy the application against a partially migrated schema. This is the failure the entrypoint's `|| true` would have hidden.

- [ ] **Step 4: Verify no migrations are pending**

```powershell
php artisan migrate:status | Select-String -Pattern "Pending"
```

Expected: no output. Every migration, including `2026_07_17_000001_create_case_category_pivot_table`, shows `Ran`.

- [ ] **Step 5: Seed reference data**

```powershell
php artisan db:seed --force --no-interaction
Write-Output "EXITCODE=$LASTEXITCODE"
```

Expected: seeder output and `EXITCODE=0`.

**Fallback if local PHP cannot reach RDS or lacks `pdo_pgsql`:** run migrations as a one-off Lightsail deployment instead. Use the Task 7 deployment JSON but replace the container `command` with `["php","artisan","migrate","--force"]`, omit `publicEndpoint`, deploy, then read `aws lightsail get-container-log` to confirm success. Redeploy the real application afterwards.

- [ ] **Step 6: Verify the schema and restore your local env**

```powershell
$env:PGPASSWORD='<rds master password>'
php -r '$d = new PDO("pgsql:host=<rds-endpoint>;port=5432;dbname=one_window;sslmode=require", "bayanihan_admin", getenv("PGPASSWORD")); foreach (["users","cases","jobs","sessions","cache","audit_logs","case_category"] as $t) { $n = $d->query("SELECT count(*) FROM information_schema.tables WHERE table_name = " . $d->quote($t))->fetchColumn(); echo $t, ": ", ($n ? "present" : "MISSING"), PHP_EOL; }'
Remove-Item Env:\PGPASSWORD
Remove-Item .env -Force
Copy-Item .env.local.backup .env -ErrorAction SilentlyContinue
php artisan config:clear
git status --short
```

Expected: all seven tables `present`; `git status` shows no modified tracked files and no `.env` committed. `jobs`, `sessions`, and `cache` must exist — they are what the database queue, session, and cache drivers depend on.

---

### Task 7: Deploy the application

**Files:**
- Create: `$JOB_TMP/app-deployment.json` (outside the repository — contains secrets)

**Interfaces:**
- Consumes: image URI (Task 1), S3 credentials (Task 2), RDS endpoint and password (Task 3), container service (Task 4), all secrets (Task 5), migrated schema (Task 6).
- Produces: a running application at `<endpoint-url>`.

- [ ] **Step 1: Write the deployment definition**

Write `$JOB_TMP/app-deployment.json`, substituting all recorded values:

```json
{
  "serviceName": "bayanihan-staging",
  "containers": {
    "app": {
      "image": "677206905439.dkr.ecr.ap-southeast-1.amazonaws.com/bayanihan:<sha>",
      "environment": {
        "APP_NAME": "One Window Bayanihan",
        "APP_ENV": "staging",
        "APP_DEBUG": "false",
        "APP_KEY": "<APP_KEY>",
        "APP_URL": "<endpoint-url without trailing slash>",
        "LOG_CHANNEL": "stderr",
        "LOG_LEVEL": "info",
        "RUN_MIGRATIONS": "false",
        "DB_CONNECTION": "pgsql",
        "DB_HOST": "<rds-endpoint>",
        "DB_PORT": "5432",
        "DB_DATABASE": "one_window",
        "DB_USERNAME": "bayanihan_admin",
        "DB_PASSWORD": "<rds master password>",
        "DB_SSLMODE": "require",
        "CACHE_STORE": "database",
        "QUEUE_CONNECTION": "database",
        "SESSION_DRIVER": "database",
        "SESSION_ENCRYPT": "true",
        "SESSION_LIFETIME": "120",
        "FILESYSTEM_DISK": "object-storage",
        "STORAGE_DRIVER": "s3",
        "STORAGE_ACCESS_KEY": "<app AccessKeyId>",
        "STORAGE_SECRET_KEY": "<app SecretAccessKey>",
        "STORAGE_REGION": "ap-southeast-1",
        "STORAGE_BUCKET": "bayanihan-staging-files",
        "STORAGE_ENDPOINT": "https://s3.ap-southeast-1.amazonaws.com",
        "STORAGE_ROOT": "uploads",
        "AUDIT_ARCHIVE_DISK": "audit-archives",
        "AUDIT_ARCHIVE_DRIVER": "s3",
        "AUDIT_ARCHIVE_ROOT": "audit-archives",
        "AUDIT_RETENTION_DAYS": "365",
        "AUDIT_EXPORT_MAX_ROWS": "100000",
        "MAIL_MAILER": "smtp",
        "MAIL_HOST": "smtp.gmail.com",
        "MAIL_PORT": "587",
        "MAIL_USERNAME": "<gmail address>",
        "MAIL_PASSWORD": "<16-char app password>",
        "MAIL_FROM_ADDRESS": "<gmail address>",
        "MAIL_FROM_NAME": "One Window Bayanihan",
        "CONTACT_RECIPIENT_EMAIL": "<gmail address>",
        "TRUSTED_PROXIES": "*",
        "MFA_LOGIN_CHALLENGE_ENABLED": "true",
        "MFA_ENROLLMENT_ENFORCEMENT_ENABLED": "true",
        "AI_CHATBOT_ENABLED": "true",
        "AI_CHATBOT_PROVIDER": "openrouter",
        "AI_CHATBOT_MODEL": "openai/gpt-oss-120b:free",
        "OPENROUTER_API_KEY": "<openrouter key>",
        "APP_ASSISTANT_NAME": "Bayani",
        "TURNSTILE_ENABLED": "true",
        "TURNSTILE_SITE_KEY": "1x00000000000000000000AA",
        "TURNSTILE_SECRET_KEY": "1x0000000000000000000000000000000AA",
        "SENTRY_LARAVEL_DSN": "<sentry dsn>",
        "SENTRY_LARAVEL_TRACES_SAMPLE_RATE": "0.2",
        "BROADCAST_CONNECTION": "log",
        "MALWARE_SCANNER": "null",
        "QUEUE_FAILED_ALERT_DRIVER": "log"
      },
      "ports": {
        "8080": "HTTP"
      }
    }
  },
  "publicEndpoint": {
    "containerName": "app",
    "containerPort": 8080,
    "healthCheck": {
      "path": "/up",
      "intervalSeconds": 30,
      "timeoutSeconds": 5,
      "healthyThreshold": 2,
      "unhealthyThreshold": 5,
      "successCodes": "200-299"
    }
  }
}
```

`TRUSTED_PROXIES=*` is required because Lightsail's ingress IP range is neither static nor published; without it, client IPs, rate limiters, and admin IP allowlists all misread the proxy as the client. `LOG_CHANNEL=stderr` sends logs to the Lightsail log collector rather than an ephemeral file.

- [ ] **Step 2: Verify the file contains no unsubstituted placeholders**

```powershell
Select-String -Path "$env:CLAUDE_JOB_DIR\tmp\app-deployment.json" -Pattern "<[a-z]" -AllMatches
```

Expected: no matches. Any match means a placeholder was left in and the deployment will misconfigure.

- [ ] **Step 3: Deploy**

```powershell
aws lightsail create-container-service-deployment --cli-input-json file://C:\Users\JKsars\.claude\jobs\30a82c39\tmp\app-deployment.json --region ap-southeast-1
```

Expected: JSON showing the new deployment `version` in state `ACTIVATING`.

- [ ] **Step 4: Watch the deployment reach ACTIVE**

```powershell
aws lightsail get-container-services --service-name bayanihan-staging --region ap-southeast-1 --query "containerServices[0].[state,currentDeployment.state,currentDeployment.version]" --output text
```

Repeat until `RUNNING   ACTIVE   <version>` (3–8 minutes). Report a status update every 15 minutes while waiting.

If it reaches `FAILED`, read the logs before changing anything:

```powershell
aws lightsail get-container-log --service-name bayanihan-staging --container-name app --region ap-southeast-1 --query "logEvents[].message" --output text
```

The two most likely causes are a database connection failure (check the security group and `DB_SSLMODE`) and a health check timeout (the entrypoint runs four `artisan` cache commands before supervisord starts, which can exceed the initial health check window).

- [ ] **Step 5: Health-gate the endpoint**

```powershell
$r = Invoke-WebRequest -Uri "<endpoint-url>/up" -UseBasicParsing -TimeoutSec 30
Write-Output "HTTP $($r.StatusCode)"
```

Expected: `HTTP 200`.

- [ ] **Step 6: Confirm the queue worker and scheduler are running**

```powershell
aws lightsail get-container-log --service-name bayanihan-staging --container-name app --region ap-southeast-1 --query "logEvents[].message" --output text | Select-String -Pattern "supervisord|queue-worker|scheduler|ENTRYPOINT"
```

Expected: entrypoint output plus supervisord lines showing `php-fpm`, `nginx`, `queue-worker`, and `scheduler` entering RUNNING.

---

### Task 8: Run the smoke test

**Files:** none.

**Interfaces:**
- Consumes: the running deployment from Task 7.
- Produces: pass/fail evidence for all twelve verification items in the spec's §10.

- [ ] **Step 1: Verify the public endpoint from outside AWS**

```powershell
$r = Invoke-WebRequest -Uri "<endpoint-url>/up" -UseBasicParsing
Write-Output "HTTP $($r.StatusCode)"
$c = Invoke-WebRequest -Uri "<endpoint-url>" -UseBasicParsing
Write-Output "Landing page HTTP $($c.StatusCode), length $($c.Content.Length)"
```

Expected: both `200`, landing page non-trivial in length.

- [ ] **Step 2: Verify TLS is valid**

```powershell
$req = [Net.HttpWebRequest]::Create("<endpoint-url>/up")
$req.GetResponse().Close()
Write-Output "TLS chain validated"
```

Expected: no certificate error. .NET validates the chain by default, so an untrusted certificate throws here.

- [ ] **Step 3: Log in and receive the OTP by email (items 2 and 8)**

Manual, in a browser at `<endpoint-url>`:
1. Log in with a seeded account (see `database/seeders/` for credentials)
2. Confirm the OTP email arrives in the Gmail inbox
3. Complete MFA enrolment

**This is the single most important check.** It proves SMTP egress, mail configuration, database sessions, and the queue all work together. If the OTP does not arrive, check the Gmail account's "Recent security activity", then the container log for SMTP errors, then `email_logs`.

- [ ] **Step 4: Verify `email_logs` recorded the send**

```powershell
$env:PGPASSWORD='<rds master password>'
php -r '$d = new PDO("pgsql:host=<rds-endpoint>;port=5432;dbname=one_window;sslmode=require", "bayanihan_admin", getenv("PGPASSWORD")); foreach ($d->query("SELECT status, count(*) FROM email_logs GROUP BY status") as $r) { echo $r["status"], ": ", $r["count"], PHP_EOL; }'
```

Expected: at least one row, status indicating success.

- [ ] **Step 5: Create a case with an upload and confirm the object reaches S3 (item 3)**

In the browser, create a case with a file attachment. Then:

```powershell
aws s3 ls s3://bayanihan-staging-files/uploads/ --recursive --region ap-southeast-1
```

Expected: at least one object under the `uploads/` prefix. If the upload succeeded in the UI but nothing appears here, `STORAGE_*` is misconfigured — check `STORAGE_ENDPOINT` and `STORAGE_ROOT`.

- [ ] **Step 6: Create a referral and confirm the queue drains (item 4)**

Create a referral in the browser, then:

```powershell
$env:PGPASSWORD='<rds master password>'
php -r '$d = new PDO("pgsql:host=<rds-endpoint>;port=5432;dbname=one_window;sslmode=require", "bayanihan_admin", getenv("PGPASSWORD")); echo "pending jobs: ", $d->query("SELECT count(*) FROM jobs")->fetchColumn(), PHP_EOL; echo "failed jobs: ", $d->query("SELECT count(*) FROM failed_jobs")->fetchColumn(), PHP_EOL;'
Remove-Item Env:\PGPASSWORD
```

Expected: `pending jobs` returns to `0` within a minute and `failed jobs` stays `0`. A permanently non-zero pending count means the queue worker is not running.

- [ ] **Step 7: Export a report (item 5)**

In the browser, run a report export. Expected: the file downloads or is emailed without error. This exercises the PostgreSQL-specific reporting functions (`to_char`, `EXTRACT`, `age`) that would fail on a non-PostgreSQL database.

- [ ] **Step 8: Verify the audit chain and archive to S3 (items 6 and 7)**

Deploy a one-off command by copying `$JOB_TMP/app-deployment.json` to `$JOB_TMP/audit-deployment.json`, removing the `publicEndpoint` block, and setting `"command": ["sh","-c","php artisan audit:verify && php artisan audit:archive && echo AUDIT_TASK_DONE && sleep 600"]`. Then:

```powershell
aws lightsail create-container-service-deployment --cli-input-json file://C:\Users\JKsars\.claude\jobs\30a82c39\tmp\audit-deployment.json --region ap-southeast-1
Start-Sleep -Seconds 180
aws lightsail get-container-log --service-name bayanihan-staging --container-name app --region ap-southeast-1 --query "logEvents[].message" --output text | Select-String -Pattern "AUDIT_TASK_DONE|verif|archiv|error"
aws s3 ls s3://bayanihan-staging-files/audit-archives/ --recursive --region ap-southeast-1
```

Expected: `AUDIT_TASK_DONE` in the logs and at least one object under `audit-archives/`. The `audit-archives` disk sets `'throw' => true`, so a write failure surfaces as an exception rather than silence.

**Then redeploy the real application** using `$JOB_TMP/app-deployment.json` (Task 7 Step 3) and re-run Task 7 Step 5.

- [ ] **Step 9: Verify the scheduler is ticking (item 9)**

```powershell
aws lightsail get-container-log --service-name bayanihan-staging --container-name app --region ap-southeast-1 --query "logEvents[].message" --output text | Select-String -Pattern "schedule"
```

Expected: recurring scheduler activity. `schedule:work` logs each run it dispatches.

- [ ] **Step 10: Verify the chatbot, Turnstile, and Sentry (items 10, 11, 12)**

Manual, in the browser:
1. Ask the chatbot a question answerable from helpdesk content — expect a grounded answer, not the `AI_FALLBACK_MESSAGE`
2. Confirm a Turnstile widget renders on the login page (the test keys auto-pass)
3. Visit `<endpoint-url>/a-path-that-does-not-exist` to confirm the custom 404 renders, then confirm events appear in the Sentry issues feed

If the chatbot returns only the fallback message, check the container log for `chatbot:index` errors at boot — that indicates the FTS5 index failed to build.

- [ ] **Step 11: Record the results**

Write a pass/fail line for each of the twelve items. Any failure blocks Task 9.

---

### Task 9: Document the deployment and record follow-ups

**Files:**
- Create: `docs/DEPLOYMENT_STAGING_AWS_v1.0.0.md`
- Modify: `docs/superpowers/specs/2026-07-27-aws-staging-deployment-design-v1.0.0.md` (only if Task 4's decision gate changed the mail transport)

- [ ] **Step 1: Write the runbook**

Create `docs/DEPLOYMENT_STAGING_AWS_v1.0.0.md` containing:

- **Resource inventory:** container service name, endpoint URL, ECR repository URI, RDS instance identifier and endpoint, S3 bucket, both IAM user names, security group ID, parameter group name. **No secrets** — reference the password manager instead.
- **Redeploy procedure:** push to `main` (or run `build-image.yml`) → note the new SHA → run migrations if the release contains any → update the image tag in the deployment JSON → `create-container-service-deployment` → health-gate `/up`.
- **Rollback procedure:** `aws lightsail get-container-service-deployments --service-name bayanihan-staging` lists the retained versions; redeploy a previous version's image tag. Lightsail keeps the last 50.
- **Operational commands:** `get-container-log`, `queue:failed`, `audit:verify`, and the one-off deployment pattern used in Task 8 Step 8.
- **Known constraints:** `scale` must stay at 1 until the scheduler is extracted; `RUN_MIGRATIONS` must stay `false`; `APP_KEY` must never change.
- **A changelog table** with version 1.0.0 dated 2026-07-27.

- [ ] **Step 2: Record the standards follow-up register**

Include a section listing every ⚠️ item from the spec's §11 with an owner and a target, specifically:

1. RDS reachable from `0.0.0.0/0` — resolved by the ECS Fargate migration (blocking for production)
2. No SPF/DKIM/DMARC — requires a domain (blocking for production)
3. Upload malware scanning absent (`MALWARE_SCANNER=null`)
4. Root account still in use; MFA on root and IAM Identity Center pending
5. No restore drill performed yet — schedule one using `scripts/restore-test.sh`
6. No alert thresholds or on-call routing defined
7. `TRUSTED_PROXIES=*` broader than policy
8. Supplier register entries needed for AWS, Google, Sentry, OpenRouter, Cloudflare — note that the chatbot transmits helpdesk queries to OpenRouter
9. **Lightsail deployment environment variables are not a secret store** — they are readable by anyone with console or `get-container-services` access and are not KMS-encrypted. Move to AWS Secrets Manager when compute moves to Fargate.

Item 9 was identified during planning and is not in the spec's §11; add it there too.

- [ ] **Step 3: Create the scoped deployment IAM user (spec §5)**

Everything up to here ran as the account root. This creates the user that ongoing deployments use instead.

Write `$JOB_TMP/deploy-policy.json`:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "ManageBayanihanStagingContainerService",
      "Effect": "Allow",
      "Action": [
        "lightsail:GetContainerServices",
        "lightsail:GetContainerServiceDeployments",
        "lightsail:CreateContainerServiceDeployment",
        "lightsail:GetContainerLog",
        "lightsail:GetContainerServiceMetricData",
        "lightsail:UpdateContainerService"
      ],
      "Resource": "*"
    },
    {
      "Sid": "ReadBayanihanImages",
      "Effect": "Allow",
      "Action": [
        "ecr:DescribeImages",
        "ecr:ListImages",
        "ecr:BatchGetImage",
        "ecr:GetAuthorizationToken"
      ],
      "Resource": "*"
    }
  ]
}
```

```powershell
$env:Path = [Environment]::GetEnvironmentVariable('Path','Machine') + ';' + [Environment]::GetEnvironmentVariable('Path','User')
$env:PYTHONIOENCODING='utf-8'
aws iam create-user --user-name bayanihan-deploy
aws iam put-user-policy --user-name bayanihan-deploy --policy-name lightsail-deploy-staging --policy-document file://C:\Users\JKsars\.claude\jobs\30a82c39\tmp\deploy-policy.json
aws iam create-access-key --user-name bayanihan-deploy
```

Expected: `AccessKeyId` and `SecretAccessKey`. **Record both.**

Lightsail IAM actions do not support resource-level permissions, so `Resource` must be `*`. This user still cannot touch RDS, S3 data, or IAM, which is the meaningful reduction from root.

- [ ] **Step 4: Verify the deploy user can deploy without root**

```powershell
$env:Path = [Environment]::GetEnvironmentVariable('Path','Machine') + ';' + [Environment]::GetEnvironmentVariable('Path','User')
$env:PYTHONIOENCODING='utf-8'
$env:AWS_ACCESS_KEY_ID='<deploy AccessKeyId>'
$env:AWS_SECRET_ACCESS_KEY='<deploy SecretAccessKey>'
aws sts get-caller-identity --query "Arn" --output text
aws lightsail get-container-services --service-name bayanihan-staging --region ap-southeast-1 --query "containerServices[0].state" --output text
aws rds describe-db-instances --region ap-southeast-1
Remove-Item Env:\AWS_ACCESS_KEY_ID, Env:\AWS_SECRET_ACCESS_KEY
```

Expected: the ARN ends in `user/bayanihan-deploy`; the Lightsail query returns `RUNNING`; the RDS command fails with `AccessDenied`, proving the scope holds.

- [ ] **Step 5: Commit**

```powershell
git add docs/DEPLOYMENT_STAGING_AWS_v1.0.0.md docs/superpowers/specs/2026-07-27-aws-staging-deployment-design-v1.0.0.md
git commit -m "docs: add AWS staging deployment runbook and standards follow-up register"
git push
```

- [ ] **Step 6: Open a pull request**

```powershell
gh pr create --base main --head deploy/aws-staging --title "AWS staging deployment: CI image build, runbook, and design spec" --body "Implements docs/superpowers/plans/2026-07-27-aws-staging-deployment-v1.0.0.md. Adds the ECR image build workflow and the staging runbook. Live environment: <endpoint-url>. All twelve smoke-test items pass. Nine standards follow-ups recorded; two are blocking for production (public database, no SPF/DKIM/DMARC).

🤖 Generated with [Claude Code](https://claude.com/claude-code)"
```

- [ ] **Step 7: Note the inert Render workflows**

The existing `deploy-staging.yml` and `deploy-production.yml` still target Render and are gated on `vars.RENDER_STAGING_SERVICE_ID != ''`, so they skip harmlessly. Rewiring them to Lightsail is **explicitly out of scope** for this plan; record it as a follow-up. Do not delete them in this change.

---

## Out of scope

- Rewiring the GitHub Actions deploy workflows from Render to Lightsail (deployment is manual for staging)
- The ECS Fargate production migration (spec §14)
- Domain registration, SES, SPF/DKIM/DMARC
- Malware scanning for uploads
- Load testing (Gmail's ~500/day limit would be the first thing to break)
- Horizontal scaling and scheduler extraction
