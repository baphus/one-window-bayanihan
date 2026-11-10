<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\CaseCategory;
use App\Models\Referral;
use App\Models\User;
use Database\Seeders\AgencySeeder;
use Database\Seeders\CaseCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReferralClassificationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_referrals_keep_independent_encrypted_classification_without_plaintext_audit_values(): void
    {
        $first = Referral::factory()->create([
            'category_id' => CaseCategory::factory(),
            'concern' => 'Unpaid wages and private details',
            'override_reason' => 'Private reason for another agency',
        ]);
        $second = Referral::factory()->create([
            'case_id' => $first->case_id,
            'category_id' => CaseCategory::factory(),
            'concern' => 'Skills training',
        ]);

        $first->update(['concern' => 'Corrected private concern']);

        $this->assertNotEquals($first->category_id, $second->category_id);
        $this->assertSame('Skills training', $second->fresh()->concern);
        $this->assertTrue($first->category->referrals->contains($first));
        $this->assertSame('Corrected private concern', $first->fresh()->concern);
        $this->assertSame('Private reason for another agency', $first->fresh()->override_reason);
        $stored = DB::table('referrals')->where('id', $first->id)->first();
        $this->assertNotSame($first->concern, $stored->concern);
        $this->assertNotSame($first->override_reason, $stored->override_reason);
        $auditValues = DB::table('audit_logs')->where('entity_id', $first->id)->get(['old_value', 'new_value'])->toJson();
        $this->assertStringNotContainsString('concern', $auditValues);
        $this->assertStringNotContainsString('override_reason', $auditValues);
    }

    public function test_approved_category_seeds_map_to_existing_lci_and_both_cebu_governments_idempotently(): void
    {
        $this->seed(AgencySeeder::class);
        $this->seed(CaseCategorySeeder::class);
        $this->seed(CaseCategorySeeder::class);

        $expected = [
            'LEGAL & RIGHTS ADVOCACY' => ['law-center-inc'],
            'WELFARE & REPATRIATION' => ['province-cebu', 'city-cebu', 'owwa', 'dswd', 'dole', 'tesda'],
            'LIVELIHOOD & EMPLOYMENT' => ['province-cebu', 'city-cebu', 'dole', 'dswd', 'owwa', 'tesda'],
            'SOCIAL & MEDICAL SERVICES' => ['dswd', 'doh'],
            'SKILLS UPSKILLING' => ['tesda', 'province-cebu', 'city-cebu', 'dole'],
        ];

        $this->assertSame(5, CaseCategory::count());
        foreach ($expected as $name => $slugs) {
            $category = CaseCategory::where('name', $name)->firstOrFail();
            $this->assertEqualsCanonicalizing($slugs, $category->suggestedAgencies->pluck('slug')->all());
            $this->assertNull($category->description);
        }
        $this->assertSame('LCI', CaseCategory::where('name', 'LEGAL & RIGHTS ADVOCACY')->firstOrFail()->suggestedAgencies->sole()->short);
    }

    public function test_seed_fails_without_approved_agencies_instead_of_creating_guessed_mappings(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Seed approved agencies before referral categories: LEGAL & RIGHTS ADVOCACY');

        $this->seed(CaseCategorySeeder::class);
    }

    public function test_admin_can_set_preserve_and_clear_suggested_agencies(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $agency = Agency::factory()->create(['is_active' => true]);
        $this->actingAsWithMfa($admin)->post(route('admin.case-categories.store'), [
            'name' => 'Managed category',
            'suggested_agency_ids' => [$agency->id],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $category = CaseCategory::where('name', 'Managed category')->firstOrFail();
        $this->assertTrue($category->suggestedAgencies->contains($agency));

        $this->patch(route('admin.case-categories.update', $category->id), [
            'name' => 'Renamed category',
        ])->assertSessionHasNoErrors();
        $this->assertTrue($category->fresh()->suggestedAgencies->contains($agency));

        $this->patch(route('admin.case-categories.update', $category->id), [
            'name' => 'Renamed category',
            'suggested_agency_ids' => [],
        ])->assertSessionHasNoErrors();
        $this->assertCount(0, $category->fresh()->suggestedAgencies);
    }

    public function test_admin_cannot_map_inactive_deleted_unknown_or_duplicate_agencies(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $active = Agency::factory()->create(['is_active' => true]);
        $inactive = Agency::factory()->create(['is_active' => false]);
        $deleted = Agency::factory()->create(['is_active' => true]);
        $deleted->delete();
        $this->actingAsWithMfa($admin);

        foreach ([[$inactive->id], [$deleted->id], [(string) Str::uuid()], [$active->id, $active->id]] as $ids) {
            $this->post(route('admin.case-categories.store'), [
                'name' => 'Invalid category',
                'suggested_agency_ids' => $ids,
            ])->assertSessionHasErrors('suggested_agency_ids.0');
        }
        $this->assertDatabaseMissing('case_categories', ['name' => 'Invalid category']);
    }

    public function test_non_admin_cannot_change_suggestions(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $category = CaseCategory::factory()->create();
        $agency = Agency::factory()->create();

        $this->actingAsWithMfa($manager)->patch(route('admin.case-categories.update', $category->id), [
            'name' => $category->name,
            'suggested_agency_ids' => [$agency->id],
        ])->assertForbidden();
        $this->assertCount(0, $category->fresh()->suggestedAgencies);
    }

    public function test_category_used_by_referral_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $category = CaseCategory::factory()->create();
        Referral::factory()->create(['category_id' => $category->id, 'concern' => 'Legal assistance']);

        $this->actingAsWithMfa($admin)->delete(route('admin.case-categories.destroy', $category->id))
            ->assertSessionHas('error');

        $this->assertFalse($category->fresh()->is_deleted);
    }
}
