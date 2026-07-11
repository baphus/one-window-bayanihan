const content = `# Securing Your Account: Password and MFA

Every staff account (Case Manager, Agency Focal Person, Administrator) is protected by a password plus a one-time code at sign-in. This guide covers signing in, changing your password and email, and adding two-factor authentication (MFA) with an authenticator app.

![Login page](/assets/helpdesk/login-page.png)

## How sign-in works

1. Enter your **email and password** on the login page.
2. Verify the **one-time code (OTP)** sent to your email. You can request a resend if it doesn't arrive.
3. If you have enabled two-factor authentication, you are asked for the **6-digit code from your authenticator app** instead — or a **recovery code** if you don't have your device.

![Login OTP step](/assets/helpdesk/login-otp.png)

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
4. **Save your Recovery Codes.** Use **Copy Codes** or **Download** and store them somewhere safe (not in your email inbox). A recovery code lets you sign in if you lose your phone.

You can **Regenerate** recovery codes at any time — regenerating replaces the old set — or select **Disable MFA** to turn two-factor authentication off.

> Administrators and focal persons handle sensitive OFW data. Enabling MFA is strongly recommended for all staff accounts and may be required by your office's security policy.

## If you're locked out

- OTP email not arriving: check spam, then use the resend option; persistent failures can be checked by an administrator in the email logs.
- Lost authenticator device: sign in with a recovery code, then disable and re-enable MFA with your new device.
- No recovery codes: contact your administrator, who can assist through User Management.
`;
export default content;
