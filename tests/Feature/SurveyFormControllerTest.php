<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Agency;
use App\Models\Referral;
use App\Models\SurveyForm;
use App\Models\SurveyInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyFormControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_agency_and_unassigned_agency_users_are_forbidden(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'CASE_MANAGER']))
            ->get(route('survey.forms.index'))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'AGENCY', 'agcy_id' => null]))
            ->get(route('survey.forms.index'))->assertForbidden();
    }

    public function test_index_is_scoped_to_the_authenticated_agency(): void
    {
        [$user, $agency] = $this->agencyUser();
        SurveyForm::create(['agency_id' => $agency->id, 'title' => 'Mine']);
        SurveyForm::create(['agency_id' => Agency::factory()->create()->id, 'title' => 'Other']);

        $this->actingAs($user)->get(route('survey.forms.index'))
            ->assertOk()->assertInertia(fn ($page) => $page->has('forms', 1));
    }

    public function test_store_validation_has_no_side_effects(): void
    {
        [$user] = $this->agencyUser();
        $this->actingAs($user)->post(route('survey.forms.store'), [])->assertInvalid(['title', 'questions']);
        $this->assertSame(0, SurveyForm::count());
    }

    public function test_create_persists_questions_options_and_order(): void
    {
        [$user, $agency] = $this->agencyUser();
        $this->actingAs($user)->post(route('survey.forms.store'), $this->formPayload())->assertRedirect();
        $form = SurveyForm::where('agency_id', $agency->id)->firstOrFail();
        $this->assertSame(['Second', 'First'], $form->questions()->orderBy('order')->pluck('label')->all());
        $this->assertSame(['Yes', 'No'], $form->questions()->where('label', 'Second')->first()->options);
    }

    public function test_form_builder_payload_with_every_question_type_saves_and_reaches_final_inertia_page(): void
    {
        [$user, $agency] = $this->agencyUser();
        $payload = [
            'title' => 'All question types',
            'description' => 'Created from the Agency form builder.',
            'questions' => [
                ['type' => 'likert', 'label' => 'Likert question', 'options' => [], 'is_required' => true, 'order' => 0],
                ['type' => 'text', 'label' => 'Text question', 'options' => [], 'is_required' => false, 'order' => 1],
                ['type' => 'radio', 'label' => 'Radio question', 'options' => ['Yes', 'No'], 'is_required' => true, 'order' => 2],
                ['type' => 'checkbox', 'label' => 'Checkbox question', 'options' => ['One', 'Two'], 'is_required' => false, 'order' => 3],
                ['type' => 'rating', 'label' => 'Rating question', 'options' => [], 'is_required' => true, 'order' => 4],
            ],
        ];

        $response = $this->actingAs($user)
            ->withHeader('X-Inertia', 'true')
            ->post(route('survey.forms.store'), $payload);

        $response->assertRedirect(route('survey.forms.index'));

        $form = SurveyForm::where('agency_id', $agency->id)->firstOrFail();
        $this->assertSame(
            ['likert', 'text', 'radio', 'checkbox', 'rating'],
            $form->questions()->orderBy('order')->pluck('type')->all(),
        );
        $this->assertSame(['Yes', 'No'], $form->questions()->where('type', 'radio')->first()->options);
        $this->assertFalse($form->questions()->where('type', 'checkbox')->first()->is_required);

        $this->actingAs($user)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
            ])
            ->get(route('survey.forms.index'))
            ->assertOk()
            ->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', 'Survey/FormIndex')
            ->assertJsonCount(1, 'props.forms')
            ->assertJsonPath('props.flash.success', 'Survey form created successfully.');
    }

    public function test_update_replaces_form_metadata_and_questions(): void
    {
        [$user, $agency] = $this->agencyUser();
        $form = SurveyForm::create(['agency_id' => $agency->id, 'title' => 'Old']);
        $form->questions()->create(['type' => 'text', 'label' => 'Old question', 'order' => 0]);
        $this->actingAs($user)->patch(route('survey.forms.update', $form), [
            'title' => 'New', 'description' => 'Updated', 'questions' => [[
                'type' => 'radio', 'label' => 'New question', 'options' => ['A', 'B'], 'order' => 3,
            ]],
        ])->assertRedirect();
        $this->assertSame('New', $form->fresh()->title);
        $this->assertSame('New question', $form->fresh()->questions->first()->label);
    }

    public function test_form_without_invitations_can_be_deleted(): void
    {
        [$user, $agency] = $this->agencyUser();
        $form = SurveyForm::create(['agency_id' => $agency->id, 'title' => 'Delete me']);
        $this->actingAs($user)->delete(route('survey.forms.destroy', $form))->assertRedirect();
        $this->assertDatabaseMissing('survey_forms', ['id' => $form->id]);
    }

    public function test_cross_agency_mutations_are_forbidden_without_side_effects(): void
    {
        [$user] = $this->agencyUser();
        $form = SurveyForm::create(['agency_id' => Agency::factory()->create()->id, 'title' => 'Other']);

        $this->actingAs($user)->get(route('survey.forms.edit', $form))->assertForbidden();
        $this->actingAs($user)->patch(route('survey.forms.update', $form), ['title' => 'changed'])->assertForbidden();
        $this->actingAs($user)->delete(route('survey.forms.destroy', $form))->assertForbidden();
        $this->actingAs($user)->patch(route('survey.forms.activate', $form))->assertForbidden();
        $this->assertSame('Other', $form->fresh()->title);
    }

    public function test_activation_leaves_exactly_one_active_form(): void
    {
        [$user, $agency] = $this->agencyUser();
        $old = SurveyForm::create(['agency_id' => $agency->id, 'title' => 'Old', 'is_active' => true]);
        $new = SurveyForm::create(['agency_id' => $agency->id, 'title' => 'New']);

        $this->actingAs($user)->patch(route('survey.forms.activate', $new))->assertRedirect();
        $this->assertSame(1, SurveyForm::where('agency_id', $agency->id)->where('is_active', true)->count());
        $this->assertFalse($old->fresh()->is_active);
        $this->assertTrue($new->fresh()->is_active);
    }

    public function test_form_with_an_invitation_is_immutable_and_non_deletable(): void
    {
        [$user, $agency] = $this->agencyUser();
        $form = SurveyForm::create(['agency_id' => $agency->id, 'title' => 'Locked']);
        $referral = Referral::factory()->create(['agcy_id' => $agency->id]);
        SurveyInvitation::create($this->invitationAttributes($form, $agency, $referral));

        $this->actingAs($user)->patch(route('survey.forms.update', $form), ['title' => 'Changed'])->assertStatus(409);
        $this->actingAs($user)->delete(route('survey.forms.destroy', $form))->assertStatus(409);
        $this->assertSame('Locked', $form->fresh()->title);
    }

    public function test_immutable_form_edit_get_is_conflict_but_activation_is_allowed(): void
    {
        [$user, $agency] = $this->agencyUser();
        $form = SurveyForm::create(['agency_id' => $agency->id, 'title' => 'Locked']);
        $referral = Referral::factory()->create(['agcy_id' => $agency->id]);
        SurveyInvitation::create($this->invitationAttributes($form, $agency, $referral));
        $this->actingAs($user)->get(route('survey.forms.edit', $form))->assertStatus(409);
        $this->actingAs($user)->patch(route('survey.forms.activate', $form))->assertRedirect();
    }

    public function test_list_question_missing_type_is_rejected_without_writes(): void
    {
        [$user, $agency] = $this->agencyUser();
        $form = SurveyForm::create(['agency_id' => $agency->id, 'title' => 'Original']);
        $this->actingAs($user)->patch(route('survey.forms.update', $form), [
            'title' => 'Changed', 'questions' => [['label' => 'Missing type']],
        ])->assertInvalid(['questions.0.type']);
        $this->assertUnchanged($form, 'Original', 0);
    }

    public function test_associative_radio_options_are_rejected_without_writes(): void
    {
        [$user, $agency] = $this->agencyUser();
        $form = SurveyForm::create(['agency_id' => $agency->id, 'title' => 'Original']);
        $this->actingAs($user)->patch(route('survey.forms.update', $form), [
            'title' => 'Changed', 'questions' => [[
                'type' => 'radio', 'label' => 'Choices', 'options' => ['first' => 'Yes'],
            ]],
        ])->assertInvalid(['questions.0.options']);
        $this->assertUnchanged($form, 'Original', 0);
    }

    public function test_duplicate_options_are_rejected_without_writes(): void
    {
        [$user, $agency] = $this->agencyUser();
        $form = SurveyForm::create(['agency_id' => $agency->id, 'title' => 'Original']);
        $this->actingAs($user)->patch(route('survey.forms.update', $form), [
            'title' => 'Changed', 'questions' => [[
                'type' => 'checkbox', 'label' => 'Choices', 'options' => ['Yes', 'Yes'],
            ]],
        ])->assertInvalid(['questions.0.options.1']);
        $this->assertUnchanged($form, 'Original', 0);
    }

    public function test_unauthenticated_mutation_redirects_to_login(): void
    {
        $this->post(route('survey.forms.store'), [])->assertRedirect(route('login'));
    }

    public function test_missing_agency_mutation_is_forbidden(): void
    {
        $user = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => null]);
        $this->actingAs($user)->post(route('survey.forms.store'), [])->assertForbidden();
    }

    private function agencyUser(): array
    {
        $agency = Agency::factory()->create();

        return [User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id]), $agency];
    }

    private function assertUnchanged(SurveyForm $form, string $title, int $questionCount): void
    {
        $fresh = $form->fresh();
        $this->assertSame($title, $fresh->title);
        $this->assertSame($questionCount, $fresh->questions()->count());
    }

    private function formPayload(): array
    {
        return ['title' => 'Feedback', 'questions' => [
            ['type' => 'text', 'label' => 'First', 'order' => 2],
            ['type' => 'radio', 'label' => 'Second', 'options' => ['Yes', 'No'], 'order' => 1],
        ]];
    }

    private function invitationAttributes(SurveyForm $form, Agency $agency, Referral $referral): array
    {
        return [
            'survey_form_id' => $form->id,
            'case_id' => $referral->case_id,
            'agency_id' => $agency->id,
            'referral_id' => $referral->id,
            'client_name' => 'Client',
            'client_email' => 'client@example.test',
            'service_name' => 'Service',
            'token' => str_repeat('b', 64),
            'expires_at' => now()->addDay(),
        ];
    }
}
