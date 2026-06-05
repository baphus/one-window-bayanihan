# Test Plan Template

> Use this template to document your testing cycle on the staging environment.

---

## Test Cycle Information

| Field | Value |
|-------|-------|
| **Test Cycle ID** | TC-YYYY-MM-DD-001 |
| **Start Date** | [DATE] |
| **End Date** | [DATE] |
| **Tested By** | [NAME] |
| **Environment** | Staging (https://bayanihan-staging.onrender.com) |
| **Build Version** | [COMMIT HASH or DATE] |
| **Browser/OS** | [e.g., Chrome 127 on Windows 11] |

---

## Test Scope

- [ ] Case Management (Create, Update, Close, Refer)
- [ ] Referral Workflow (Send, Accept, Complete)
- [ ] File Uploads (Documents, Attachments)
- [ ] User Authentication (Login, OTP, 2FA)
- [ ] Admin Functions (Settings, User Management)
- [ ] Analytics Dashboard
- [ ] Audit Trail & Logging
- [ ] Email Notifications
- [ ] System Performance (Load time, Responsiveness)

---

## Critical Test Cases

### Case Lifecycle
- [ ] Create case with all required fields ✓/✗ [Notes]
- [ ] Case number auto-generated ✓/✗ [Notes]
- [ ] Edit case details ✓/✗ [Notes]
- [ ] Change case status (OPEN → IN_PROGRESS → CLOSED) ✓/✗ [Notes]
- [ ] Archive closed case ✓/✗ [Notes]
- [ ] Delete case (soft delete) ✓/✗ [Notes]

### Referral Management
- [ ] Create referral with agency selection ✓/✗ [Notes]
- [ ] Select multiple required services ✓/✗ [Notes]
- [ ] Send referral to agency ✓/✗ [Notes]
- [ ] Agency receives notification ✓/✗ [Notes]
- [ ] Agency accepts/rejects referral ✓/✗ [Notes]
- [ ] Track referral status changes ✓/✗ [Notes]

### File Management
- [ ] Upload PDF document ✓/✗ [Notes]
- [ ] Upload JPG image ✓/✗ [Notes]
- [ ] Document appears in file list ✓/✗ [Notes]
- [ ] Download uploaded file ✓/✗ [Notes]
- [ ] Delete document ✓/✗ [Notes]

### User Authentication
- [ ] Login with valid credentials ✓/✗ [Notes]
- [ ] OTP login flow (send OTP) ✓/✗ [Notes]
- [ ] OTP verification ✓/✗ [Notes]
- [ ] Login fails with invalid credentials ✓/✗ [Notes]
- [ ] Logout clears session ✓/✗ [Notes]

### Access Control
- [ ] Case Manager sees only own cases ✓/✗ [Notes]
- [ ] Agency user sees referrals for their agency ✓/✗ [Notes]
- [ ] Admin sees all cases and analytics ✓/✗ [Notes]
- [ ] Cannot access unauthorized pages ✓/✗ [Notes]

---

## Performance Checks

| Scenario | Target | Actual | Status |
|----------|--------|--------|--------|
| Page load time (main dashboard) | < 3s | [___] | ✓/✗ |
| Case list load (1000+ cases) | < 5s | [___] | ✓/✗ |
| Case creation submit | < 2s | [___] | ✓/✗ |
| Referral send | < 2s | [___] | ✓/✗ |
| File upload (10MB) | < 10s | [___] | ✓/✗ |
| Search/filter cases | < 3s | [___] | ✓/✗ |

---

## Issues Found

### Issue #1
- **Title:** [Brief description]
- **Severity:** [Critical / High / Medium / Low]
- **Steps to Reproduce:** 
  1. [Step 1]
  2. [Step 2]
- **Expected:** [What should happen]
- **Actual:** [What happened]
- **Screenshot/Logs:** [Attach if available]
- **Status:** [New / Reported / In Progress / Fixed]
- **GitHub Issue:** [#123](https://github.com/...)

### Issue #2
[Repeat as needed]

---

## Test Execution Summary

| Category | Pass | Fail | Blocked | Total | Pass Rate |
|----------|------|------|---------|-------|-----------|
| Case Management | ___ | ___ | ___ | ___ | __% |
| Referral Workflow | ___ | ___ | ___ | ___ | __% |
| File Uploads | ___ | ___ | ___ | ___ | __% |
| Authentication | ___ | ___ | ___ | ___ | __% |
| Admin Functions | ___ | ___ | ___ | ___ | __% |
| **TOTAL** | ___ | ___ | ___ | ___ | __% |

---

## Sign-Off

- **Tested By:** [NAME]
- **Date:** [DATE]
- **Approved By:** [TEAM LEAD]
- **Approval Date:** [DATE]

**Overall Result:** ✓ PASS / ✗ FAIL / ⚠ CONDITIONAL

**Notes:**
[Any additional comments or recommendations]

---

## Attachments

- [ ] Screenshots of issues
- [ ] Performance logs
- [ ] Browser console errors
- [ ] Test evidence
