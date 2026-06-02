# Supabase Setup Guide

## 1. Prerequisites

Before you start, make sure you have these ready:

- PHP 8.2+ with the `pgsql` extension enabled
- Composer dependencies installed (`composer install`)
- Node.js 20+ with npm dependencies installed (`npm install`)
- A Supabase account (free tier works for development)

You can check your PHP extensions with `php -m | findstr pgsql` on Windows or `php -m | grep pgsql` on Linux/Mac.

## 2. Creating a Supabase Project

1. Go to [supabase.com](https://supabase.com) and sign in.
2. Click **New project**.
3. Fill in the project details:
   - **Name**: `one-window-bayanihan`
   - **Database Password**: Generate a strong password and save it somewhere secure (password manager recommended).
   - **Region**: Choose the closest region to your users. For PH-based deployments, Singapore is a good choice.
4. Click **Create new project**.
5. Wait for the project to finish provisioning (usually takes 1-2 minutes).

Once the project is ready, you will land on the project dashboard.

## 3. Getting Connection Details

### Database Connection

1. Go to **Project Settings** > **Database** > **Connection string**.
2. You have two options for connecting:

#### Option A: Direct Connection (Recommended for RLS)

Use the **Direct connection** parameters. This bypasses PgBouncer entirely.

Direct connection is required because this project uses `SET SESSION` variables for Row-Level Security (RLS). PgBouncer in transaction mode resets session-level settings between queries, which breaks the RLS middleware. See section 5 for details.

Gather these parameters from the dashboard:
- Host
- Port (default `5432`)
- Database name (`postgres` by default)
- Username (`postgres` by default)
- Password (the one you created in step 2)

#### Option B: Connection Pooling (PgBouncer)

Supabase provides a built-in connection pooler (PgBouncer) for high-concurrency scenarios. It works well for standard queries.

**Important**: RLS session variables will NOT work with PgBouncer in transaction mode (the Supabase default). If you need pooling AND RLS support, you must configure the pooler in session mode instead.

> **Note**: The RLS middleware in this project uses `SET SESSION` variables for per-user access control. PgBouncer in transaction mode resets these variables between queries. For RLS to work correctly, use the direct database connection.

### API Configuration

1. Go to **Project Settings** > **API**.
2. Locate and copy these three values:

| Value | Env Variable | Where to find it |
|-------|-------------|------------------|
| Project URL | `SUPABASE_URL` | Under **Project URL** (format: `https://xxxxx.supabase.co`) |
| anon public key | `SUPABASE_KEY` | Under **Project API keys** > **anon public** |
| service_role key | `SUPABASE_SERVICE_KEY` | Under **Project API keys** > **service_role** (keep this secret) |

The `SUPABASE_URL` is used for Supabase Management API calls (backup monitoring and status checks). The `SUPABASE_SERVICE_KEY` is a privileged key used only for admin-level management API operations, not for database queries.

## 4. Configuring Laravel

### Environment Variables

Edit your `.env` file (copy from `.env.example` if you have not done so yet):

```env
# Database - use direct connection (not pooler) for RLS
DB_CONNECTION=pgsql
DB_HOST=db.xxxxxxxxxxxxxx.supabase.co
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=your-database-password
DB_SSLMODE=require

# Supabase API
SUPABASE_URL=https://xxxxxxxxxxxxxx.supabase.co
SUPABASE_KEY=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
SUPABASE_SERVICE_KEY=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

Replace the placeholder values with the actual credentials from your Supabase project settings.

### Verify Configuration

Run the built-in check command to confirm everything is connected:

```bash
php artisan supabase:check
```

Expected output (your keys will differ):

```
Supabase Configuration Check

Config                  Status
SUPABASE_URL           ✓ https://xxxxx.supabase.co
SUPABASE_KEY           ✓ eyJhbGci...
SUPABASE_SERVICE_KEY   ✓ eyJhbGci...
DB_SSLMODE             ✓ require
Supabase API connectivity  ✓ DONE
```

If the check fails, run with the `-v` flag for verbose output:

```bash
php artisan supabase:check -v
```

### Run Migrations

```bash
php artisan migrate
php artisan db:seed
```

The RLS migrations will enable Row-Level Security on the relevant tables and create per-role policies automatically. The seeder will populate your database with initial roles, agencies, and services.

## 5. How RLS Works in This Project

### Auth Pattern

This project uses **Laravel's built-in authentication** with custom OTP 2FA. It does NOT use Supabase Auth. User sessions are managed entirely by Laravel and Sanctum.

Supabase is used only as the PostgreSQL database provider and for management API calls (monitoring, backups).

### Session Variable Middleware

On every authenticated request, the `SetPostgresSession` middleware runs two SQL statements to set the current user's identity:

```sql
SET SESSION app.current_user_id = 'user-uuid';
SET SESSION app.user_role = 'CASE_MANAGER';
```

These session-level variables are scoped to the database connection. They persist only as long as the connection lives, which is why PgBouncer transaction-mode pooling breaks them.

RLS policies on each table use `current_setting('app.current_user_id')` and `current_setting('app.user_role')` to determine access. The `current_setting()` function reads the session variable you set earlier.

### RLS Policy Design

| Role | Access Level |
|------|-------------|
| **ADMIN** | Full access to all tables (policies use a blanket bypass) |
| **CASE_MANAGER** | Their own cases and related records |
| **AGENCY** | Cases referred to their agency |

Each policy references the session variables to decide whether to allow or deny access to a row.

### Important Notes

- RLS is enforced at the database level through the direct PostgreSQL connection.
- The `service_role` key is only used for Supabase Management API calls (backup monitoring, project status). It is never used for database queries.
- RLS requires a direct database connection. If you connect through PgBouncer in transaction mode, session variables get reset. Policies that expect `app.current_user_id` will fail.
- All policies use `current_setting(..., TRUE)`. The `TRUE` parameter returns `NULL` instead of raising an error if the setting is missing. This means a missing session variable results in denied access, not a crash.

## 6. Troubleshooting

| Problem | Likely Cause | Solution |
|---------|-------------|----------|
| `SQLSTATE[08006]` connection refused | Supabase IP allowlist blocking your connection | Check the IP allowlist in Supabase Dashboard > Database > Network settings. Add your server's IP address. |
| `SSL connection required` | Missing `DB_SSLMODE` config | Set `DB_SSLMODE=require` in your `.env` file. Supabase requires SSL connections. |
| RLS blocking all queries (even new rows) | The `SetPostgresSession` middleware is not running | Make sure `SetPostgresSession` middleware is registered in `bootstrap/app.php` under the web middleware group. |
| `SET SESSION` variables not persisting | Connected through PgBouncer transaction mode | Switch to the direct database connection. Check your `DB_HOST` and `DB_PORT`: direct uses port `5432`, pooler uses port `6543`. |
| `php artisan supabase:check` fails | Missing or incorrect `.env` values | Run with `-v` for verbose output. Double-check that all three Supabase env vars are set correctly in `.env`. |
| `php artisan migrate` fails with permission errors | Database user lacks schema creation privileges | In Supabase Dashboard, go to Database > SQL Editor and run: `GRANT ALL ON SCHEMA public TO postgres;` |

### Still stuck?

- Refer to [Supabase Documentation](https://supabase.com/docs) for platform-specific issues.
- See the [PostgreSQL Row-Level Security docs](https://www.postgresql.org/docs/current/ddl-rowsecurity.html) for RLS policy syntax.
- Check the [Supabase Postgres Best Practices guide](https://github.com/supabase/agent-skills/tree/main/skills/supabase-postgres-best-practices) for query optimization tips.
- The [DEPLOYMENT_GUIDE.md](./DEPLOYMENT_GUIDE.md) covers the full production deployment workflow, including environment promotion and CI/CD.
