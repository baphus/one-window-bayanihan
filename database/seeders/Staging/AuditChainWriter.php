<?php

namespace Database\Seeders\Staging;

use App\Models\AuditLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Hash-chained audit row builder reusing the proven fix-4 approach
 * (see TestingSeeder's audit section): bulk inserts bypass the
 * AuditLog::creating hook, so this class computes prev_hash itself.
 *
 * Usage (T10):
 *   $writer = AuditChainWriter::createFromCurrentTail(); // or new AuditChainWriter()
 *   foreach ($timeline as $event) { $writer->push([...]); }  // timeline order == chain_seq order
 *   $inserted = $writer->finalize();                         // chains + inserts in chunks of 200
 *
 * Rules enforced here:
 * - Digests are computed in finalize(), AFTER the full timeline is built
 *   (shared Carbon instances keep mutating during the build).
 * - chainDigest() is replicated field-for-field:
 *   sha256(id|action|module|entity_id|user_id|timestamp ISO-8601|old_value|new_value|ip_address|prev_hash),
 *   JSON_UNESCAPED_SLASHES, null payloads digest as "null".
 * - old_value/new_value digest from their DB-normalised form
 *   (SELECT (col::jsonb)::text, batched at 500 with dedup) because
 *   PostgreSQL normalises/reorders stored jsonb documents — digesting the
 *   raw pre-insert strings would break audit:verify.
 * - Chain root: first row gets prev_hash null on an empty table, otherwise
 *   chaining starts from the existing tail digest.
 */
class AuditChainWriter
{
    /**
     * jsonb normalisation batch size (with dedup, as fix-4 does).
     */
    public const NORMALIZE_BATCH_SIZE = 500;

    /**
     * audit_logs insert chunk size.
     */
    public const INSERT_CHUNK_SIZE = 200;

    /**
     * Buffered rows in chain (insertion) order. prev_hash is assigned in
     * finalize(), not at push() time.
     *
     * @var list<array<string, mixed>>
     */
    private array $rows = [];

    /**
     * Digest of the row preceding the buffer (null = buffer starts the chain).
     */
    private ?string $previousDigest;

    /**
     * @param  string|null  $previousDigest  tail digest to chain off, or null for a chain root.
     */
    public function __construct(?string $previousDigest = null)
    {
        $this->previousDigest = $previousDigest;
    }

    /**
     * Start a writer chained off the current table tail. On an empty table
     * (the truncate-then-seed case) the first pushed row becomes the chain
     * root with prev_hash null — identical to a model-created first row.
     */
    public static function createFromCurrentTail(): self
    {
        return new self(AuditLog::orderBy('chain_seq', 'desc')->first()?->chainDigest());
    }

    /**
     * Buffer one audit row. Required keys: id, action, module. Optional:
     * entity_id, user_id, timestamp (Carbon|DateTimeInterface|string|null),
     * old_value / new_value (array|string|null — arrays are JSON-encoded
     * here with JSON_UNESCAPED_SLASHES), ip_address, description, category,
     * user_agent, request_id.
     *
     * @param  array<string, mixed>  $row
     */
    public function push(array $row): static
    {
        foreach (['id', 'action', 'module'] as $key) {
            if (! array_key_exists($key, $row)) {
                throw new \InvalidArgumentException("Audit row is missing required key '{$key}'.");
            }
        }

        foreach (['old_value', 'new_value'] as $column) {
            if (array_key_exists($column, $row) && is_array($row[$column])) {
                $row[$column] = json_encode($row[$column], JSON_UNESCAPED_SLASHES);
            }
        }

        $this->rows[] = $row;

        return $this;
    }

    /**
     * Buffered row count.
     */
    public function count(): int
    {
        return count($this->rows);
    }

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }

    /**
     * Read-only copy of the buffered rows (prev_hash unset until finalize()).
     *
     * @return list<array<string, mixed>>
     */
    public function rows(): array
    {
        return $this->rows;
    }

    /**
     * Digest of the latest chained row (buffer tail, or the construction
     * tail when empty). Useful for chaining across multiple finalize()
     * calls without re-querying the table.
     */
    public function tailDigest(): ?string
    {
        return $this->previousDigest;
    }

    /**
     * Compute the chain, assign prev_hash, and bulk-insert in chunks of
     * 200. Clears the buffer; returns the inserted row count (0 when empty,
     * with no queries issued).
     */
    public function finalize(): int
    {
        if ($this->rows === []) {
            return 0;
        }

        $normalisedByRaw = $this->normalisePayloads();

        $previousDigest = $this->previousDigest;

        foreach ($this->rows as &$row) {
            $row['prev_hash'] = $previousDigest;
            $previousDigest = $this->digestOf($row, $previousDigest, $normalisedByRaw);
        }
        unset($row);

        $this->previousDigest = $previousDigest;

        $inserted = 0;

        foreach (array_chunk($this->rows, self::INSERT_CHUNK_SIZE) as $chunk) {
            DB::table('audit_logs')->insert($chunk);
            $inserted += count($chunk);
        }

        $this->rows = [];

        return $inserted;
    }

    // ------------------------------------------------------------------
    // Internals (fix-4 replication — keep field list/format in sync with
    // App\Models\AuditLog::chainDigest()).
    // ------------------------------------------------------------------

    /**
     * Normalise every distinct non-null payload through the database so
     * digests use the exact document form PostgreSQL will store.
     *
     * @return array<string, string> raw JSON string => normalised text
     */
    private function normalisePayloads(): array
    {
        $rawPayloads = [];

        foreach ($this->rows as $row) {
            foreach (['old_value', 'new_value'] as $column) {
                if (($row[$column] ?? null) !== null) {
                    $rawPayloads[] = $row[$column];
                }
            }
        }

        $normalisedByRaw = [];

        foreach (array_chunk(array_values(array_unique($rawPayloads)), self::NORMALIZE_BATCH_SIZE) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '(?)'));
            $normalisedRows = DB::select(
                "SELECT (col::jsonb)::text AS normalised FROM (VALUES {$placeholders}) AS v(col)",
                $chunk
            );

            foreach ($chunk as $index => $raw) {
                $normalisedByRaw[$raw] = $normalisedRows[$index]->normalised;
            }
        }

        return $normalisedByRaw;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $normalisedByRaw
     */
    private function digestOf(array $row, ?string $prevHash, array $normalisedByRaw): string
    {
        $encodeJson = static function ($value) use ($normalisedByRaw): string {
            if ($value === null) {
                return json_encode(null, JSON_UNESCAPED_SLASHES);
            }

            $raw = is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_SLASHES);
            $normalised = $normalisedByRaw[$raw] ?? $raw;

            return json_encode(json_decode($normalised, true), JSON_UNESCAPED_SLASHES);
        };

        $content = implode('|', [
            $row['id'],
            $row['action'],
            $row['module'],
            $row['entity_id'] ?? '',
            $row['user_id'] ?? '',
            $row['timestamp'] instanceof \DateTimeInterface
                ? Carbon::parse($row['timestamp'])->toIso8601String()
                : '',
            $encodeJson($row['old_value'] ?? null),
            $encodeJson($row['new_value'] ?? null),
            $row['ip_address'] ?? '',
            $prevHash ?? '',
        ]);

        return hash('sha256', $content);
    }
}
