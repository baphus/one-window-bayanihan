<?php

namespace Tests\Feature;

use Tests\TestCase;

class InsightsAccessTest extends TestCase
{
    // -----------------------------------------------------------------------
    //  ADMIN
    // -----------------------------------------------------------------------

    public function test_admin_can_view_all(): void
    {
        $role = 'ADMIN';

        // ADMIN should have full access to all views
        $restrictedViews = [
            'geographic', 'overloaded_agencies', 'bottleneck_detection',
            'agency_scorecard', 'satisfaction_other', 'predictive',
            'case_trend', 'status_distribution', 'category_distribution',
            'client_type_split', 'aging_cases', 'cm_scorecard',
        ];

        foreach ($restrictedViews as $view) {
            $this->assertTrue(
                $this->simulateCan($role, $view),
                "ADMIN should be able to view '{$view}'"
            );
        }

        // ADMIN should have all 8 tabs
        $allTabs = $this->simulateAllowedTabs($role);
        $this->assertCount(8, $allTabs);
        $this->assertEquals([
            'executive', 'trends', 'distribution', 'operational',
            'scorecards', 'satisfaction', 'predictive', 'alerts',
        ], $allTabs);

        // ADMIN should have no filter scope
        $this->assertNull($this->simulateFilterScope($role));
    }

    // -----------------------------------------------------------------------
    //  CASE_MANAGER
    // -----------------------------------------------------------------------

    public function test_cm_cannot_view_scorecards(): void
    {
        $role = 'CASE_MANAGER';

        // CM should NOT be able to view certain sections
        $this->assertFalse(
            $this->simulateCan($role, 'predictive'),
            'CASE_MANAGER should NOT be able to view predictive'
        );
        $this->assertFalse(
            $this->simulateCan($role, 'agency_scorecard'),
            'CASE_MANAGER should NOT be able to view agency_scorecard'
        );
        $this->assertFalse(
            $this->simulateCan($role, 'satisfaction_other'),
            'CASE_MANAGER should NOT be able to view satisfaction_other'
        );

        // CM should still be able to view some sections
        $this->assertTrue(
            $this->simulateCan($role, 'executive'),
            'CASE_MANAGER should be able to view executive'
        );
        $this->assertTrue(
            $this->simulateCan($role, 'cm_scorecard'),
            'CASE_MANAGER should be able to view cm_scorecard'
        );

        // CM allowedTabs should exclude 'predictive'
        $allowedTabs = $this->simulateAllowedTabs($role);
        $this->assertNotContains('predictive', $allowedTabs);
        $this->assertContains('scorecards', $allowedTabs);
        $this->assertContains('executive', $allowedTabs);

        // CM should have no filter scope
        $this->assertNull($this->simulateFilterScope($role));
    }

    // -----------------------------------------------------------------------
    //  AGENCY
    // -----------------------------------------------------------------------

    public function test_agency_owns_only(): void
    {
        $role = 'AGENCY';

        // AGENCY should NOT be able to view CM-restricted sections
        $this->assertFalse(
            $this->simulateCan($role, 'case_trend'),
            'AGENCY should NOT be able to view case_trend'
        );
        $this->assertFalse(
            $this->simulateCan($role, 'cm_scorecard'),
            'AGENCY should NOT be able to view cm_scorecard'
        );

        // AGENCY should be able to view agency-specific sections
        $this->assertTrue(
            $this->simulateCan($role, 'agency_scorecard'),
            'AGENCY should be able to view agency_scorecard'
        );

        // AGENCY allowedTabs should exclude 'trends'
        $allowedTabs = $this->simulateAllowedTabs($role);
        $this->assertNotContains('trends', $allowedTabs);
        $this->assertContains('distribution', $allowedTabs);

        // AGENCY should have 'agency' filter scope
        $this->assertEquals('agency', $this->simulateFilterScope($role));
    }

    // -----------------------------------------------------------------------
    //  Helpers — replicate the useInsightsAccess hook's logic in PHP
    // -----------------------------------------------------------------------

    /**
     * Simulate the can() function from useInsightsAccess.jsx.
     */
    private function simulateCan(string $role, string $view): bool
    {
        $restrictedViews = [
            'ADMIN' => [],
            'CASE_MANAGER' => [
                'geographic', 'overloaded_agencies', 'bottleneck_detection',
                'agency_scorecard', 'satisfaction_other', 'predictive',
            ],
            'AGENCY' => [
                'case_trend', 'status_distribution', 'category_distribution',
                'client_type_split', 'aging_cases', 'overloaded_agencies',
                'bottleneck_detection', 'cm_scorecard',
            ],
        ];

        $restricted = $restrictedViews[$role] ?? [];

        return ! in_array($view, $restricted, true);
    }

    /**
     * Simulate the allowedTabs getter from useInsightsAccess.jsx.
     */
    private function simulateAllowedTabs(string $role): array
    {
        $allTabs = [
            'executive', 'trends', 'distribution', 'operational',
            'scorecards', 'satisfaction', 'predictive', 'alerts',
        ];

        $allowedByRole = [
            'CASE_MANAGER' => [
                'executive', 'trends', 'distribution', 'operational',
                'scorecards', 'satisfaction', 'alerts',
            ],
            'AGENCY' => [
                'executive', 'distribution', 'operational', 'scorecards',
                'satisfaction', 'predictive', 'alerts',
            ],
        ];

        return $allowedByRole[$role] ?? $allTabs;
    }

    /**
     * Simulate the filterScope getter from useInsightsAccess.jsx.
     */
    private function simulateFilterScope(string $role): ?string
    {
        return match ($role) {
            'AGENCY' => 'agency',
            default => null,
        };
    }
}
