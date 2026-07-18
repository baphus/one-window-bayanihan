<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\UsesCaseDraftMaintenanceConnection;
use App\Encryption\CaseDraftReencryptionService;
use App\Exceptions\CaseDraftPayloadDecryptionException;
use App\Models\CaseDraft;
use Illuminate\Console\Command;
use Throwable;

final class ReencryptCaseDraftPayloads extends Command
{
    use UsesCaseDraftMaintenanceConnection;

    protected $signature = 'case-drafts:reencrypt
        {--after= : Resume after this draft UUID (exclusive)}
        {--limit=100 : Maximum number of editable drafts to inspect}
        {--dry-run : Validate and report without writing changes}';

    protected $description = 'Re-encrypt editable case-draft payloads with the current encryption key';

    public function handle(CaseDraftReencryptionService $reencryption): int
    {
        try {
            $this->useCaseDraftMaintenanceConnection();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $limit = (int) $this->option('limit');
        if ($limit < 1 || $limit > 1000) {
            $this->error('The --limit must be between 1 and 1000.');

            return self::INVALID;
        }

        $query = CaseDraft::query()
            ->where('state', CaseDraft::STATE_EDITING)
            ->whereNotNull('payload_encrypted')
            ->orderBy('id')
            ->limit($limit);

        if ($after = $this->option('after')) {
            $query->where('id', '>', $after);
        }

        $scanned = $rewritten = $skipped = $failed = 0;
        $lastId = null;

        foreach ($query->get() as $draft) {
            $scanned++;
            $lastId = $draft->id;
            $ciphertext = (string) $draft->getRawOriginal('payload_encrypted');

            try {
                if ($reencryption->reencryptIfUnchanged($draft->id, $ciphertext, (bool) $this->option('dry-run'))) {
                    $rewritten++;
                } else {
                    $skipped++;
                }
            } catch (CaseDraftPayloadDecryptionException $exception) {
                $failed++;
                $this->error("{$draft->id}: {$exception->getMessage()}");
            } catch (Throwable $exception) {
                $failed++;
                $this->error("{$draft->id}: re-encryption failed ({$exception->getMessage()})");
            }
        }

        $mode = $this->option('dry-run') ? 'validated' : 'processed';
        $this->info("{$mode}: {$scanned}; rewritten: {$rewritten}; already current: {$skipped}; failed: {$failed}.");
        if ($lastId !== null) {
            $this->line("Resume with: --after={$lastId}");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
