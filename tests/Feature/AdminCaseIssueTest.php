<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminCaseIssueTest extends TestCase
{
    public function test_obsolete_case_issue_endpoints_are_not_registered(): void
    {
        foreach ([
            'case-issues.quick',
            'admin.case-issues.index',
            'admin.case-issues.store',
            'admin.case-issues.update',
            'admin.case-issues.destroy',
            'admin.case-issues.reactivate',
        ] as $name) {
            $this->assertFalse(Route::has($name), "Retired route [{$name}] must stay absent.");
        }

        $this->postJson('/case-issues/quick', ['name' => 'Legacy issue'])->assertNotFound();
        $this->get('/admin/case-issues')->assertNotFound();
    }
}
