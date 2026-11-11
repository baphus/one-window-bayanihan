<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CaseOwnedIntakeTest extends TestCase
{
    use RefreshDatabase;

    public function test_intake_round_trips_encrypted_fields_without_generic_payload_or_audit_disclosure(): void
    {
        $case = CaseFile::factory()->withIntake()->create([
            'nok_contact_number' => '09179876543',
            'nok_email' => 'relative@example.test',
            'staff_summary' => 'Private staff assessment',
            'consent_evidence' => 'Signed paper retained privately',
            'confirmed_assessments' => ['OTHER', 'LEGAL_ASSISTANCE'],
            'confirmed_assessment_other' => 'Private explanation',
        ])->fresh();

        $this->assertSame('1990-06-15', $case->date_of_birth->toDateString());
        $this->assertSame(['OTHER', 'LEGAL_ASSISTANCE'], $case->confirmed_assessments);
        foreach ([
            'address_street' => '123 Example Street',
            'employment_employer_name' => 'Example Employer',
            'employment_position' => 'Engineer',
            'employment_country' => 'Japan',
            'employment_last_position' => 'Engineer',
            'nok_contact_number' => '09179876543',
            'nok_email' => 'relative@example.test',
            'staff_summary' => 'Private staff assessment',
            'consent_evidence' => 'Signed paper retained privately',
            'confirmed_assessment_other' => 'Private explanation',
        ] as $field => $plain) {
            $this->assertSame($plain, $case->$field);
            $this->assertNotSame($plain, DB::table('cases')->where('id', $case->id)->value($field));
        }
        $this->assertNotSame('1990-06-15', $case->getRawOriginal('date_of_birth'));

        $payload = $case->toArray();
        $audit = AuditLog::where('entity_id', $case->id)->sole();
        foreach (CaseFile::INTAKE_FIELDS as $field) {
            $this->assertArrayNotHasKey($field, $payload);
            $this->assertArrayNotHasKey($field, $audit->new_value);
        }
        $this->assertSame('OFW', $payload['client_type']);
        $this->assertSame($case->case_number, $audit->new_value['case_number']);
        $this->assertArrayNotHasKey('employment_last_country', $case->getAttributes());
        $this->assertArrayNotHasKey('employment_start_date', $case->getAttributes());
        $this->assertArrayNotHasKey('employment_end_date', $case->getAttributes());

        $case->update(['staff_summary' => 'Changed private assessment']);

        $this->assertSame(1, AuditLog::where('entity_id', $case->id)->count());
    }

    #[DataProvider('birthdays')]
    public function test_age_uses_intake_date_not_current_date(string $dob, string $intake, int $age): void
    {
        $this->travelTo('2040-12-31');
        $case = CaseFile::factory()->withIntake()->make(['date_of_birth' => $dob, 'intake_date' => $intake]);

        $this->assertSame($age, $case->age_at_intake);
    }

    public static function birthdays(): array
    {
        return [
            'day before birthday' => ['1990-06-15', '2026-06-14', 35],
            'birthday' => ['1990-06-15', '2026-06-15', 36],
            'day after birthday' => ['1990-06-15', '2026-06-16', 36],
            'leap birthday before anniversary' => ['2000-02-29', '2024-02-28', 23],
            'leap birthday' => ['2000-02-29', '2024-02-29', 24],
        ];
    }

    public function test_missing_dates_have_no_age_and_impossible_dates_cannot_be_saved(): void
    {
        $this->assertNull((new CaseFile)->age_at_intake);
        $case = CaseFile::factory()->withIntake()->make(['date_of_birth' => '2026-11-11']);

        $this->expectException(\InvalidArgumentException::class);
        $case->save();
    }

    public function test_schema_has_no_duplicate_employment_country_or_period_columns(): void
    {
        foreach (['employment_last_country', 'employment_start_date', 'employment_end_date'] as $column) {
            $this->assertFalse(Schema::hasColumn('cases', $column));
        }
        $this->assertTrue(Schema::hasColumn('cases', 'employment_country'));
        $this->assertTrue(Schema::hasColumn('cases', 'employment_date_of_arrival'));
    }

    public function test_public_owner_is_nullable_and_separate_from_creator(): void
    {
        $creator = User::factory()->create();
        $owner = User::factory()->create();
        $staffCase = CaseFile::factory()->withIntake()->create(['user_id' => $creator->id]);
        $publicCase = CaseFile::factory()->withIntake()->create(['user_id' => $creator->id, 'ofw_user_id' => $owner->id]);

        $this->assertNull($staffCase->fresh()->ofw_user_id);
        $this->assertTrue($publicCase->ofwUser->is($owner));
        $this->assertTrue($publicCase->user->is($creator));
    }

    public function test_persisted_case_classification_is_the_source_of_truth(): void
    {
        $ofw = CaseFile::factory()->withIntake()->create(['client_type' => 'OFW']);
        $nextOfKin = CaseFile::factory()->withIntake()->create(['client_type' => 'NEXT_OF_KIN']);

        $this->assertSame('OFW', $ofw->fresh()->client_type);
        $this->assertSame('NEXT_OF_KIN', $nextOfKin->fresh()->client_type);
        $this->assertSame([$ofw->id], CaseFile::where('client_type', 'OFW')->pluck('id')->all());
        $this->assertSame([$nextOfKin->id], CaseFile::where('client_type', 'NEXT_OF_KIN')->pluck('id')->all());
        $this->assertNull(CaseFile::factory()->make()->client_type);
    }

    #[DataProvider('invalidFields')]
    public function test_schema_rejects_unsupported_intake_choices(array $attributes): void
    {
        $this->expectException(QueryException::class);

        CaseFile::factory()->withIntake()->create($attributes);
    }

    public static function invalidFields(): array
    {
        return [
            'client type' => [['client_type' => 'UNKNOWN']],
            'obsolete client identifier' => [['client_id' => '00000000-0000-0000-0000-000000000000']],
            'sex' => [['sex' => 'UNKNOWN']],
            'civil status' => [['civil_status' => 'UNKNOWN']],
            'civil other explanation' => [['civil_status' => 'OTHER']],
            'deployment' => [['deployment_mode' => 'UNKNOWN']],
            'employment' => [['employment_status' => 'UNKNOWN']],
            'proposed code' => [['proposed_assessments' => ['UNKNOWN']]],
            'confirmed code' => [['confirmed_assessments' => ['UNKNOWN']]],
            'assessment object' => [['confirmed_assessments' => ['nested' => 'LEGAL_ASSISTANCE']]],
            'proposed other explanation' => [['proposed_assessments' => ['OTHER']]],
            'confirmed other explanation' => [['confirmed_assessments' => ['OTHER']]],
        ];
    }
}
