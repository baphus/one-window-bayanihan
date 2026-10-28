<?php

use App\Enums\AuditAction;
use App\Enums\AuditModule;
use App\Enums\UserRole;

$caseManagerModules = [
    AuditModule::CASE->value,
    AuditModule::CASE_ISSUE->value,
    AuditModule::CASE_DOCUMENT->value,
    AuditModule::REFERRAL->value,
    AuditModule::MILESTONE->value,
    AuditModule::REFERRAL_ATTACHMENT->value,
    AuditModule::REFERRAL_COMMENT->value,
    AuditModule::REFERRAL_CLIENT_REQUEST->value,
];

$agencyModules = [
    AuditModule::REFERRAL->value,
    AuditModule::MILESTONE->value,
    AuditModule::REFERRAL_ATTACHMENT->value,
    AuditModule::REFERRAL_SERVICE_REQUIREMENT->value,
    AuditModule::REFERRAL_CLIENT_REQUEST->value,
    AuditModule::REFERRAL_CLIENT_REQUEST_ITEM->value,
    AuditModule::REFERRAL_CLIENT_MESSAGE->value,
    AuditModule::REFERRAL_CLIENT_MESSAGE_ATTACHMENT->value,
    AuditModule::REFERRAL_CLIENT_ACCESS_LINK->value,
    AuditModule::REFERRAL_COMMENT->value,
];

$caseManagerActions = [
    AuditAction::CREATE->value,
    AuditAction::UPDATE->value,
    AuditAction::CLOSE->value,
    AuditAction::APPROVE->value,
    AuditAction::REJECT->value,
    AuditAction::ARCHIVE->value,
    AuditAction::RESTORE->value,
    AuditAction::SEND->value,
    AuditAction::CREATE_CASE_ISSUE->value,
    AuditAction::DOWNLOAD->value,
];

$agencyActions = [
    AuditAction::ACCEPT->value,
    AuditAction::REQUEST_COMPLIANCE->value,
    AuditAction::RESUME->value,
    AuditAction::COMPLETE->value,
    AuditAction::UPDATE->value,
    AuditAction::CREATE->value,
    AuditAction::ASSIGN_SERVICE->value,
    AuditAction::UNASSIGN_SERVICE->value,
    AuditAction::ADD_REQUIREMENT->value,
    AuditAction::UPDATE_REQUIREMENT->value,
    AuditAction::REMOVE_REQUIREMENT->value,
    AuditAction::ISSUE_CLIENT_REQUEST->value,
    AuditAction::RESOLVE_CLIENT_REQUEST->value,
    AuditAction::CANCEL_CLIENT_REQUEST->value,
    AuditAction::REOPEN_CLIENT_REQUEST->value,
    AuditAction::REPLY_CLIENT_REQUEST->value,
    AuditAction::ISSUE_LINK->value,
    AuditAction::REISSUE_LINK->value,
    AuditAction::REVOKE_LINK->value,
    AuditAction::DOWNLOAD->value,
];

return [
    UserRole::CASE_MANAGER->value => [
        'modules' => $caseManagerModules,
        'actions' => $caseManagerActions,
    ],
    UserRole::AGENCY->value => [
        'modules' => $agencyModules,
        'actions' => $agencyActions,
    ],
    // Admin-only modules are those absent from both staff lists: client,
    // client_address, client_employment, next_of_kin, and all other modules
    // not listed above.
];
