<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Referral;
use App\Models\SurveyForm;
use App\Models\SurveyInvitation;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Services\SurveyInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SurveyResponseControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_agency_scope_has_exact_stats_and_submitted_only_rows(): void
    {
        $agency = Agency::factory()->create();
        $submitted = $this->makeInvitation($agency, true);
        $this->makeInvitation($agency, false);
        $this->makeInvitation(Agency::factory()->create(), true);
        $user = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id]);

        $this->actingAs($user)->get(route('survey.responses.index', ['agency_id' => 'not-a-uuid']))
            ->assertInertia(fn (Assert $page) => $this->assertIndex($page, [$submitted->id], 2, 1, 50));
    }

    public function test_case_manager_sees_all_survey_responses(): void
    {
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $mine = $this->makeInvitation(Agency::factory()->create(), true, $manager);
        $ownUnsubmitted = $this->makeInvitation(Agency::factory()->create(), false, $manager);
        $other = $this->makeInvitation(Agency::factory()->create(), true, User::factory()->create(['role' => 'CASE_MANAGER']));

        $this->actingAs($manager)->get(route('survey.responses.index'))
            ->assertInertia(fn (Assert $page) => $this->assertIndex($page, [$other->id, $mine->id], 3, 2, 66.7));
        $this->actingAs($manager)->get(route('survey.responses.show', $mine))
            ->assertInertia(fn (Assert $page) => $this->assertDetail($page));
        $this->actingAs($manager)->get(route('survey.responses.show', $other))
            ->assertInertia(fn (Assert $page) => $this->assertDetail($page));
        $this->assertNotSame($mine->id, $ownUnsubmitted->id);
    }

    public function test_admin_sees_all_or_a_valid_agency_filter_with_exact_stats(): void
    {
        $firstAgency = Agency::factory()->create();
        $firstSubmitted = $this->makeInvitation($firstAgency, true);
        $this->makeInvitation($firstAgency, false);
        $secondSubmitted = $this->makeInvitation(Agency::factory()->create(), true);
        $admin = User::factory()->create(['role' => 'ADMIN']);

        $this->actingAs($admin)->get(route('survey.responses.index'))
            ->assertInertia(fn (Assert $page) => $this->assertIndex($page, [$secondSubmitted->id, $firstSubmitted->id], 3, 2, 66.7));
        $this->actingAs($admin)->get(route('survey.responses.index', ['agency_id' => $firstAgency->id]))
            ->assertInertia(fn (Assert $page) => $this->assertIndex($page, [$firstSubmitted->id], 2, 1, 50));
        $this->actingAs($admin)->get(route('survey.responses.show', [$firstSubmitted, 'agency_id' => $firstAgency->id]))
            ->assertInertia(fn (Assert $page) => $this->assertDetail($page));
        $this->actingAs($admin)->get(route('survey.responses.show', [$secondSubmitted, 'agency_id' => $firstAgency->id]))->assertForbidden();
    }

    public function test_admin_rejects_malformed_or_nonexistent_agency_filters(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        foreach (['not-a-uuid', '00000000-0000-0000-0000-000000000000'] as $agencyId) {
            $this->actingAs($admin)->get(route('survey.responses.index', ['agency_id' => $agencyId]))
                ->assertSessionHasErrors('agency_id');
        }
    }

    public function test_per_page_accepts_one_and_one_hundred_and_falls_back_to_fifteen(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        for ($i = 0; $i < 16; $i++) {
            $this->makeInvitation(Agency::factory()->create(), true);
        }

        foreach ([0, -1, 'abc', 101] as $perPage) {
            $this->actingAs($admin)->get(route('survey.responses.index', ['per_page' => $perPage]))
                ->assertInertia(fn (Assert $page) => $page->where('filters.per_page', 15)->where('invitations.per_page', 15));
        }
        $this->actingAs($admin)->get(route('survey.responses.index', ['per_page' => 1]))
            ->assertInertia(fn (Assert $page) => $page->where('filters.per_page', 1)->where('invitations.per_page', 1)->has('invitations.data', 1));
        $this->actingAs($admin)->get(route('survey.responses.index', ['per_page' => 100]))
            ->assertInertia(fn (Assert $page) => $page->where('filters.per_page', 100)->where('invitations.per_page', 100)->has('invitations.data', 16));
    }

    public function test_detail_denies_unsubmitted_and_cross_agency_access_and_unauthenticated_redirects(): void
    {
        $agency = Agency::factory()->create();
        $submitted = $this->makeInvitation($agency, true);
        $unsubmitted = $this->makeInvitation($agency, false);
        $other = $this->makeInvitation(Agency::factory()->create(), true);
        $user = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id]);

        $this->get(route('survey.responses.index'))->assertRedirect();
        $this->actingAs($user)->get(route('survey.responses.show', $unsubmitted))->assertForbidden();
        $this->actingAs($user)->get(route('survey.responses.show', $other))->assertForbidden();
        $this->actingAs($user)->get(route('survey.responses.show', $submitted))
            ->assertInertia(fn (Assert $page) => $this->assertDetail($page));
        $this->actingAs(User::factory()->create(['role' => 'OTHER']))
            ->get(route('survey.responses.index'))->assertForbidden();
    }

    private function assertIndex(Assert $page, array $ids, int $sent, int $submitted, mixed $rate): Assert
    {
        return $page->component('Survey/ResponseIndex')
            ->where('stats.total_sent', $sent)
            ->where('stats.total_submitted', $submitted)
            ->where('stats.response_rate', $rate)
            ->where('invitations.data', function ($rows) use ($ids): bool {
                $actual = $rows->pluck('id')->all();
                sort($actual);
                $expected = $ids;
                sort($expected);

                return $actual === $expected;
            })
            ->has('invitations.data.0', fn (Assert $row) => $row
                ->hasAll(['id', 'client_name', 'service_name', 'submitted_at', 'survey_form', 'agency'])
                ->missing('token')->missing('token_hash')->missing('client_email')->missing('caseFile')->missing('case_file')
                ->has('survey_form', fn (Assert $form) => $form->has('title')->missing('id'))
                ->has('agency', fn (Assert $agency) => $agency->has('name')->missing('id'))
            );
    }

    private function assertDetail(Assert $page): Assert
    {
        return $page->component('Survey/ResponseShow')->has('invitation', fn (Assert $invitation) => $invitation
            ->hasAll(['client_name', 'service_name', 'submitted_at', 'survey_form', 'agency', 'responses'])
            ->missing('id')->missing('token')->missing('token_hash')->missing('client_email')->missing('caseFile')->missing('case_file')
            ->has('survey_form', fn (Assert $form) => $form->has('title')->missing('id'))
            ->has('agency', fn (Assert $agency) => $agency->has('name')->missing('id'))
            ->has('responses.0', fn (Assert $response) => $response
                ->hasAll(['id', 'answer', 'selected_options', 'question'])
                ->has('question', fn (Assert $question) => $question
                    ->hasAll(['label', 'type', 'order', 'is_required'])->missing('id'))
            )
        );
    }

    private function makeInvitation(Agency $agency, bool $submitted, ?User $owner = null): SurveyInvitation
    {
        $client = Client::factory()->create(['email' => 'private@example.test']);
        $owner ??= User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = CaseFile::factory()->create(['client_id' => $client->id, 'user_id' => $owner->id]);
        $referral = Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agency->id]);
        $form = SurveyForm::firstOrCreate(
            ['agency_id' => $agency->id],
            ['title' => 'Feedback', 'is_active' => true],
        );
        $question = $form->questions()->first() ?? $form->questions()->create(['type' => 'text', 'label' => 'Answer', 'order' => 0]);
        $created = app(SurveyInvitationService::class)->createInvitation(
            referralId: $referral->id, clientName: 'Private Client', clientEmail: $client->email,
            serviceName: 'Service', surveyFormId: $form->id,
        );
        $invitation = $created->invitation;
        if ($submitted) {
            $invitation->update(['submitted_at' => now()]);
            SurveyResponse::create(['survey_invitation_id' => $invitation->id, 'survey_question_id' => $question->id, 'answer' => 'Yes']);
        }

        return $invitation->fresh(['surveyForm', 'agency', 'caseFile']);
    }
}
