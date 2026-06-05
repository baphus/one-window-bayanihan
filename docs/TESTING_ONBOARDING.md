# Tester Quick Start Guide

> **Staging Environment for One Window Bayanihan**  
> Last Updated: June 4, 2026

## 🎯 Quick Access

**Staging URL:** https://bayanihan-staging.onrender.com  
**Status:** Auto-deploys on every push to `main` branch (5-10 mins)

---

## 👤 Test User Credentials

Use any of these credentials to log in:

| Role | Username | Password |
|------|----------|----------|
| **Admin** | `admin@test.gov.ph` | `123456` |
| **Case Manager** | `casemanager1@test.gov.ph` | `123456` |
| **Case Manager** | `casemanager2@test.gov.ph` | `123456` |
| **Case Manager** | `casemanager3@test.gov.ph` | `123456` |
| **Agency User** | `agency1@test.gov.ph` | `123456` |
| **Agency User** | `agency2@test.gov.ph` | `123456` |
| **Agency User** | `agency3@test.gov.ph` | `123456` |

---

## 📊 Test Data Available

Staging includes **1000+ realistic test cases**:

- ✓ Cases in all statuses: OPEN, IN_PROGRESS, PENDING_REFERRAL, REFERRED, CLOSED, ARCHIVED
- ✓ Multiple agencies with real relationships
- ✓ Clients with families and OFW categories
- ✓ Referrals with attachments and milestones
- ✓ Complete audit trail (6 months of history)
- ✓ Services and case categories fully populated

---

## 🔑 OTP Login

1. Enter your username and click "Send OTP"
2. OTP is sent to email (in development, check logs or use debug mode)
3. **Debug Mode Enabled:** OTP auto-fills if you wait 3 seconds

For immediate access without OTP:
- Use the admin credentials above (OTP may be bypassed for dev accounts)
- Or ask dev team to enable OTP debug mode in System Settings

---

## 🧪 Core Test Flows

### 1. Create a New Case
1. Log in as **Case Manager**
2. Click **"New Case"** button
3. Fill client info (required fields marked with *)
4. Select a category (e.g., "Legal Assistance")
5. Click **"Create Case"**
6. Verify case appears in case list with unique case number

### 2. Refer a Case
1. Open an existing case (status: OPEN or IN_PROGRESS)
2. Click **"Create Referral"** tab
3. Select agency and required services
4. Add notes (optional)
5. Click **"Send Referral"**
6. Verify referral appears in referral list with agency details

### 3. Upload Referral Documents
1. In referral view, click **"Add Document"**
2. Select a file (PDF, DOC, JPG supported)
3. Upload should complete without errors
4. Document appears in attachments list

### 4. View Audit Trail
1. Open a case
2. Scroll to **"Activity"** tab
3. Verify audit logs show CREATE, UPDATE, ASSIGN events
4. Each log shows: user, timestamp, action, old/new values

### 5. Admin: System Settings
1. Log in as **Admin**
2. Click **"Settings"** (admin menu)
3. Verify all settings pages load without errors:
   - System Configuration
   - User Management
   - Agency Management
   - Services & Categories
   - Analytics Dashboard

---

## 🔄 Resetting Test Data

### Option 1: Manual Reset (Immediate)
Ask dev team to run:
```bash
php artisan seed:staging --fresh
```

### Option 2: Scheduled Reset (Daily)
Staging data resets automatically every day at **2 AM UTC** (10 AM PH time).

### Option 3: On-Demand via GitHub
Comment on any GitHub issue:
```
@bayanihan reset-staging
```
(Scheduled workflow trigger – 2 min delay)

---

## 🐛 Reporting Bugs

### How to Report

1. **Create a GitHub Issue** in this repository
2. Use template: **[Staging Bug Report](../../issues/new?template=staging-bug-report.md)**
3. Include:
   - Environment (browser, OS)
   - Steps to reproduce
   - Expected vs. actual behavior
   - Screenshots (if applicable)
   - Console errors (F12 → Console)

### Issue Labels
- Tag issues with: `tester-reported`, `staging-environment`
- Severity levels: `high-priority`, `blocked`, `minor`

---

## ⚠️ Known Limitations

| Issue | Workaround |
|-------|-----------|
| Email notifications not real | Check dashboard notifications instead |
| File uploads to staging (Cloudinary) | Use staging API key (configured) |
| SMS notifications not active | Check in-app notifications |
| Some external APIs stubbed | Refer to API contract docs |

---

## 🆘 Troubleshooting

### Page Won't Load / 500 Error
1. **Clear browser cache:** Ctrl+Shift+Delete → Clear all
2. **Hard refresh:** Ctrl+F5
3. **Check Slack:** Dev team posts status updates
4. **Wait 5 min:** Deployment may be in progress
5. **Report:** Create GitHub issue with error details

### Login Failed
1. Verify username matches exactly (copy-paste from above)
2. Password is `123456` (all users)
3. Check if Render app is running (check Actions status)
4. Try a different browser

### OTP Not Arriving
1. Enable debug mode in System Settings (ask admin)
2. Or ask dev team to toggle OTP debug mode
3. In debug mode, OTP auto-fills after 3 seconds

### Data Looks Wrong
1. Request manual reset (ask dev team)
2. Or wait for daily 2 AM UTC reset
3. Report the issue with screenshots

---

## 📞 Support

| Issue | Contact |
|-------|---------|
| **Urgent bug blocking test** | Slack dev team |
| **General question** | GitHub Discussions |
| **Infrastructure down** | Check Actions status |
| **Need data reset** | Comment in GitHub issue |

---

## 🚀 What's Next?

After testing on staging:
1. Document findings in test report
2. Create GitHub issues for any bugs (use bug template)
3. Include severity, reproduction steps, screenshots
4. Tag with `staging-environment` + `tester-reported`
5. Dev team will triage and assign priority

---

**Happy Testing! 🎉**
