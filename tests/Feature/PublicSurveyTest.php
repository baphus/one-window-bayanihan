<?php

namespace Tests\Feature;

use App\Events\ReferralCompleted;
use App\Listeners\SendSurveyRequest;
use App\Mail\SurveyRequestMail;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Referral;
use App\Models\SurveyForm;
use App\Models\SurveyInvitation;
use App\Models\SurveyResponse;
use App\Models\SystemSetting;
use App\Services\SurveyInvitationService;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicSurveyTest extends TestCase
{
    use RefreshDatabase;

    public function test_hash_and_legacy_links_render_only_allowlisted_public_props(): void
    {
        [$invitation, $token] = $this->invitation();
        $response = $this->get(route('survey.public.show', $token));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Survey/PublicForm')
            ->has('invitation', fn (Assert $props) => $props
                ->where('id', $invitation->id)
                ->where('client_name', 'Client Name')
                ->where('service_name', 'Service')
                ->missing('token')->missing('token_hash')->missing('client_email')->missing('case_id'))
            ->has('surveyForm', fn (Assert $props) => $props->hasAll(['id', 'title', 'description'])->missing('agency_id'))
            ->has('questions', 1, fn (Assert $props) => $props->hasAll(['id', 'type', 'label', 'options', 'is_required', 'order'])));

        $legacy = $this->invitation()[0];
        $legacyToken = 'legacy-token';
        $legacy->forceFill(['token' => $legacyToken, 'token_hash' => null])->save();
        $this->get(route('survey.public.show', $legacyToken))->assertInertia(
            fn (Assert $page) => $page->component('Survey/PublicForm')
        );
    }

    public function test_invalid_expired_submitted_and_missing_form_links_do_not_write(): void
    {
        [$invitation, $token] = $this->invitation();
        $invitation->update(['expires_at' => now()->subSecond()]);
        $this->get(route('survey.public.show', $token))
            ->assertInertia(fn (Assert $page) => $page->component('Survey/PublicFormError'));
        $this->get(route('survey.public.show', 'not-a-token'))
            ->assertInertia(fn (Assert $page) => $page->component('Survey/PublicFormError'));

        [$submitted, $submittedToken] = $this->invitation();
        $submitted->update(['submitted_at' => now()]);
        $this->get(route('survey.public.show', $submittedToken))->assertInertia(
            fn (Assert $page) => $page->component('Survey/PublicFormError')
        );

        [$orphan, $orphanToken] = $this->invitation();
        $orphan->update(['survey_form_id' => null]);
        $this->get(route('survey.public.show', $orphanToken))->assertInertia(
            fn (Assert $page) => $page->component('Survey/PublicFormError')
        );
        $this->assertSame(0, SurveyResponse::count());
    }

    public function test_valid_submission_is_atomic_and_sequential_replay_is_rejected(): void
    {
        [$invitation, $token] = $this->invitation();
        $question = $invitation->surveyForm->questions->first();
        $payload = ['answers' => [['question_id' => $question->id, 'answer' => 'Great']]];

        $this->post(route('survey.public.submit', $token), $payload)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Survey/PublicFormSubmitted'));
        $this->assertDatabaseHas('survey_responses', ['survey_invitation_id' => $invitation->id]);
        $this->assertNotNull($invitation->fresh()->submitted_at);
        $this->post(route('survey.public.submit', $token), $payload)
            ->assertInertia(fn (Assert $page) => $page->component('Survey/PublicFormError'));
        $this->assertSame(1, SurveyResponse::where('survey_invitation_id', $invitation->id)->count());
    }

    public function test_existing_invitation_submits_after_its_form_is_deactivated(): void
    {
        [$invitation, $token] = $this->invitation();
        $question = $invitation->surveyForm->questions->first();
        $invitation->surveyForm->update(['is_active' => false]);

        $this->post(route('survey.public.submit', $token), [
            'answers' => [['question_id' => $question->id, 'answer' => 'Still valid']],
        ])->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Survey/PublicFormSubmitted'));

        $this->assertNotNull($invitation->fresh()->submitted_at);
    }

    public function test_mixed_question_submission_persists_exact_answer_values(): void
    {
        [$invitation, $token] = $this->invitation([
            ['type' => 'text'],
            ['type' => 'rating'],
            ['type' => 'likert'],
            ['type' => 'radio', 'options' => ['Good', 'Bad']],
            ['type' => 'checkbox', 'options' => ['Fast', 'Friendly']],
        ]);
        $questions = $invitation->surveyForm->questions;
        $answers = [
            ['question_id' => $questions[0]->id, 'answer' => 'Helpful'],
            ['question_id' => $questions[1]->id, 'answer' => '5'],
            ['question_id' => $questions[2]->id, 'answer' => '2'],
            ['question_id' => $questions[3]->id, 'answer' => 'Good'],
            ['question_id' => $questions[4]->id, 'selected_options' => ['Fast', 'Friendly']],
        ];

        $this->post(route('survey.public.submit', $token), ['answers' => $answers])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Survey/PublicFormSubmitted'));
        $persisted = SurveyResponse::where('survey_invitation_id', $invitation->id)
            ->get(['survey_question_id', 'answer', 'selected_options'])
            ->keyBy('survey_question_id');
        $this->assertSame('Helpful', $persisted[$questions[0]->id]->answer);
        $this->assertSame('5', $persisted[$questions[1]->id]->answer);
        $this->assertSame('2', $persisted[$questions[2]->id]->answer);
        $this->assertSame('Good', $persisted[$questions[3]->id]->answer);
        $this->assertNull($persisted[$questions[4]->id]->answer);
        $this->assertSame(['Fast', 'Friendly'], $persisted[$questions[4]->id]->selected_options);
    }

    public function test_rejected_valid_question_payloads_leave_no_responses_or_submission(): void
    {
        [$invitation, $token] = $this->invitation([
            ['type' => 'text', 'is_required' => true],
            ['type' => 'radio', 'options' => ['A', 'B'], 'is_required' => true],
            ['type' => 'checkbox', 'options' => ['X', 'Y'], 'is_required' => false],
        ]);
        $questions = $invitation->surveyForm->questions;
        $cases = [
            'duplicate valid question' => [['answers' => [
                ['question_id' => $questions[0]->id, 'answer' => 'one'],
                ['question_id' => $questions[0]->id, 'answer' => 'two'],
                ['question_id' => $questions[1]->id, 'answer' => 'A'],
            ]], 200],
            'omitted valid required question' => [['answers' => [['question_id' => $questions[0]->id, 'answer' => 'only one']]], 200],
            'empty valid required answer' => [['answers' => [
                ['question_id' => $questions[0]->id, 'answer' => ''],
                ['question_id' => $questions[1]->id, 'answer' => 'A'],
            ]], 200],
            'invalid configured checkbox option' => [['answers' => [
                ['question_id' => $questions[0]->id, 'answer' => 'text'],
                ['question_id' => $questions[1]->id, 'answer' => 'A'],
                ['question_id' => $questions[2]->id, 'selected_options' => ['Z']],
            ]], 200],
            'associative selected options' => [['answers' => [
                ['question_id' => $questions[0]->id, 'answer' => 'text'],
                ['question_id' => $questions[1]->id, 'answer' => 'A'],
                ['question_id' => $questions[2]->id, 'selected_options' => ['first' => 'X']],
            ]], 302],
        ];

        foreach ($cases as $name => [$payload, $expectedStatus]) {
            $response = $this->post(route('survey.public.submit', $token), $payload);
            $this->assertSame($expectedStatus, $response->getStatusCode(), $name);
            if ($expectedStatus === 200) {
                $response->assertInertia(fn (Assert $page) => $page->component('Survey/PublicFormError'));
            } else {
                $response->assertRedirect();
            }
            $this->assertSame(0, SurveyResponse::where('survey_invitation_id', $invitation->id)->count(), $name);
            $this->assertNull($invitation->fresh()->submitted_at, $name);
        }
    }

    public function test_required_answer_types_options_and_cross_form_questions_are_rejected_without_writes(): void
    {
        [$invitation, $token] = $this->invitation([
            ['type' => 'rating', 'is_required' => false],
            ['type' => 'likert', 'is_required' => false],
            ['type' => 'radio', 'options' => ['A'], 'is_required' => false],
            ['type' => 'checkbox', 'options' => ['X', 'Y'], 'is_required' => false],
        ]);
        $q = $invitation->surveyForm->questions;
        $foreign = SurveyForm::create(['agency_id' => $invitation->agency_id, 'title' => 'Other']);
        $foreignQuestion = $foreign->questions()->create(['type' => 'text', 'label' => 'foreign']);
        $this->assertRejectedSubmission($invitation, $token, [['question_id' => $q[0]->id, 'answer' => '6']]);
        $this->assertRejectedSubmission($invitation, $token, [['question_id' => $q[1]->id, 'answer' => '0']]);
        $this->assertRejectedSubmission($invitation, $token, [['question_id' => $q[2]->id, 'answer' => 'B']]);
        $this->assertRejectedSubmission($invitation, $token, [['question_id' => $q[3]->id, 'selected_options' => ['X', 'X']]]);
        $this->assertRejectedSubmission($invitation, $token, [['question_id' => $q[0]->id, 'answer' => '1', 'selected_options' => ['X']]]);
        $this->assertRejectedSubmission($invitation, $token, [['question_id' => (string) Str::uuid(), 'answer' => 'x']]);
        try {
            app(SurveyInvitationService::class)->submitResponse($invitation, [['question_id' => $foreignQuestion->id, 'answer' => 'x']]);
            $this->fail('Expected a cross-form question to be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Invalid survey answers.', $exception->getMessage());
        }
        $this->assertSame(0, SurveyResponse::where('survey_invitation_id', $invitation->id)->count());
        $this->assertNull($invitation->fresh()->submitted_at);

        $response = $this->post(route('survey.public.submit', $token), [
            'answers' => [
                ['question_id' => $q[0]->id, 'answer' => '1'],
                ['question_id' => $q[1]->id, 'answer' => 'A'],
                ['question_id' => $q[3]->id, 'selected_options' => ['first' => 'X']],
            ],
        ]);
        $response->assertRedirect()->assertSessionHasErrors('answers.2.selected_options');
        $this->assertSame(0, SurveyResponse::where('survey_invitation_id', $invitation->id)->count());
    }

    public function test_optional_only_form_accepts_an_empty_answer_array(): void
    {
        [$invitation, $token] = $this->invitation([['type' => 'text', 'is_required' => false]]);
        $this->post(route('survey.public.submit', $token), ['answers' => []])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Survey/PublicFormSubmitted'));
        $this->assertNotNull($invitation->fresh()->submitted_at);
    }

    public function test_optional_only_form_accepts_an_empty_present_answer(): void
    {
        [$invitation, $token] = $this->invitation([['type' => 'text', 'is_required' => false]]);
        $question = $invitation->surveyForm->questions->first();
        $this->post(route('survey.public.submit', $token), [
            'answers' => [['question_id' => $question->id, 'answer' => '']],
        ])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Survey/PublicFormSubmitted'));
        $this->assertNotNull($invitation->fresh()->submitted_at);
    }

    public function test_service_selects_only_active_same_agency_form_and_hashes_new_token(): void
    {
        Carbon::setTestNow('2026-07-14 12:00:00');
        try {
            $referral = Referral::factory()->create();
            $form = SurveyForm::create(['agency_id' => $referral->agcy_id, 'title' => 'Active', 'is_active' => true]);
            $created = app(SurveyInvitationService::class)->createInvitation(
                referralId: $referral->id, clientName: 'Client', clientEmail: 'client@example.test', serviceName: 'Service', surveyFormId: $form->id,
            );
            $this->assertNotNull($created);
            $this->assertSame($referral->case_id, $created->invitation->case_id);
            $this->assertSame($referral->agcy_id, $created->invitation->agency_id);
            $this->assertSame($referral->id, $created->invitation->referral_id);
            $this->assertSame($form->id, $created->invitation->survey_form_id);
            $this->assertSame('Client', $created->invitation->client_name);
            $this->assertSame('client@example.test', $created->invitation->client_email);
            $this->assertSame('Service', $created->invitation->service_name);
            $this->assertNull($created->invitation->token);
            $this->assertSame(hash('sha256', $created->rawToken), $created->invitation->token_hash);
            $this->assertTrue($created->invitation->expires_at->equalTo(now()->addDays(30)));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_service_rejects_inactive_cross_agency_and_missing_forms(): void
    {
        $referral = Referral::factory()->create();
        $forms = [
            SurveyForm::create(['agency_id' => $referral->agcy_id, 'title' => 'Inactive']),
            SurveyForm::create(['agency_id' => Agency::factory()->create()->id, 'title' => 'Foreign', 'is_active' => true]),
        ];
        foreach ([...$forms, null] as $form) {
            try {
                app(SurveyInvitationService::class)->createInvitation(
                    referralId: $referral->id, clientName: 'Client', clientEmail: 'client@example.test', serviceName: 'Service', surveyFormId: $form?->id,
                );
                $this->fail('Expected invalid survey form to be rejected.');
            } catch (\RuntimeException) {
                $this->assertSame(0, SurveyInvitation::count());
            }
        }
    }

    public function test_delivery_skips_disabled_feedback_missing_email_and_missing_form(): void
    {
        Mail::fake();
        $referral = $this->deliveryReferral();
        SystemSetting::setValue('feedback_enabled', false);
        (new SendSurveyRequest)->handle(new ReferralCompleted($referral));
        $this->assertSame(0, SurveyInvitation::count());
        Mail::assertQueuedCount(0);
        SystemSetting::setValue('feedback_enabled', true);
        $referral->caseFile->client->update(['email' => null]);
        (new SendSurveyRequest)->handle(new ReferralCompleted($referral->fresh(['caseFile.client', 'agency'])));
        $this->assertSame(0, SurveyInvitation::count());
        Mail::assertQueuedCount(0);
        $referral->caseFile->client->update(['email' => 'recipient@example.test']);
        SurveyForm::where('agency_id', $referral->agcy_id)->update(['is_active' => false]);
        (new SendSurveyRequest)->handle(new ReferralCompleted($referral->fresh(['caseFile.client', 'agency'])));
        $this->assertSame(0, SurveyInvitation::count());
        Mail::assertQueuedCount(0);
    }

    public function test_delivery_queues_exactly_one_encrypted_mail_with_resolvable_raw_url(): void
    {
        Mail::fake();
        $referral = $this->deliveryReferral();
        $listener = new SendSurveyRequest;
        $listener->handle(new ReferralCompleted($referral));
        $listener->handle(new ReferralCompleted($referral->fresh(['caseFile.client', 'agency'])));
        $this->assertSame(1, SurveyInvitation::count());
        Mail::assertQueuedCount(1);
        $queuedMail = null;
        Mail::assertQueued(SurveyRequestMail::class, function (SurveyRequestMail $mail) use (&$queuedMail, $referral) {
            $queuedMail = $mail;

            return $mail->hasTo($referral->caseFile->client->email);
        });
        $this->assertInstanceOf(ShouldBeEncrypted::class, $queuedMail);
        $this->get(parse_url($queuedMail->survey_url, PHP_URL_PATH))
            ->assertInertia(fn (Assert $page) => $page->component('Survey/PublicForm'));
    }

    public function test_response_uniqueness_constraint_exists(): void
    {
        [$invitation] = $this->invitation();
        $question = $invitation->surveyForm->questions->first();
        SurveyResponse::create(['survey_invitation_id' => $invitation->id, 'survey_question_id' => $question->id, 'answer' => 'one']);
        $this->expectException(QueryException::class);
        SurveyResponse::create(['survey_invitation_id' => $invitation->id, 'survey_question_id' => $question->id, 'answer' => 'two']);
    }

    public function test_token_migration_backfills_a_plaintext_row_in_a_disposable_schema_state(): void
    {
        [$invitation] = $this->invitation();
        $migration = require base_path('database/migrations/2026_07_14_000002_hash_survey_invitation_tokens.php');
        $migration->down();

        $legacyToken = 'pre-migration-plaintext-token';
        DB::table('survey_invitations')->where('id', $invitation->id)->update([
            'token' => $legacyToken,
        ]);
        $this->assertFalse(Schema::hasColumn('survey_invitations', 'token_hash'));

        $migration->up();

        $this->assertSame(hash('sha256', $legacyToken), DB::table('survey_invitations')
            ->where('id', $invitation->id)->value('token_hash'));
    }

    public function test_listener_is_after_commit_and_mail_is_encrypted(): void
    {
        $this->assertInstanceOf(ShouldQueueAfterCommit::class, new SendSurveyRequest);
        $this->assertTrue(is_subclass_of(SurveyRequestMail::class, ShouldBeEncrypted::class));
    }

    private function assertRejectedSubmission(SurveyInvitation $invitation, string $token, array $answers): void
    {
        $response = $this->post(route('survey.public.submit', $token), ['answers' => $answers]);
        $this->assertSame(200, $response->getStatusCode(), json_encode($answers));
        $response
            ->assertInertia(fn (Assert $page) => $page->component('Survey/PublicFormError'));
        $this->assertSame(0, SurveyResponse::where('survey_invitation_id', $invitation->id)->count());
        $this->assertNull($invitation->fresh()->submitted_at);
    }

    private function invitation(array $questions = [['type' => 'text', 'is_required' => true]]): array
    {
        $referral = Referral::factory()->create();
        $client = Client::factory()->create(['email' => 'client@example.test']);
        $referral->caseFile->update(['client_id' => $client->id]);
        $form = SurveyForm::create(['agency_id' => $referral->agcy_id, 'title' => 'Feedback', 'is_active' => true]);
        foreach ($questions as $index => $question) {
            $form->questions()->create(array_merge(['label' => 'Question '.$index, 'order' => $index], $question));
        }
        $created = app(SurveyInvitationService::class)->createInvitation(
            referralId: $referral->id, clientName: 'Client Name', clientEmail: 'client@example.test', serviceName: 'Service', surveyFormId: $form->id,
        );

        return [$created->invitation->load('surveyForm.questions'), $created->rawToken];
    }

    private function deliveryReferral(): Referral
    {
        $client = Client::factory()->create(['email' => 'recipient@example.test']);
        $case = CaseFile::factory()->create(['client_id' => $client->id]);
        $agency = Agency::factory()->create();
        $referral = Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agency->id]);
        SurveyForm::create(['agency_id' => $agency->id, 'title' => 'Active', 'is_active' => true]);

        return $referral->fresh(['caseFile.client', 'agency']);
    }
}
