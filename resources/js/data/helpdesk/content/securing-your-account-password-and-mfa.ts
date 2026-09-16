const content = `# Securing Your Account: Password and MFA

Every account (Case Manager, Agency Focal Person, Administrator, OFW) signs in with an email address and password. Staff accounts handling sensitive OFW data can add two-factor authentication (MFA) with an authenticator app. This guide covers signing in, changing your password and email, and managing MFA.

![Login page](/assets/helpdesk/login-page.png)

## How sign-in works

1. Enter your **email and password** on the login page.
2. If MFA is enabled on your account, you are taken to the MFA challenge screen. Enter the **6-digit code from your authenticator app** — or a **recovery code** if you don't have your device.
3. Accounts without MFA complete sign-in with the password alone. OFW users land in the OFW portal; staff land on their role dashboard.

There is no email one-time code at sign-in. Email OTP codes are used only in three other places: public intake email verification, the public tracking portal, and profile email change.

Staff accounts are invite-only: an administrator sends an invitation and you complete registration through the invite link. There is no open self-registration for staff roles.

## Your Profile page

Open **Profile** from the user menu. It contains, in order: your **profile photo**, agency information, **personal information and emergency contact**, **notification preferences**, **email change**, **update password**, **two-factor authentication**, and account deletion. Select **Save Changes** after editing personal details — the button stays disabled until something changes.

![Profile security settings](/assets/helpdesk/profile-mfa.png)

## Changing your password

Use the **Update Password** section: enter your current password and the new one. Choose a passphrase you don't use anywhere else.

## Changing your email

Use the **Email Change** section. For security the change is confirmed with a **one-time code** before it takes effect. Administrators can also change a user's email through User Management using the same OTP confirmation.

## Enabling two-factor authentication

1. In the **Two-Factor Authentication** section, select **Enable Two-Factor Authentication**.
2. Scan the QR code with an authenticator app (Google Authenticator, Microsoft Authenticator, Aegis, etc.). If you can't scan, copy the **Manual Setup Key** into the app instead.
3. Enter the app's 6-digit code to verify. You'll see **"Two-factor authentication is now enabled!"**
4. **Save your Recovery Codes.** Use **Copy Codes** or **Download** and store them somewhere safe (not in your email inbox). Each recovery code can be used once to sign in if you lose your phone.

You can **Regenerate** recovery codes at any time — regenerating replaces the old set — or select **Disable MFA** to turn two-factor authentication off.

> Administrators, case managers, and focal persons handle sensitive OFW data. Enabling MFA is strongly recommended for all staff accounts and may be required by your office's security policy. MFA enrollment lives under **/profile/mfa**, and sign-in is gated so inactive accounts cannot proceed and MFA-enabled accounts must complete the challenge.

## If you're locked out

- Lost authenticator device: sign in with a recovery code, then disable and re-enable MFA with your new device.
- No recovery codes: contact your administrator, who can reset MFA through User Management.
- Forgotten password: use the **Forgot password** link on the login page to reset it by email.
- Email OTP not arriving (intake, tracking, or email change): check spam, then use the resend option; persistent failures can be checked by an administrator in the email logs.
`;
export default content;
