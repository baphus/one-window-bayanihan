# Tester Runbook & Troubleshooting

> **Emergency procedures and common issues for staging testers**

---

## 🚨 Emergency Procedures

### Staging App Won't Load

**Symptoms:** 502 Bad Gateway, Connection Timeout, or Blank Page

**Steps:**
1. Check GitHub Actions: https://github.com/bayanihan/bayanihan/actions
   - Look for "Deploy to Staging" workflow status
   - If RED: Deployment failed, wait for dev team to fix
   - If YELLOW: Deployment in progress, wait 5-10 mins

2. Clear browser cache:
   - Windows: `Ctrl+Shift+Delete`
   - Select "All time" → Clear

3. Hard refresh: `Ctrl+F5` (or `Cmd+Shift+R` on Mac)

4. Try another browser (Chrome → Firefox)

5. **If still down after 15 mins:**
   - Check Render dashboard: https://dashboard.render.com (ask dev for access)
   - Post in Slack: #dev-staging-status

---

### Login Not Working

**Symptoms:** Invalid credentials error, or stuck on login page

**Troubleshooting:**

1. **Copy-paste username from docs:**
   ```
   admin@test.gov.ph
   Password: 123456
   ```
   *(Don't type manually—copy from guide above)*

2. **Check Caps Lock is OFF**

3. **Verify user role has correct password:**
   - All test users use same password: `123456`
   - No special characters, no extra spaces

4. **Check if OTP is blocking:**
   - If OTP appears but doesn't auto-fill, wait 3 seconds
   - Or ask dev to enable debug mode in System Settings
   - (Debug mode auto-fills OTP)

5. **Try incognito/private browser mode** (clears cookies)

6. **Still stuck?**
   - Ask dev: "Is staging down?"
   - Request fresh database: `php artisan seed:staging --fresh`

---

### OTP Not Arriving

**Symptoms:** "Send OTP" button doesn't show code, or code never arrives

**Workarounds:**

1. **Check System Settings (Admin only):**
   - Log in as `admin@test.gov.ph`
   - Settings → OTP Configuration
   - Toggle "Debug Mode" ON
   - Now OTP auto-fills after 3 seconds

2. **Use alternative login:**
   - Ask dev to manually create session token
   - Or use direct login (if available in dev mode)

3. **Check email:**
   - OTP sent to user's email (staging uses real SMTP)
   - Check SPAM folder
   - If SendGrid is down, ask dev

4. **Request fresh seed:**
   - Old test users may have stale OTP data
   - Ask dev: `php artisan seed:staging --fresh`

---

### Slow Performance / Timeouts

**Symptoms:** Page takes 20+ seconds to load, or requests timeout

**Diagnosis:**

1. **Check network tab (F12 → Network):**
   - Are requests stuck/pending?
   - Any 503 errors?
   - → Database is slow (scaling issue)

2. **Check Render metrics:**
   - Dev team can check CPU/memory usage
   - If maxed out: database needs scaling or optimization

3. **Temporary workarounds:**
   - Use filters to narrow search (reduce 1000+ case load)
   - Clear browser cache
   - Hard refresh
   - Try at different time (less load)

4. **Report to dev:**
   - Time of incident
   - Page that was slow
   - Browser network tab screenshot (F12 → Network)

---

## 🔄 Reset Staging Data

### Manual Reset (Immediate)

If you accidentally delete important test data:

**Request from dev team:**
```
User: [Your name]
Request: Reset staging database to fresh state
Urgency: [High/Medium/Low]
```

Dev runs:
```bash
php artisan seed:staging --fresh
```

**Takes:** ~3 minutes (testers locked out during reset)

---

### Automatic Daily Reset

Staging automatically resets every day at:
- **2 AM UTC** = **10 AM Philippines time**

Reset includes:
- Wipe all case/referral data
- Fresh 1000+ test case seed
- Clean audit logs
- Reset all user passwords to `123456`

---

### On-Demand Reset via GitHub

**For automated reset without dev involvement:**

1. Create/open any GitHub issue in this repo
2. Post comment:
   ```
   /reset-staging
   ```
3. GitHub Action triggers automatically
4. Staging resets in ~2 minutes
5. Confirmation posted in Slack

---

## 📋 Pre-Testing Checklist

Before starting your test cycle:

- [ ] I have staging URL bookmarked: https://bayanihan-staging.onrender.com
- [ ] I can access staging (not 502/down)
- [ ] I can log in with test credentials
- [ ] I see 1000+ test cases in case list
- [ ] Browser is Chrome/Edge/Firefox (latest)
- [ ] Dev tools ready (F12 for screenshots of errors)
- [ ] Test plan template printed/open
- [ ] GitHub issue template ready for bug reports

---

## 🐛 How to Document a Bug

### Minimal Bug Report (Quick)
```
URL: https://bayanihan-staging.onrender.com/cases/123
Issue: Case status won't change from OPEN to IN_PROGRESS
Steps: Click case → Click status dropdown → Select "IN_PROGRESS" → Click Save
Error: Page shows "500 Internal Server Error"
```

### Full Bug Report (Complete)
1. Create GitHub issue: https://github.com/bayanihan/bayanihan/issues/new
2. Use template: "Staging Bug Report"
3. Fill all fields:
   - Environment (URL, browser, OS)
   - Steps to reproduce (numbered list)
   - Expected vs. actual
   - Screenshots/console errors
   - Labels: `tester-reported`, `staging-environment`

### High-Priority Issues
If issue is **BLOCKING** (prevents testing):
1. Post to Slack immediately
2. Tag @dev-on-call
3. Create GitHub issue (for record)
4. Include: what's blocked, severity, steps to reproduce

---

## 🔍 Debugging Tips

### Capture Console Errors
1. Press `F12` (dev tools)
2. Click **Console** tab
3. Right-click error → Copy
4. Paste in GitHub issue

### Take Screenshots
- **Windows:** `PrtScn` → Paste in Paint → Save
- **Highlight issue area:** Use browser dev tools inspector (click icon → click element)

### Check Response Errors
1. Press `F12`
2. Go to **Network** tab
3. Perform action (create case, etc.)
4. Click the failed request
5. View **Response** tab for error message

### Browser DevTools Tips
| Action | Keys |
|--------|------|
| Open DevTools | F12 |
| Console tab | F12 → Console |
| Network tab | F12 → Network |
| Inspect element | F12 → Ctrl+Shift+C → click element |
| Clear cache | Ctrl+Shift+Delete |
| Hard refresh | Ctrl+F5 |

---

## 🧪 Test Data Cheat Sheet

### Test Users (All password: `123456`)
- **Admin:** `admin@test.gov.ph`
- **Case Manager:** `casemanager1@test.gov.ph`
- **Agency:** `agency1@test.gov.ph`

### Available Test Data
- **Cases:** 1000+ (all statuses)
- **Agencies:** 5+ (DMW Region VII sub-agencies)
- **Services:** 20+ (legal, health, financial, etc.)
- **Categories:** 10+ (domestic abuse, labor, etc.)
- **Clients:** 800+ (individuals, families, OFWs)

---

## 📞 Escalation Path

| Severity | Action | Contact |
|----------|--------|---------|
| **CRITICAL** (App down) | Immediately notify | Slack @dev-on-call |
| **HIGH** (Feature broken) | Create issue + Slack | Slack #dev-staging |
| **MEDIUM** (Bug found) | Create GitHub issue | GitHub Issues |
| **LOW** (Question/UX) | GitHub Discussion | GitHub Discussions |

---

## ✅ Sign-Off Checklist

Before ending your test session:

- [ ] All test cases documented
- [ ] All bugs reported in GitHub (with screenshots)
- [ ] Test plan template completed and saved
- [ ] Data has been reset (if needed for next tester)
- [ ] Shared notes/findings with team

---

**Last Updated:** June 4, 2026  
**Maintained By:** Development Team  
**Questions?** Ask in Slack #dev-staging or @dev-team
