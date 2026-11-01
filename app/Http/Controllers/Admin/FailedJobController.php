<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Failed queue jobs for admins.
 *
 * failed_jobs keeps one week of payload (`queue:prune-failed --hours=168`).
 * Until now the only way to read a failure was a shell on the worker, which
 * is exactly where an admin is not.
 *
 * Retry is delegated to `queue:retry` so the page does not reimplement what
 * the framework already guarantees (releasing to the queue, removing the row,
 * emitting the right events).
 */
class FailedJobController extends Controller
{
    use ResolvesPerPage;

    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer'],
        ]);

        $query = DB::table('failed_jobs')->orderByDesc('failed_at');

        if (! empty($filters['search'])) {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $filters['search']).'%';
            $query->where(function ($q) use ($term) {
                $q->where('queue', 'ilike', $term)
                    ->orWhere('exception', 'ilike', $term);
            });
        }

        $jobs = $query->paginate($this->perPage($request, 25))
            ->appends($request->except(['page', 'cursor']))
            ->through(fn ($job) => [
                'id' => $job->id,
                'uuid' => $job->uuid,
                'queue' => $job->queue,
                'failed_at' => $job->failed_at ? Carbon::parse($job->failed_at)->toIso8601String() : null,
                // The first line of the exception is the whole story for the
                // list; the trace belongs on the detail view.
                'exception_summary' => $this->summarize($job->exception),
            ]);

        return Inertia::render('Admin/FailedJobs/Index', [
            'jobs' => $jobs,
            'filterValues' => (object) $request->only(['search', 'per_page']),
        ]);
    }

    /**
     * One failure in full.
     *
     * The payload and trace are shown because they are the only things that
     * let someone diagnose the failure without shell access. Neither contains
     * redacted material beyond what the job itself serialized.
     */
    public function show(string $uuid)
    {
        $job = DB::table('failed_jobs')->where('uuid', $uuid)->first();

        abort_if($job === null, 404);

        return Inertia::render('Admin/FailedJobs/Show', [
            'job' => [
                'id' => $job->id,
                'uuid' => $job->uuid,
                'connection' => $job->connection,
                'queue' => $job->queue,
                'failed_at' => $job->failed_at ? Carbon::parse($job->failed_at)->toIso8601String() : null,
                'exception' => $job->exception,
                'payload' => $this->decodedPayload($job->payload),
            ],
        ]);
    }

    /**
     * Re-queue a failure.
     *
     * Routed through the framework command rather than deleting the row and
     * pushing by hand: `queue:retry` re-creates the job from its stored
     * payload and only removes the row on success, so a failure here leaves
     * the original intact rather than losing it.
     */
    public function retry(string $uuid)
    {
        $exists = DB::table('failed_jobs')->where('uuid', $uuid)->exists();

        abort_if(! $exists, 404);

        $exitCode = Artisan::call('queue:retry', ['id' => [$uuid]]);

        if ($exitCode !== 0) {
            return back()->withErrors(['retry' => 'The job could not be re-queued. See the application log.']);
        }

        return back()->with('success', 'Job re-queued.');
    }

    /**
     * The first line of an exception, trimmed for a table row.
     */
    private function summarize(?string $exception): string
    {
        if ($exception === null || trim($exception) === '') {
            return 'No exception recorded';
        }

        $firstLine = strtok($exception, "\n");

        return mb_strimwidth((string) $firstLine, 0, 180, '…');
    }

    /**
     * @return array<string, mixed>
     */
    private function decodedPayload(string $payload): array
    {
        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : [];
    }
}
