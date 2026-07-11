<?php

namespace App\Services;

use App\Models\AuditArchive;
use App\Models\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class AuditArchiveService
{
    /**
     * Calendar months (YYYY-MM) that lie entirely before the cutoff, contain
     * audit entries, and have no finalized bundle yet.
     */
    public function eligiblePeriods(CarbonImmutable $cutoff): array
    {
        $lastFullMonthEnd = $cutoff->startOfMonth();

        $periods = AuditLog::query()
            ->where('timestamp', '<', $lastFullMonthEnd)
            ->selectRaw("to_char(timestamp, 'YYYY-MM') as period")
            ->groupBy('period')
            ->orderBy('period')
            ->pluck('period')
            ->all();

        $finalized = AuditArchive::whereNotNull('finalized_at')->pluck('period')->all();

        return array_values(array_diff($periods, $finalized));
    }

    /**
     * Write one calendar month of audit entries to a gzipped NDJSON bundle
     * plus manifest on the archive disk, verify the upload by re-reading and
     * re-hashing it, and record the finalized bundle. Idempotent per period.
     */
    public function archivePeriod(string $period): AuditArchive
    {
        $existing = AuditArchive::where('period', $period)->whereNotNull('finalized_at')->first();
        if ($existing) {
            return $existing;
        }

        $start = CarbonImmutable::createFromFormat('Y-m-d', "{$period}-01")->startOfDay();
        $end = $start->addMonth();

        $disk = Storage::disk(config('audit.archive_disk'));
        $bundlePath = $start->format('Y')."/audit-{$period}.ndjson.gz";
        $manifestPath = $start->format('Y')."/audit-{$period}.manifest.json";

        // Build the bundle in a local temp stream first; hash the compressed
        // bytes as written so the manifest checksum covers the stored file.
        $tempPath = tempnam(sys_get_temp_dir(), 'audit-archive-');
        $gz = gzopen($tempPath, 'wb9');
        if ($gz === false) {
            throw new RuntimeException("Unable to open temp bundle stream for {$period}");
        }

        $rowCount = 0;
        $firstEntryAt = null;
        $lastEntryAt = null;
        $lastDigest = null;

        try {
            AuditLog::where('timestamp', '>=', $start)
                ->where('timestamp', '<', $end)
                ->orderBy('chain_seq')
                ->chunk(1000, function ($logs) use ($gz, &$rowCount, &$firstEntryAt, &$lastEntryAt, &$lastDigest) {
                    foreach ($logs as $log) {
                        gzwrite($gz, json_encode($log->getAttributes(), JSON_UNESCAPED_SLASHES)."\n");
                        $rowCount++;
                        $firstEntryAt ??= $log->timestamp;
                        $lastEntryAt = $log->timestamp;
                        $lastDigest = $log->chainDigest();
                    }
                });
        } finally {
            gzclose($gz);
        }

        if ($rowCount === 0) {
            @unlink($tempPath);
            throw new RuntimeException("No audit entries found for period {$period}");
        }

        $checksum = hash_file('sha256', $tempPath);

        $stream = fopen($tempPath, 'rb');
        try {
            $disk->writeStream($bundlePath, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            @unlink($tempPath);
        }

        // Read back and re-hash: the period is only finalized once the stored
        // object provably matches what was written (guards against silent
        // upload corruption, especially on remote S3-compatible disks).
        $storedChecksum = $this->checksumOf($disk, $bundlePath);
        if ($storedChecksum !== $checksum) {
            throw new RuntimeException("Checksum mismatch after upload for {$bundlePath}: wrote {$checksum}, read {$storedChecksum}");
        }

        $manifest = [
            'period' => $period,
            'row_count' => $rowCount,
            'first_entry_at' => $firstEntryAt?->toIso8601String(),
            'last_entry_at' => $lastEntryAt?->toIso8601String(),
            'bundle_path' => $bundlePath,
            'bundle_sha256' => $checksum,
            'last_entry_chain_digest' => $lastDigest,
            'created_at' => now()->toIso8601String(),
        ];
        $disk->put($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return AuditArchive::updateOrCreate(
            ['period' => $period],
            [
                'path' => $bundlePath,
                'checksum' => $checksum,
                'row_count' => $rowCount,
                'first_entry_at' => $firstEntryAt,
                'last_entry_at' => $lastEntryAt,
                'finalized_at' => now(),
            ]
        );
    }

    /**
     * SHA-256 of a stored bundle, streamed so large bundles do not load
     * into memory.
     */
    public function checksumOf($disk, string $path): string
    {
        $stream = $disk->readStream($path);
        if ($stream === null || $stream === false) {
            throw new RuntimeException("Archive bundle unreadable: {$path}");
        }

        $ctx = hash_init('sha256');
        try {
            while (! feof($stream)) {
                $chunk = fread($stream, 1024 * 1024);
                if ($chunk === false) {
                    throw new RuntimeException("Failed reading archive bundle: {$path}");
                }
                hash_update($ctx, $chunk);
            }
        } finally {
            fclose($stream);
        }

        return hash_final($ctx);
    }

    /**
     * Re-verify a finalized bundle's stored checksum against its record.
     */
    public function verifyBundle(AuditArchive $archive): bool
    {
        $disk = Storage::disk(config('audit.archive_disk'));

        if (! $disk->exists($archive->path)) {
            return false;
        }

        return $this->checksumOf($disk, $archive->path) === $archive->checksum;
    }

    public function manifestPathFor(AuditArchive $archive): string
    {
        return preg_replace('/\.ndjson\.gz$/', '.manifest.json', $archive->path);
    }
}
