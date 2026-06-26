const content = `# Troubleshooting Common Issues

This guide provides solutions for common issues encountered when using the One Window Bayanihan system.

## Login Problems

### Wrong Credentials
If you cannot log in:
1. Check that you are entering the correct **email address**
2. Check that you are entering the correct **password**
3. Passwords are **case-sensitive**
4. If you forgot your password, contact your system administrator to reset it

### Account Inactive
If your account has been deactivated:
- You will see a message indicating the account is inactive
- Contact your system administrator to reactivate it
- Accounts are typically deactivated for users who are on leave or have changed roles

### OTP Not Received
If you do not receive the OTP code after logging in:

1. **Check your email** — the OTP is sent to your registered email address
2. **Check your spam/junk folder** — the email may have been filtered
3. **Wait 5 minutes** — the OTP expires after 5 minutes; after expiry, click **"Resend OTP"**
4. **Check SMTP configuration** — if OTPs are not being sent to anyone, an administrator should verify the mail server settings
5. **Request a resend** — after the 5-minute expiry, click the resend button

### Persistent Login Issues
If you have tried all the above and still cannot log in:
- Clear your browser cache and cookies
- Try a different browser (Chrome, Edge, Firefox, or Safari)
- Check your internet connection
- Contact your system administrator

## Document Upload Failures

### File Too Large
The maximum file size is **10MB per document**. If your file exceeds this:
- Compress the file using compression software
- Split the document into multiple files
- Convert images to a compressed format (JPG instead of PNG for photos)

### Wrong File Format
Accepted file formats are: **PDF, JPG, PNG**

- Convert Word documents (.doc, .docx) to PDF before uploading
- Convert other image formats (.bmp, .gif, .tiff) to JPG or PNG
- Spreadsheets and presentations cannot be uploaded directly

### Storage Connectivity Issues
If uploads fail consistently:
- The file is being sent to Supabase Storage for storage
- Check your internet connection
- Supabase Storage service may be temporarily unavailable
- Contact your system administrator if the problem persists

## Dashboard Not Loading

If the dashboard is blank or not loading properly:

1. **Clear your browser cache** — Ctrl+Shift+Delete (Windows) or Cmd+Shift+Delete (Mac)
2. **Try a different browser** — Chrome, Edge, Firefox, and Safari are all supported
3. **Check your internet connection** — a stable connection is required
4. **Disable browser extensions** — ad blockers or script blockers may interfere
5. **Check system status** — the system may be undergoing maintenance

## Notifications Not Arriving

If you are not receiving system notifications:

1. Verify the **queue worker** is running:
   \`\`\`
   php artisan queue:listen
   \`\`\`
2. Check the **failed_jobs** table for any failed notification jobs
3. Retry any failed jobs:
   \`\`\`
   php artisan queue:retry all
   \`\`\`
4. Ensure your **email address** is correct in your user profile
5. Check that **notifications are enabled** in your account settings

## Referral Status Not Updating

If you cannot update a referral status:

### Mandatory Fields
- Changing to **REJECTED** requires a **decision reason** comment
- Ensure all **required fields** are filled in

### Valid Status Transitions
Referrals can only follow valid transitions:
- PENDING to PROCESSING or REJECTED
- PROCESSING to FOR COMPLIANCE or COMPLETED
- FOR COMPLIANCE to PROCESSING

If you are trying to make an invalid transition, the system will show an error message.

## Session Timeout

Users are automatically logged out after **120 minutes of inactivity**:

- Any unsaved work will be lost — save frequently
- After logout, you will need to log in again with email, password, and OTP
- If you need longer sessions, ask your administrator to adjust the session timeout setting

## Browser Compatibility

For the best experience, use one of these supported browsers:

| Browser | Minimum Version |
|---------|----------------|
| Google Chrome | Latest 2 versions |
| Microsoft Edge | Latest 2 versions |
| Mozilla Firefox | Latest 2 versions |
| Apple Safari | Latest 2 versions |

Clear your browser cache and update to the latest version before reporting issues.

## Still Having Problems?

If none of these solutions resolve your issue:
1. Contact your **system administrator** or **supervisor**
2. Provide a detailed description of the problem, including any error messages
3. Mention what browser and operating system you are using
4. Note the time the issue occurred — this helps with audit log investigation
`;

export default content;
