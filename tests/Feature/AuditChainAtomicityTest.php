<?php

namespace Tests\Feature;

use App\Models\AuditChainCheckpoint;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditChainAtomicityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * These tests need the real transaction level, so the suite's wrapping
     * transaction is disabled and audit rows are cleared manually instead.
     */
    protected $connectionsToTransact = [];

    protected function setUp(): void
    {
        parent::setUp();

        AuditLog::truncate();
        AuditChainCheckpoint::truncate();
    }

    protected function tearDown(): void
    {
        // This class runs outside the suite's wrapping transaction, so its audit
        // rows are not rolled back. Clear them here as well as in setUp(): a row
        // left behind is visible to every later test in the same process (it made
        // AuditVocabularyTest count 13 rows for a 12-case enum).
        AuditLog::truncate();
        AuditChainCheckpoint::truncate();

        AuditLog::flushEventListeners();
        AuditLog::clearBootedModels();

        parent::tearDown();
    }

    private function makeLog(): AuditLog
    {
        return AuditLog::create([
            'action' => 'CREATE',
            'module' => 'case',
            'description' => 'Atomicity probe',
            'timestamp' => now(),
            'ip_address' => '127.0.0.1',
        ]);
    }

    public function test_save_outside_caller_transaction_runs_inside_one(): void
    {
        $this->assertSame(0, DB::transactionLevel());

        $levels = [];
        AuditLog::creating(function () use (&$levels) {
            $levels[] = DB::transactionLevel();
        });

        $this->makeLog();

        $this->assertSame([1], $levels);
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_save_inside_caller_transaction_does_not_nest(): void
    {
        $levels = [];
        AuditLog::creating(function () use (&$levels) {
            $levels[] = DB::transactionLevel();
        });

        DB::transaction(function () use (&$levels) {
            $this->makeLog();

            $this->assertSame([1], $levels);
            $this->assertSame(1, DB::transactionLevel());
        });
    }
}
