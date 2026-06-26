const content = `# Monitoring Queue Jobs and System Health

The One Window Bayanihan system uses a database-driven queue for background job processing. This guide covers how to monitor and maintain the queue and overall system health.

## Understanding the Queue System

The system uses \`QUEUE_CONNECTION=database\`, meaning all queued jobs are stored in the \`jobs\` database table. A worker process (\`queue:listen\`) picks up jobs and processes them in the background.

### What Uses the Queue

- **Email notifications** — OTP codes, case status updates
- **SMS notifications** — tracker numbers, alerts
- **Document processing** — file validation, storage uploads
- **Audit log entries** — some are queued for performance

## Checking Queue Health

The **failed_jobs** table stores any jobs that could not be processed after retries:

\`\`\`
php artisan queue:failed
\`\`\`

This lists all failed jobs with their ID, connection, queue, and failure reason.

## Monitoring Failed Jobs

### Viewing Failure Details

Each failed job record contains:
- The **exception message** explaining what went wrong
- The **job payload** with the data being processed
- The **failed_at** timestamp

### Common Failure Causes

| Issue | Typical Cause | Solution |
|-------|---------------|----------|
| Email sending failure | SMTP configuration issue | Check mail settings |
| Document upload failure | Storage connectivity | Verify storage config |
| Database deadlock | Concurrent job conflicts | Retry the job |
| Model not found | Record deleted before job ran | Check data integrity |

### Retrying Failed Jobs

To retry a specific failed job:

\`\`\`
php artisan queue:retry <job_id>
\`\`\`

To retry all failed jobs:

\`\`\`
php artisan queue:retry all
\`\`\`

## Restarting the Queue Worker

The queue worker runs continuously, processing jobs as they arrive. If it stops or becomes unresponsive:

1. Stop the current worker (Ctrl+C)
2. Start it again:

\`\`\`
php artisan queue:listen
\`\`\`

For production environments, use a process monitor (like Supervisor on Linux) to keep the worker running automatically.

## Cache and Session Management

The system uses \`CACHE_STORE=database\` for cache storage:

- OTP codes are stored in the \`cache\` table with TTL
- Session data is also stored in the database (if using database sessions)
- System settings are cached for performance

### Clearing the Cache

\`\`\`
php artisan cache:clear
\`\`\`

Use this command when:
- System configuration changes do not take effect
- Cached data appears stale
- After deploying updates

## Common System Health Checks

Run these checks regularly to ensure system health:

### 1. Database Connection
\`\`\`
php artisan db:monitor
\`\`\`
Verify the database is reachable and responsive.

### 2. Queue Worker Status
Check that the queue worker is running. If not, jobs will accumulate in the jobs table without being processed.

### 3. Storage Link
\`\`\`
php artisan storage:link
\`\`\`
Ensure the public storage symlink exists. Without it, uploaded files cannot be accessed.

### 4. Config Cache
If using config caching in production:
\`\`\`
php artisan config:cache
\`\`\`
Remember to clear and re-cache after configuration changes:
\`\`\`
php artisan config:clear
\`\`\`

### 5. Application Health
\`\`\`
php artisan about
\`\`\`
Displays the application version, environment, cache status, and other relevant information.

## Using php artisan queue:monitor

Laravel provides a queue monitor command:

\`\`\`
php artisan queue:monitor redis:default,redis:default --max=100
\`\`\`

For this system, use the database connection:

\`\`\`
php artisan queue:monitor database:default
\`\`\`

This command checks queue sizes and can trigger events when queues exceed thresholds.

## Setting Up Monitoring Alerts

For proactive monitoring:

1. **Check failed jobs daily** — review and retry as needed
2. **Set up a cron job** to restart the queue worker periodically
3. **Monitor disk space** — logs and uploads can consume space
4. **Check system logs** — look for error patterns

## Troubleshooting Queue Issues

| Symptom | Possible Cause | Check |
|---------|---------------|-------|
| Jobs not processing | Queue worker not running | Run queue:listen |
| Jobs repeatedly failing | Code error or dependency issue | Check failed_jobs table |
| Slow processing | High job volume or resource contention | Check server resources |
| Memory errors | Job memory leak | Increase memory limit or fix job
`;

export default content;
