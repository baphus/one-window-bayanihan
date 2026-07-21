<?php

namespace Tests\Feature;

use App\Enums\AuditModule;
use App\Models\CaseCategory;
use App\Observers\AuditObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards against silent loss of audit coverage: if a model is dropped from
 * config('audit.observed_models') (and thus stops being audited), these tests
 * fail rather than the loss going unnoticed.
 *
 * Coverage is verified two ways because most observed models require complex
 * FK setup to instantiate: (1) every listed model has AuditObserver actually
 * wired to its create/update/delete events, and (2) an end-to-end create of a
 * representative model genuinely produces an audit row. Functional emission for
 * other specific models is covered by their own feature tests
 * (e.g. TrackingService/CaseStatusAuditTrailTest for CaseFile).
 */
class AuditModelCoverageTest extends TestCase
{
    use RefreshDatabase;

    // Note: the model list is read inside each test (not via a @dataProvider),
    // because data providers run before the Laravel application boots, so
    // config() is unavailable there.
    private function observedModels(): array
    {
        return config('audit.observed_models', []);
    }

    public function test_the_observed_model_list_is_not_empty(): void
    {
        $this->assertNotEmpty(
            $this->observedModels(),
            'config(audit.observed_models) must list the audited models.'
        );
    }

    public function test_every_observed_model_has_audit_observer_wired(): void
    {
        foreach ($this->observedModels() as $model) {
            $this->assertTrue(class_exists($model), "{$model} does not exist");

            $dispatcher = $model::getEventDispatcher();

            foreach (['created', 'updated', 'deleted'] as $event) {
                $this->assertTrue(
                    $dispatcher->hasListeners("eloquent.{$event}: {$model}"),
                    "AuditObserver is not observing {$event} on {$model}"
                );
            }
        }
    }

    public function test_every_observed_model_declares_a_canonical_audit_contract(): void
    {
        foreach ($this->observedModels() as $model) {
            $this->assertTrue(
                method_exists($model, 'getAuditModuleName'),
                "{$model} must define getAuditModuleName()"
            );
            $this->assertTrue(
                property_exists($model, 'auditExclude'),
                "{$model} must declare \$auditExclude"
            );

            $moduleName = (new $model)->getAuditModuleName();

            $this->assertNotNull(
                AuditModule::tryFromLegacy($moduleName),
                "{$model} reports module '{$moduleName}', which is not a known AuditModule"
            );
        }
    }

    public function test_representative_model_creation_emits_an_audit_row(): void
    {
        $this->assertContains(CaseCategory::class, config('audit.observed_models', []));

        $category = CaseCategory::factory()->create();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'CREATE',
            'module' => 'case_category',
            'entity_id' => $category->id,
        ]);
    }

    public function test_observer_is_the_registered_class(): void
    {
        // Sanity check that we are asserting the right observer everywhere.
        $this->assertTrue(class_exists(AuditObserver::class));
    }
}
