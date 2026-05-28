<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\User;
use App\Services\Chatbot\ChatbotCaseService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChatbotCaseServiceTest extends TestCase
{
    use RefreshDatabase;

    private ChatbotCaseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ChatbotCaseService::class);

        // Seed roles (spatie separate table, not the User.role column)
        (new RolesAndPermissionsSeeder)->run();
    }

    // ──────────────────────────────────────────────
    //  Auth helpers
    // ──────────────────────────────────────────────

    /** Create a logged-in CASE_MANAGER user */
    private function actingAsCaseManager(): User
    {
        $user = User::factory()->create();
        $user->assignRole('CASE_MANAGER');
        $this->actingAs($user);

        return $user;
    }

    /** Create a logged-in ADMIN user */
    private function actingAsAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('ADMIN');
        $this->actingAs($user);

        return $user;
    }

    /** Create an agency-focal user with agcy_id set */
    private function actingAsAgencyFocal(): User
    {
        $agency = Agency::factory()->create();
        $user = User::factory()->create(['agcy_id' => $agency->id]);
        $user->assignRole('AGENCY_FOCAL_PERSON');
        $this->actingAs($user);

        return $user;
    }

    /** Build a case with an attached client, assigned to the given user */
    private function createCaseWithClient(User $caseManager): CaseFile
    {
        $case = CaseFile::factory()->create(['user_id' => $caseManager->id]);
        Client::factory()->create(['case_id' => $case->id]);

        return $case;
    }

    // ──────────────────────────────────────────────
    //  Auth context tests
    // ──────────────────────────────────────────────

    public function test_is_authenticated_returns_false_for_guest()
    {
        $this->assertFalse($this->service->isAuthenticated());
    }

    public function test_is_authenticated_returns_true_when_logged_in()
    {
        $this->actingAsCaseManager();
        $this->assertTrue($this->service->isAuthenticated());
    }

    public function test_is_staff_user_returns_false_for_guest()
    {
        $this->assertFalse($this->service->isStaffUser());
    }

    public function test_is_staff_user_returns_true_for_case_manager()
    {
        $this->actingAsCaseManager();
        $this->assertTrue($this->service->isStaffUser());
    }

    public function test_is_staff_user_returns_true_for_admin()
    {
        $this->actingAsAdmin();
        $this->assertTrue($this->service->isStaffUser());
    }

    public function test_is_staff_user_returns_true_for_agency_focal()
    {
        $this->actingAsAgencyFocal();
        $this->assertTrue($this->service->isStaffUser());
    }

    public function test_get_user_auth_context_for_guest()
    {
        $this->assertSame('anonymous_public', $this->service->getUserAuthContext());
    }

    public function test_get_user_auth_context_for_admin()
    {
        $this->actingAsAdmin();
        $this->assertSame('admin', $this->service->getUserAuthContext());
    }

    public function test_get_user_auth_context_for_case_manager()
    {
        $this->actingAsCaseManager();
        $this->assertSame('case_manager', $this->service->getUserAuthContext());
    }

    public function test_get_user_auth_context_for_agency_focal()
    {
        $this->actingAsAgencyFocal();
        $this->assertSame('agency_focal', $this->service->getUserAuthContext());
    }

    // ──────────────────────────────────────────────
    //  searchCases
    // ──────────────────────────────────────────────

    public function test_search_cases_denied_for_guest()
    {
        $response = $this->service->searchCases('some query');
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('logged in', $response['message']);
    }

    public function test_search_cases_returns_results_for_case_manager()
    {
        $cm = $this->actingAsCaseManager();
        $case = $this->createCaseWithClient($cm);

        $response = $this->service->searchCases($case->case_number);

        $this->assertTrue($response['success']);
        $this->assertCount(1, $response['data']);
        $this->assertSame($case->id, $response['data'][0]['id']);
    }

    public function test_search_cases_respects_ownership_for_case_manager()
    {
        $cm = $this->actingAsCaseManager();
        $this->createCaseWithClient($cm); // own case

        // Another manager's case — should not appear
        $otherCm = User::factory()->create();
        $otherCm->assignRole('CASE_MANAGER');
        $otherCase = $this->createCaseWithClient($otherCm);

        $response = $this->service->searchCases($otherCase->case_number);

        $this->assertTrue($response['success']);
        $this->assertCount(0, $response['data']);
    }

    public function test_search_cases_sees_all_for_admin()
    {
        $cm = User::factory()->create();
        $cm->assignRole('CASE_MANAGER');
        $this->createCaseWithClient($cm);

        $this->actingAsAdmin();
        $response = $this->service->searchCases('CASE-');

        $this->assertTrue($response['success']);
        $this->assertCount(1, $response['data']);
    }

    public function test_search_cases_by_client_name()
    {
        $cm = $this->actingAsCaseManager();
        $case = $this->createCaseWithClient($cm);
        $client = $case->client;

        $response = $this->service->searchCases($client->first_name);

        $this->assertTrue($response['success']);
        $this->assertCount(1, $response['data']);
        $this->assertStringContainsString($client->first_name, $response['data'][0]['client_name']);
    }

    public function test_search_cases_by_tracker_number()
    {
        $cm = $this->actingAsCaseManager();
        $case = $this->createCaseWithClient($cm);

        $response = $this->service->searchCases($case->tracker_number);

        $this->assertTrue($response['success']);
        $this->assertCount(1, $response['data']);
        $this->assertSame($case->tracker_number, $response['data'][0]['tracker_number']);
    }

    // ──────────────────────────────────────────────
    //  getCaseDetail
    // ──────────────────────────────────────────────

    public function test_get_case_detail_denied_for_guest()
    {
        $response = $this->service->getCaseDetail('some-id');
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('logged in', $response['message']);
    }

    public function test_get_case_detail_returns_data_for_owner()
    {
        $cm = $this->actingAsCaseManager();
        $case = $this->createCaseWithClient($cm);

        $response = $this->service->getCaseDetail($case->id);

        $this->assertTrue($response['success']);
        $this->assertSame($case->case_number, $response['data']['case_number']);
        $this->assertArrayHasKey('client', $response['data']);
        $this->assertArrayHasKey('referrals', $response['data']);
        $this->assertArrayHasKey('client_type', $response['data']);
    }

    public function test_get_case_detail_denied_for_non_owner_case_manager()
    {
        $cm = $this->actingAsCaseManager();
        $otherCm = User::factory()->create();
        $otherCm->assignRole('CASE_MANAGER');
        $otherCase = $this->createCaseWithClient($otherCm);

        $response = $this->service->getCaseDetail($otherCase->id);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('do not have access', $response['message']);
    }

    public function test_get_case_detail_allows_admin_any_case()
    {
        $cm = User::factory()->create();
        $cm->assignRole('CASE_MANAGER');
        $case = $this->createCaseWithClient($cm);

        $this->actingAsAdmin();
        $response = $this->service->getCaseDetail($case->id);

        $this->assertTrue($response['success']);
        $this->assertSame($case->case_number, $response['data']['case_number']);
    }

    // ──────────────────────────────────────────────
    //  initiateCaseOTP
    // ──────────────────────────────────────────────

    public function test_initiate_otp_with_invalid_tracker()
    {
        $response = $this->service->initiateCaseOTP('NONEXISTENT-123');

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('No case found', $response['message']);
    }

    public function test_initiate_otp_with_no_email_on_case()
    {
        $cm = User::factory()->create();
        $cm->assignRole('CASE_MANAGER');
        $case = CaseFile::factory()->create(['user_id' => $cm->id]);
        // Create client WITHOUT email
        Client::factory()->create(['case_id' => $case->id, 'email' => null]);

        $response = $this->service->initiateCaseOTP($case->tracker_number);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('No email', $response['message']);
    }

    public function test_initiate_otp_sends_code()
    {
        $cm = User::factory()->create();
        $cm->assignRole('CASE_MANAGER');
        $case = $this->createCaseWithClient($cm);

        $response = $this->service->initiateCaseOTP($case->tracker_number);

        $this->assertTrue($response['success']);
        $this->assertStringContainsString('verification code', $response['message']);
        $this->assertStringContainsString('@', $response['email_masked']);
    }

    // ──────────────────────────────────────────────
    //  OTP verify + getVerifiedCaseInfo flow
    // ──────────────────────────────────────────────

    public function test_verify_otp_with_invalid_code()
    {
        $cm = User::factory()->create();
        $cm->assignRole('CASE_MANAGER');
        $case = $this->createCaseWithClient($cm);

        // Initiate to create the OTP in cache
        $this->service->initiateCaseOTP($case->tracker_number);

        $response = $this->service->verifyCaseOTP($case->tracker_number, '000000');

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Invalid or expired', $response['message']);
    }

    public function test_full_otp_verify_flow()
    {
        $cm = User::factory()->create();
        $cm->assignRole('CASE_MANAGER');
        $case = $this->createCaseWithClient($cm);

        // Initiate OTP
        $initResponse = $this->service->initiateCaseOTP($case->tracker_number);
        $this->assertTrue($initResponse['success']);

        // Retrieve the OTP from cache to simulate what the user receives
        $email = $case->client->email;
        $otp = Cache::get('otp:chatbot_case_verify:'.$email);
        $this->assertNotNull($otp, 'OTP should be stored in cache');

        // Verify
        $verifyResponse = $this->service->verifyCaseOTP($case->tracker_number, $otp);
        $this->assertTrue($verifyResponse['success']);
        $this->assertSame($case->case_number, $verifyResponse['case_data']['case_number']);

        // Now getVerifiedCaseInfo should work
        $infoResponse = $this->service->getVerifiedCaseInfo($case->tracker_number);
        $this->assertTrue($infoResponse['success']);
        $this->assertSame($case->case_number, $infoResponse['data']['case_number']);
    }

    public function test_get_verified_case_info_fails_without_verification()
    {
        $cm = User::factory()->create();
        $cm->assignRole('CASE_MANAGER');
        $case = $this->createCaseWithClient($cm);

        $response = $this->service->getVerifiedCaseInfo($case->tracker_number);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('not been verified', $response['message']);
    }

    public function test_otp_verify_for_invalid_tracker()
    {
        $response = $this->service->verifyCaseOTP('BAD-TRACKER', '123456');

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('No case found', $response['message']);
    }

    public function test_otp_ttl_expiry()
    {
        $cm = User::factory()->create();
        $cm->assignRole('CASE_MANAGER');
        $case = $this->createCaseWithClient($cm);

        $this->service->initiateCaseOTP($case->tracker_number);
        $email = $case->client->email;

        // Manually expire the cache entry
        Cache::forget('otp:chatbot_case_verify:'.$email);

        $verifyResponse = $this->service->verifyCaseOTP($case->tracker_number, '123456');
        $this->assertFalse($verifyResponse['success']);
        $this->assertStringContainsString('Invalid or expired', $verifyResponse['message']);
    }

    // ──────────────────────────────────────────────
    //  Edge cases / error handling
    // ──────────────────────────────────────────────

    public function test_search_cases_empty_query_lists_accessible_cases()
    {
        $cm = $this->actingAsCaseManager();
        $this->createCaseWithClient($cm);

        // Empty query returns all cases the user has access to (no text filter)
        $response = $this->service->searchCases('');
        $this->assertTrue($response['success']);
        $this->assertGreaterThanOrEqual(1, count($response['data']));
    }

    public function test_get_case_detail_nonexistent_id()
    {
        $this->actingAsAdmin();
        $response = $this->service->getCaseDetail('00000000-0000-0000-0000-000000000000');
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('not found', $response['message']);
    }

    public function test_get_verified_case_info_nonexistent_tracker()
    {
        // Manually inject into session to simulate prior verification
        Session::put('chatbot_verified_cases', ['FAKE-TRACKER-999']);

        $response = $this->service->getVerifiedCaseInfo('FAKE-TRACKER-999');
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('not found', $response['message']);
    }
}
