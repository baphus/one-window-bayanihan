<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CaseFile;
use App\Models\User;
use App\Services\CaseService;
use App\Services\IntakeDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MultiCasePerClientTest extends TestCase
{
    use RefreshDatabase;

    private function fileCase(User $owner, array $overrides = []): CaseFile
    {
        $data = CaseFile::factory()->withIntake()->make()->only(CaseFile::INTAKE_FIELDS);

        return app(CaseService::class)->createCase(array_merge($data, [
            'client_type' => 'OFW', 'summary' => 'Assistance requested', 'consent' => true,
            'consent_text_version' => IntakeDocumentService::CONSENT_VERSION,
            'documents' => collect(['passport_front_page', 'arrival_stamp', 'oec', 'sworn_affidavit'])
                ->mapWithKeys(fn ($slot) => [$slot => UploadedFile::fake()->image($slot.'.jpg')])->all(),
        ], $overrides), $owner->id);
    }

    public function test_account_can_file_multiple_independent_cases(): void
    {
        Storage::fake('object-storage');
        $owner = User::factory()->create(['role' => UserRole::OFW->value]);
        $first = $this->fileCase($owner);
        $second = $this->fileCase($owner, ['employment_employer_name' => 'Later Employer', 'address_street' => 'Later address']);
        $this->assertSame(2, $owner->ownedCases()->count());
        $this->assertSame('Example Employer', $first->fresh()->employment_employer_name);
        $this->assertSame('123 Example Street', $first->fresh()->address_street);
        $this->assertSame('Later Employer', $second->employment_employer_name);
        $this->assertSame($owner->id, $first->ofw_user_id);
        $this->assertSame($owner->id, $second->ofw_user_id);
    }

    public function test_account_settings_do_not_propagate_into_historical_cases(): void
    {
        Storage::fake('object-storage');
        $owner = User::factory()->create(['role' => UserRole::OFW->value]);
        $case = $this->fileCase($owner);
        $owner->update(['name' => 'Changed account name', 'email' => 'changed@example.test']);
        $this->assertSame('Juan', $case->fresh()->first_name);
        $this->assertSame('juan@example.test', $case->fresh()->email);
    }

    public function test_case_schema_has_no_case_wide_category_or_issue_columns(): void
    {
        $this->assertFalse(Schema::hasColumn('cases', 'category_id'));
        $this->assertFalse(Schema::hasColumn('cases', 'case_issue_id'));
        $this->assertFalse(Schema::hasTable('case_category'));
    }
}
