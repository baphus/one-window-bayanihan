<?php

namespace Database\Seeders;

use App\Models\CaseIssue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CaseIssueSeeder extends Seeder
{
    public function run(): void
    {
        $issues = [
            ['name' => 'Illegal Recruitment', 'description' => null, 'sort_order' => 1, 'is_active' => true],
            ['name' => 'Contract Violation', 'description' => null, 'sort_order' => 2, 'is_active' => true],
            ['name' => 'Salary/Wage Dispute', 'description' => null, 'sort_order' => 3, 'is_active' => true],
            ['name' => 'Employer Abuse/Maltreatment', 'description' => null, 'sort_order' => 4, 'is_active' => true],
            ['name' => 'Medical Repatriation', 'description' => null, 'sort_order' => 5, 'is_active' => true],
            ['name' => 'Emergency Repatriation', 'description' => null, 'sort_order' => 6, 'is_active' => true],
            ['name' => 'Repatriation of Remains', 'description' => null, 'sort_order' => 7, 'is_active' => true],
            ['name' => 'Documentation Assistance', 'description' => null, 'sort_order' => 8, 'is_active' => true],
            ['name' => 'Welfare/Welcoming Concern', 'description' => null, 'sort_order' => 9, 'is_active' => true],
            ['name' => 'Family Emergency', 'description' => null, 'sort_order' => 10, 'is_active' => true],
            ['name' => 'Other Concern', 'description' => null, 'sort_order' => 11, 'is_active' => true],
        ];

        foreach ($issues as $issue) {
            CaseIssue::firstOrCreate(
                ['name' => $issue['name']],
                array_merge($issue, ['id' => (string) Str::uuid()]),
            );
        }
    }
}
