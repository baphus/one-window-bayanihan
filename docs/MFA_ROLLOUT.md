# Administrator-controlled MFA rollout

## Policy

MFA enrollment policy is identical in every environment. All roles initially require enrollment. Administrators may change role defaults for case managers, agencies, and OFWs, or set a per-user `inherit`, `required`, or `optional` override. Administrator accounts always require MFA. Overrides survive role changes; the administrator requirement takes precedence while that role applies.

Role defaults use `mfa_role_case_manager_required`, `mfa_role_agency_required`, and `mfa_role_ofw_required` in system settings. The old enrollment environment switches and legacy `two_factor_required` setting are not policy controls.

Optional means enrollment is optional, not that enrolled MFA is bypassed. Relaxing policy preserves credentials. Newly required unenrolled users must enroll on their next protected request, without a grace period. Valid MFA-authenticated sessions continue. Unenrolled required users retain only necessary enrollment, email-verification, recovery, and logout access.

## Deployment checks

1. Apply application migrations, including the additive per-user policy migration. Preserve existing MFA secrets and recovery credentials.
2. Verify all initial role defaults are required and administrator policy cannot be relaxed. Review any deliberate exceptions through administrator controls.
3. Test required-user enrollment, enrolled optional-user login challenges, and an optional unenrolled account. Verify role and user policy changes apply to existing sessions on the next request.
4. Test TOTP and single-use recovery codes, five-minute administrator confirmation, expired-confirmation rejection, and reset/session revocation.
5. Confirm audit records and user notifications arrive without exposing credentials or recovery codes.

Do not use enrollment environment flags as a rollback or development bypass. Restoring an older application version requires a deliberate security and migration compatibility review; resetting credentials is recovery, not policy rollback.

## Administrator actions

Policy changes and resets require a reason and MFA confirmation within five minutes. Login MFA or dedicated confirmation opens this window; ordinary activity does not extend it. Policy edits are audited even when the effective requirement stays unchanged. Effective requirement changes, resets, and credential removal notify affected users through existing notification channels.

An administrator cannot reset their own MFA. Another freshly verified administrator can reset it, revoking target sessions and requiring reenrollment. Optional users may disable their own MFA with their password and current TOTP or recovery proof; other sessions are revoked. Required users cannot disable. Recovery-code regeneration retains password confirmation and requires fresh MFA confirmation.

## Last-administrator recovery

Use recovery codes or another verified administrator first. If neither is available, an authorized server operator can recover one active existing administrator:

```bash
php artisan mfa:recover-admin --target="administrator@example.test" --operator="operator identity" --reason="Lost authenticator and recovery codes" --confirm=RECOVER
```

The target may be an email address or UUID. This is a privileged server-side action: verify the account owner's identity before running it. It clears enrollment, revokes sessions, audits operator attribution and reason, and notifies the target. It does not grant a permanent MFA exemption. The administrator must sign in and reenroll before protected access. Never put passwords, TOTP secrets, or recovery codes in command arguments or logs.
