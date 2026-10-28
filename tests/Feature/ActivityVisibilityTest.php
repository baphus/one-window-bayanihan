<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\AuditModule;
use App\Services\ActivityVisibility;
use Tests\TestCase;

class ActivityVisibilityTest extends TestCase
{
    private const ADMIN_ONLY_MODULES = [
        'client',
        'client_address',
        'client_employment',
        'next_of_kin',
        'survey_form',
        'survey_question',
        'survey_invitation',
        'survey_response',
        'feedback',
        'user',
        'agency',
        'service',
        'service_requirement',
        'case_category',
        'case_status',
        'user_invite',
        'helpdesk_article',
        'audit',
        'data_export',
        'email_log',
        'system_setting',
        'auth',
        'security',
        'session',
        'mfa',
    ];

    private const ADMIN_ONLY_ACTIONS = [
        'DELETE',
        'UNARCHIVE',
        'PUBLISH',
        'PURGE',
        'LOGIN',
        'LOGOUT',
        'LOGIN_FAILED',
        'ACCEPT_INVITE',
        'REQUEST_PASSWORD_RESET',
        'RESET_PASSWORD',
        'CHANGE_PASSWORD',
        'VERIFY_EMAIL',
        'CHANGE_EMAIL',
        'ENABLE_MFA',
        'DISABLE_MFA',
        'REGENERATE_RECOVERY_CODES',
        'ADMIN_RESET_PASSWORD',
        'ADMIN_CHANGE_EMAIL',
        'ADMIN_VERIFY_EMAIL',
        'DELETE_ACCOUNT',
        'PURGE_USER',
        'REVOKE_SESSIONS',
        'EXPORT',
        'INVITE',
        'RESEND_INVITE',
        'CANCEL_INVITE',
        'DEACTIVATE',
        'REACTIVATE',
        'RESET_MFA',
        'TOGGLE_MAINTENANCE',
        'UPDATE_SECURITY',
        'SEND_REMINDER',
        'REGISTER',
        'FILE_INTAKE',
        'REPLY_CLIENT_REQUEST',
        'UPLOAD_REPLY_DOC',
        'TRACK_CASE',
        'SUBMIT_SURVEY',
        'SUBMIT_CONTACT',
    ];

    public function test_every_module_has_a_visibility_decision(): void
    {
        $visibleModules = array_unique(array_merge(
            ActivityVisibility::modulesFor('CASE_MANAGER'),
            ActivityVisibility::modulesFor('AGENCY'),
            self::ADMIN_ONLY_MODULES,
        ));

        foreach (AuditModule::cases() as $module) {
            $this->assertContains(
                $module->value,
                $visibleModules,
                "Module {$module->value} has no activity visibility decision.",
            );
        }
    }

    public function test_every_action_has_a_visibility_decision(): void
    {
        $visibleActions = array_unique(array_merge(
            ActivityVisibility::actionsFor('CASE_MANAGER'),
            ActivityVisibility::actionsFor('AGENCY'),
            self::ADMIN_ONLY_ACTIONS,
        ));

        foreach (AuditAction::cases() as $action) {
            $this->assertContains(
                $action->value,
                $visibleActions,
                "Action {$action->value} has no activity visibility decision.",
            );
        }
    }

    public function test_agency_has_no_case_or_client_modules(): void
    {
        $this->assertEmpty(array_intersect([
            'case',
            'case_document',
            'client',
            'client_address',
            'client_employment',
            'next_of_kin',
        ], ActivityVisibility::modulesFor('AGENCY')));
    }

    public function test_admin_modules_are_the_staff_union(): void
    {
        $union = array_unique(array_merge(
            ActivityVisibility::modulesFor('CASE_MANAGER'),
            ActivityVisibility::modulesFor('AGENCY'),
        ));
        sort($union);

        $admin = ActivityVisibility::modulesFor('ADMIN');
        sort($admin);

        $this->assertSame($union, $admin);
    }

    public function test_staff_visibility_truth_table(): void
    {
        $cases = [
            ['user', 'CHANGE_EMAIL', false],
            ['client_address', 'UPDATE', false],
            ['client_employment', 'UPDATE', false],
            ['next_of_kin', 'UPDATE', false],
            ['case', 'CLOSE', true],
            ['referral', 'ACCEPT', true],
            ['case_document', 'DOWNLOAD', true],
            ['unknown_module', 'UPDATE', false],
        ];

        foreach ($cases as [$module, $action, $expected]) {
            $this->assertSame(
                $expected,
                ActivityVisibility::isVisibleToStaff($module, $action),
                "Unexpected visibility for {$module}/{$action}.",
            );
        }
    }

    public function test_legacy_module_spelling_is_normalised(): void
    {
        $this->assertTrue(ActivityVisibility::isVisibleToStaff('cases', 'CLOSE'));
    }
}
