<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HelpdeskSeederV2 extends Seeder
{
    public function run(): void
    {
        $now = now();
        $authorId = DB::table('users')->where('role', 'ADMIN')->value('id');

        if (! $authorId) {
            return;
        }

        DB::transaction(function () use ($now, $authorId) {
            // --- Fetch existing categories (already seeded by HelpdeskSeeder) ---
            $categories = DB::table('helpdesk_categories')->where('is_deleted', false)->pluck('id', 'slug');

            // --- Tags: upsert 6 new tags alongside the existing 10 ---
            $tagNames = ['cases', 'referrals', 'documents', 'tracking', 'repatriation', 'compliance', 'escalation', 'training', 'troubleshooting', 'onboarding', 'chatbot', 'audit', 'privacy', 'dashboard', 'feedback', 'glossary'];
            $tags = [];
            foreach ($tagNames as $name) {
                $existing = DB::table('helpdesk_tags')->where('slug', $name)->first();
                if ($existing) {
                    $tags[$name] = $existing->id;
                } else {
                    $id = (string) Str::uuid();
                    DB::table('helpdesk_tags')->insert([
                        'id' => $id,
                        'name' => $name,
                        'slug' => $name,
                        'is_deleted' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $tags[$name] = $id;
                }
            }

            // --- Articles ---
            $articles = [];

            // === Section A: Getting Started Guides (featured) ===

            $articles[] = [
                'title' => 'Getting Started for Case Managers',
                'slug' => 'getting-started-case-managers',
                'category_slug' => 'cm-workflow',
                'tag_slugs' => ['onboarding', 'training', 'cases'],
                'content_markdown' => $this->article1(),
                'featured' => true,
                'excerpt' => 'A comprehensive onboarding guide for Case Managers covering login, dashboard navigation, case creation, referral management, and daily workflow routines.',
            ];

            $articles[] = [
                'title' => 'Getting Started for Agency Focal Persons',
                'slug' => 'getting-started-agency-focal',
                'category_slug' => 'referral-processing',
                'tag_slugs' => ['onboarding', 'training', 'referrals'],
                'content_markdown' => $this->article2(),
                'featured' => true,
                'excerpt' => 'Step-by-step onboarding guide for Agency Focal Persons on dashboard navigation, referral acceptance, milestone tracking, and inter-agency communication.',
            ];

            $articles[] = [
                'title' => 'Getting Started for System Administrators',
                'slug' => 'getting-started-system-admin',
                'category_slug' => 'system-config',
                'tag_slugs' => ['onboarding', 'training', 'troubleshooting'],
                'content_markdown' => $this->article3(),
                'featured' => true,
                'excerpt' => 'Administrator onboarding guide covering user management, agency registration, service configuration, system settings, helpdesk CMS, and audit log basics.',
            ];

            // === Section B: OFW-Focused Guides ===

            $articles[] = [
                'title' => 'Using the Public Tracking Portal',
                'slug' => 'using-public-tracking-portal',
                'category_slug' => 'case-submission',
                'tag_slugs' => ['tracking', 'cases'],
                'content_markdown' => $this->article4(),
                'featured' => false,
                'excerpt' => 'Guide for OFWs on how to use the public tracking portal to monitor case status using a tracker number and OTP verification.',
            ];

            $articles[] = [
                'title' => 'Providing Feedback on Your Case',
                'slug' => 'providing-feedback-on-your-case',
                'category_slug' => 'ofw-rights',
                'tag_slugs' => ['cases', 'documents'],
                'content_markdown' => $this->article5(),
                'featured' => false,
                'excerpt' => 'Learn how to provide feedback and rate the service you received after your case is closed, including the SERVQUAL evaluation dimensions.',
            ];

            $articles[] = [
                'title' => 'Understanding Case Statuses and Tracker Numbers',
                'slug' => 'understanding-case-statuses-tracker-numbers',
                'category_slug' => 'case-submission',
                'tag_slugs' => ['tracking', 'cases'],
                'content_markdown' => $this->article6(),
                'featured' => false,
                'excerpt' => 'Learn the difference between internal case numbers and public tracker numbers, and understand what each case and referral status means.',
            ];

            $articles[] = [
                'title' => 'OFW Assistance Services Available from DMW Region VII',
                'slug' => 'ofw-assistance-services-available',
                'category_slug' => 'ofw-assistance',
                'tag_slugs' => ['repatriation', 'documents', 'cases'],
                'content_markdown' => $this->article7(),
                'featured' => false,
                'excerpt' => 'Overview of the full range of assistance services available through DMW Region VII and its partner agencies including OWWA, DOLE, TESDA, DSWD, and DOH.',
            ];

            // === Section C: Case Manager Guides ===

            $articles[] = [
                'title' => 'Case Documentation Best Practices',
                'slug' => 'case-documentation-best-practices',
                'category_slug' => 'cm-workflow',
                'tag_slugs' => ['cases', 'documents', 'compliance'],
                'content_markdown' => $this->article8(),
                'featured' => false,
                'excerpt' => 'Best practices for writing effective case summaries, organizing uploaded documents, maintaining a complete audit trail, and avoiding common documentation pitfalls.',
            ];

            $articles[] = [
                'title' => 'Using the Dashboard for Daily Monitoring',
                'slug' => 'using-dashboard-daily-monitoring',
                'category_slug' => 'cm-workflow',
                'tag_slugs' => ['cases', 'tracking', 'compliance'],
                'content_markdown' => $this->article9(),
                'featured' => false,
                'excerpt' => 'Learn how to use the dashboard KPIs, recent cases list, pending referrals, and case trends chart for effective daily monitoring and workflow management.',
            ];

            $articles[] = [
                'title' => 'Case Closure Checklist and Procedures',
                'slug' => 'case-closure-checklist-procedures',
                'category_slug' => 'referrals-escalations',
                'tag_slugs' => ['cases', 'compliance', 'escalation'],
                'content_markdown' => $this->article10(),
                'featured' => false,
                'excerpt' => 'Complete checklist for closing cases including pre-closure verification, required documentation, the closure process, and post-closure client feedback procedures.',
            ];

            $articles[] = [
                'title' => 'Managing Overdue Referrals',
                'slug' => 'managing-overdue-referrals',
                'category_slug' => 'referrals-escalations',
                'tag_slugs' => ['referrals', 'escalation', 'tracking'],
                'content_markdown' => $this->article11(),
                'featured' => false,
                'excerpt' => 'Guide for Case Managers on monitoring overdue referrals, sending reminders, escalating chronic delays, and using SLA tracking to improve agency performance.',
            ];

            // === Section D: Agency Focal Person Guides ===

            $articles[] = [
                'title' => 'Managing Your Agency Services Profile',
                'slug' => 'managing-your-agency-services-profile',
                'category_slug' => 'referral-processing',
                'tag_slugs' => ['referrals', 'onboarding', 'compliance'],
                'content_markdown' => $this->article12(),
                'featured' => false,
                'excerpt' => 'Guide for agency focal persons on managing service offerings, setting realistic SLA processing days, and keeping service listings accurate for better referral matching.',
            ];

            $articles[] = [
                'title' => 'Adding Milestones to Referrals: Complete Guide',
                'slug' => 'adding-milestones-referrals-complete-guide',
                'category_slug' => 'referral-processing',
                'tag_slugs' => ['referrals', 'tracking', 'compliance'],
                'content_markdown' => $this->article13(),
                'featured' => false,
                'excerpt' => 'Complete guide on adding milestones to referrals including when to add them, how to write effective entries, frequency best practices, and example timelines.',
            ];

            $articles[] = [
                'title' => 'Best Practices for Timely Referral Processing',
                'slug' => 'best-practices-timely-referral-processing',
                'category_slug' => 'coordination-communication',
                'tag_slugs' => ['referrals', 'compliance', 'training'],
                'content_markdown' => $this->article14(),
                'featured' => false,
                'excerpt' => 'Daily checklist and best practices for agency focal persons to ensure timely referral processing, including prioritization, proactive communication, and caseload management.',
            ];

            $articles[] = [
                'title' => 'Communication Guidelines for Inter-Agency Coordination',
                'slug' => 'communication-guidelines-inter-agency',
                'category_slug' => 'coordination-communication',
                'tag_slugs' => ['referrals', 'escalation', 'compliance'],
                'content_markdown' => $this->article15(),
                'featured' => false,
                'excerpt' => 'Professional communication standards for inter-agency coordination including referral comments, escalation protocols, and conflict resolution procedures.',
            ];

            // === Section E: System Administrator Guides ===

            $articles[] = [
                'title' => 'Managing the Helpdesk Knowledge Base',
                'slug' => 'managing-helpdesk-knowledge-base',
                'category_slug' => 'system-config',
                'tag_slugs' => ['training', 'troubleshooting', 'onboarding'],
                'content_markdown' => $this->article16(),
                'featured' => false,
                'excerpt' => 'Guide for administrators on managing the helpdesk knowledge base including creating articles with markdown, organizing by categories and tags, and managing revisions.',
            ];

            $articles[] = [
                'title' => 'Understanding and Using the Audit Log',
                'slug' => 'understanding-using-audit-log',
                'category_slug' => 'user-account-management',
                'tag_slugs' => ['troubleshooting', 'compliance'],
                'content_markdown' => $this->article17(),
                'featured' => false,
                'excerpt' => 'Guide to the system audit log covering what events are recorded, how to filter and search logs, and how to use audit data for security monitoring and compliance.',
            ];

            $articles[] = [
                'title' => 'Configuring System Settings and AI Chatbot',
                'slug' => 'configuring-system-settings-ai-chatbot',
                'category_slug' => 'system-config',
                'tag_slugs' => ['troubleshooting', 'onboarding'],
                'content_markdown' => $this->article18(),
                'featured' => false,
                'excerpt' => 'Guide for administrators on configuring system settings including OTP debug mode, session timeout, file upload limits, and the AI chatbot provider setup.',
            ];

            $articles[] = [
                'title' => 'Monitoring Queue Jobs and System Health',
                'slug' => 'monitoring-queue-jobs-system-health',
                'category_slug' => 'system-config',
                'tag_slugs' => ['troubleshooting', 'onboarding', 'compliance'],
                'content_markdown' => $this->article19(),
                'featured' => false,
                'excerpt' => 'Guide for administrators on monitoring queue health, managing failed jobs, checking system health metrics, and maintaining optimal system performance.',
            ];

            // === Section F: General / Reference ===

            $articles[] = [
                'title' => 'Privacy and Data Protection in One Window Bayanihan',
                'slug' => 'privacy-data-protection-owb',
                'category_slug' => 'faq',
                'tag_slugs' => ['compliance', 'documents'],
                'content_markdown' => $this->article20(),
                'featured' => false,
                'excerpt' => 'Overview of data privacy and protection practices in the system under RA 10173, including data collected, encryption standards, access controls, and user rights.',
            ];

            $articles[] = [
                'title' => 'Glossary of Terms',
                'slug' => 'glossary-of-terms',
                'category_slug' => 'faq',
                'tag_slugs' => ['training', 'onboarding'],
                'content_markdown' => $this->article21(),
                'featured' => false,
                'excerpt' => 'Comprehensive glossary of terms, acronyms, and definitions used throughout the One Window Bayanihan system.',
            ];

            $articles[] = [
                'title' => 'Troubleshooting Common Issues',
                'slug' => 'troubleshooting-common-issues',
                'category_slug' => 'faq',
                'tag_slugs' => ['troubleshooting', 'onboarding'],
                'content_markdown' => $this->article22(),
                'featured' => false,
                'excerpt' => 'Solutions for common issues including login problems, OTP delivery failures, document upload errors, dashboard loading issues, and notification troubleshooting.',
            ];

            // --- Insert/update articles ---
            foreach ($articles as $articleData) {
                $articleId = (string) Str::uuid();

                // Pre-validate all tag_slugs exist in $tags
                $missingTags = array_diff($articleData['tag_slugs'], array_keys($tags));
                if (! empty($missingTags)) {
                    $this->command->warn("Skipping article '{$articleData['title']}' — missing tag(s): ".implode(', ', $missingTags));

                    continue;
                }

                $tagIds = array_map(fn ($s) => $tags[$s], $articleData['tag_slugs']);
                $categoryId = $categories[$articleData['category_slug']] ?? null;

                $existing = DB::table('helpdesk_articles')->where('slug', $articleData['slug'])->where('is_deleted', false)->first();
                if ($existing) {
                    DB::table('helpdesk_articles')
                        ->where('id', $existing->id)
                        ->update([
                            'category_id' => $categoryId,
                            'title' => $articleData['title'],
                            'content_markdown' => $articleData['content_markdown'],
                            'excerpt' => $articleData['excerpt'],
                            'featured' => $articleData['featured'],
                            'updated_at' => $now,
                        ]);
                    $this->command->info("Updated article: {$articleData['title']}");

                    continue;
                }

                // Check for soft-deleted articles with the same slug — skip to avoid
                // unique constraint violation on INSERT, and do NOT resurrect.
                $deleted = DB::table('helpdesk_articles')->where('slug', $articleData['slug'])->where('is_deleted', true)->first();
                if ($deleted) {
                    $this->command->warn("Skipping article '{$articleData['title']}' — soft-deleted article exists with slug '{$articleData['slug']}'");

                    continue;
                }

                DB::table('helpdesk_articles')->insert([
                    'id' => $articleId,
                    'title' => $articleData['title'],
                    'slug' => $articleData['slug'],
                    'content_markdown' => $articleData['content_markdown'],
                    'excerpt' => $articleData['excerpt'],
                    'category_id' => $categoryId,
                    'status' => 'published',
                    'featured' => $articleData['featured'],
                    'author_id' => $authorId,
                    'published_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->command->info("Created article: {$articleData['title']}");

                foreach ($tagIds as $tagId) {
                    DB::table('helpdesk_article_tag')->insert([
                        'article_id' => $articleId,
                        'tag_id' => $tagId,
                    ]);
                }

                DB::table('helpdesk_article_revisions')->insert([
                    'id' => (string) Str::uuid(),
                    'article_id' => $articleId,
                    'title' => $articleData['title'],
                    'content_markdown' => $articleData['content_markdown'],
                    'excerpt' => $articleData['excerpt'],
                    'edited_by' => $authorId,
                    'edit_notes' => 'Initial version',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    // =========================================================================
    // Section A: Getting Started Guides
    // =========================================================================

    private function article1(): string
    {
        return '# Getting Started for Case Managers

Welcome to the One Window Bayanihan system! This guide will help you get started as a Case Manager and walk you through your daily workflow.

## Step 1: Logging In

1. Navigate to the system login page
2. Enter your **email address** and **password**
3. Check your email for the **6-digit OTP code** sent to your registered email
4. Enter the OTP code on the verification screen
5. You are now logged in and redirected to the **Dashboard**

> **Note:** OTP codes expire after 5 minutes. If yours expires, click **"Resend OTP"** to get a new one.

## Step 2: Dashboard Overview

The Dashboard is your command center. It shows:

- **KPI Stat Cards** at the top: total open cases, pending referrals, completed this month, and overdue referrals
- **Recent Cases List**: the most recently updated cases with status indicators
- **Pending Referrals**: referrals awaiting agency action
- **Case Trends Chart**: a visual overview of case volume over time

Take a moment to study each widget. The dashboard updates in real time as cases and referrals are processed.

## Step 3: Navigation Sidebar

The sidebar on the left gives you access to:

| Menu Item | Description |
|-----------|-------------|
| Dashboard | Your main monitoring hub |
| Cases | Full list of all cases with filtering and search |
| Referrals | All referrals sent to agencies |
| Overdue Referrals | Referrals past their SLA deadline |
| Reports | Generate case and referral reports |

## Step 4: Creating Your First Case

1. Click **"Cases"** in the sidebar
2. Click the **"New Case"** button
3. Fill out the intake form with the following sections:
   - **OFW Information**: full name, date of birth, nationality, contact number, email address
   - **Address Information**: current address, province, city/municipality
   - **Employment Information**: employer name, position, industry, country of employment
   - **Next of Kin**: name, relationship, contact number of a family member
   - **Case Summary**: detailed description of the concern or issue
   - **Documents**: upload supporting documents (PDF, JPG, or PNG, max 10MB each)
4. Click **"Save"** to create the case

The system generates a **Case Number** (internal) and a **Tracker Number** (public-facing, format OWBAP-XXXXXXX).

## Step 5: Viewing Case Details and Timeline

Open any case to see:

- **Case Information**: all details from the intake form
- **Timeline**: a chronological log of all activities, status changes, and milestones
- **Referrals**: referral records sent to partner agencies
- **Documents**: uploaded files organized by type

## Step 6: Creating a Referral

1. Open the case you want to refer
2. Click **"Create Referral"**
3. Select the **agency** and **service** needed
4. Write detailed referral notes explaining what is required
5. Attach relevant case documents
6. Click **"Submit"** — the agency receives an automatic notification

## Daily Workflow Routine

A productive daily routine looks like this:

1. **Start with the dashboard** — review new cases and pending referrals
2. **Process new intake** — review and categorize new cases
3. **Check referral updates** — see if agencies have responded
4. **Follow up on overdue items** — contact agencies or escalate
5. **Update case records** — add notes, upload new documents
6. **End with a dashboard review** — confirm everything is on track

## Tips for Efficiency

- Use the **search and filter** features to quickly locate cases
- Set aside time each morning for **proactive case review**
- Keep case notes **concise and factual** — they form the audit trail
- Use **referral milestone updates** to track agency progress
- Flag cases needing attention using the **priority indicators**';
    }

    private function article2(): string
    {
        return '# Getting Started for Agency Focal Persons

Welcome to the One Window Bayanihan system! This guide will help you get started as an Agency Focal Person handling referrals from DMW Region VII.

## Step 1: Logging In

1. Navigate to the system login page
2. Enter your **email address** and **password**
3. Check your email for the **6-digit OTP code**
4. Enter the OTP code to complete authentication
5. You arrive at your **Agency Dashboard**

## Step 2: Dashboard Overview

Your agency-specific dashboard shows:

- **Referrals Received**: total referrals sent to your agency
- **In Progress**: referrals currently being processed
- **Completed This Month**: successfully closed referrals
- **Overdue**: referrals past the SLA processing time
- **Recent Referrals List**: newest referrals requiring action

## Step 3: Viewing Incoming Referrals

1. Click **"Referrals"** in the sidebar
2. The list shows all referrals sent to your agency
3. Use filters to view by status: **Pending**, **Processing**, **Completed**, or **Rejected**
4. Click any referral to view full details including case context and attached documents

## Step 4: Accepting or Rejecting a Referral

When you receive a new referral:

1. Open the referral details
2. Review the **referral notes** and **attached documents**
3. Decide whether your agency can handle the request
4. Click **"Accept"** to start processing, or **"Reject"** to decline

> **Important:** If rejecting, you **must** provide a reason in the comments. This helps the Case Manager reassign the referral appropriately.

## Step 5: Processing Referrals and Adding Milestones

While processing a referral:

1. Update the status to **PROCESSING** when work begins
2. Add **milestone entries** at each significant progress point:
   - Initial assessment completed
   - Client interview conducted
   - Documents verified
   - Service rendered
3. If more information is needed, set status to **FOR COMPLIANCE** and specify what is required

## Step 6: Updating Referral Status

The valid status flow is:

| From | To | When |
|------|-----|------|
| PENDING | PROCESSING | Work has started |
| PROCESSING | FOR COMPLIANCE | More info needed from client |
| FOR COMPLIANCE | PROCESSING | Compliance received |
| PROCESSING | COMPLETED | All services rendered |
| Any | REJECTED | Agency cannot fulfill |

Each status change requires a brief note explaining the reason.

## Step 7: Communicating with Case Managers

Use the **comments section** on each referral to communicate:

- Ask clarifying questions about the referral
- Provide progress updates
- Flag any issues or delays
- Confirm completion details

Comments support threaded replies for organized conversations.

## Step 8: Managing Service Offerings

Keep your agency profile accurate:

1. Go to **"Services"** in the sidebar
2. Review your list of offered services
3. Update **processing time estimates** (SLA) as needed
4. Deactivate services your agency is currently unable to provide

## Tips for Success

- Check the dashboard **at least twice daily** for new referrals
- Respond to new referrals **within 24 hours** (accept or reject)
- Add milestones for **every significant action** taken
- Communicate **delays proactively** — do not wait for the Case Manager to ask
- Keep your service listings **accurate and up to date**';
    }

    private function article3(): string
    {
        return '# Getting Started for System Administrators

Welcome to the One Window Bayanihan system! This guide covers the administrative functions you need to manage the platform effectively.

## Step 1: Logging In

Administrator accounts may have additional security:

1. Your IP address may need to be on the **approved whitelist**
2. Enter your **email** and **password**
3. Complete the **OTP verification** step
4. You are logged in with full administrative access

## Step 2: User Management

Navigate to **Admin > Users** to manage all system users.

### Creating a New User

1. Click **"New User"**
2. Fill in the required fields:
   - **Name** — full name of the user
   - **Email** — must be unique in the system
   - **Password** — minimum 8 characters
   - **Role** — CASE_MANAGER, AGENCY, or ADMIN
   - **Agency** — required only for AGENCY role users
3. Click **"Save"** to create the account

### Managing Existing Users

- **Edit** user details as needed
- **Deactivate** accounts for users on leave or reassigned
- Use deactivation rather than deletion to preserve audit trails

## Step 3: Agency Management

Navigate to **Admin > Agencies** to register and configure partner agencies.

1. Click **"New Agency"**
2. Enter the agency name, short code, and contact information
3. Assign at least one user with the **AGENCY** role
4. The agency can now receive referrals from Case Managers

## Step 4: Service Management

Services define what each agency can handle:

1. Go to **Admin > Services**
2. Add a new service with:
   - **Service name** — clear and descriptive
   - **Agency** — which agency provides this service
   - **Processing days** — SLA target for completion
3. Update or deactivate services as agency capacity changes

## Step 5: System Settings

Navigate to **Admin > System Settings** to configure:

| Setting | Purpose | Recommendation |
|---------|---------|----------------|
| OTP Debug Mode | Auto-fills OTP in dev | Enable only in development |
| Session Timeout | User session duration | Default 120 minutes |
| File Upload Limit | Max document size | 10MB |
| Allowed File Types | Accepted formats | PDF, JPG, PNG |

> **Warning:** Never enable OTP Debug Mode in production. It bypasses real OTP verification.

## Step 6: Helpdesk CMS Overview

The system includes a knowledge base for users:

- **Articles** — markdown-based help content with live preview
- **Categories** — organize articles by topic
- **Tags** — cross-reference articles by keyword

Navigate to **Admin > Helpdesk** to manage content. You can create, edit, preview, and publish articles. Each edit creates a revision for rollback capability.

## Step 7: Audit Log Basics

Every action in the system is logged:

- User logins and logouts
- Case creation and updates
- Referral processing
- Document access
- Configuration changes

Use the **Audit Log** page to review activity. Filter by action type, module, user, or date range. Regularly review logs for unusual access patterns.

## Step 8: Maintenance Tasks

- Run `php artisan queue:listen` to process background jobs
- Run `php artisan cache:clear` if configuration changes do not take effect
- Monitor the `failed_jobs` table for queue errors
- Ensure `php artisan storage:link` has been run for file uploads';
    }

    // =========================================================================
    // Section B: OFW-Focused Guides
    // =========================================================================

    private function article4(): string
    {
        return '# Using the Public Tracking Portal

The public tracking portal lets you monitor your case status online without creating an account. You only need your tracker number and access to your registered mobile number or email.

## Step 1: Navigating to the Tracker

1. Open your browser and go to the One Window Bayanihan homepage
2. Click **"Track Case"** or navigate directly to the `/track` page
3. You will see a simple form with two fields

## Step 2: Entering Your Tracker Number

Your tracker number follows this format: **OWBAP-XXXXXXX**

Where XXXXXXX is a unique 7-digit number assigned when your case was created.

> **Where to find it:** Your tracker number was sent to you via SMS and email when the case was submitted. Check your messages or ask the Case Manager who handled your intake.

## Step 3: Requesting an OTP

1. Enter your tracker number
2. Click **"Request OTP"**
3. A 6-digit code is sent to your registered **mobile number** and **email address**
4. The OTP is valid for **5 minutes**

> **Tip:** If you do not receive the OTP, check your spam folder or click **"Resend OTP"** after 5 minutes.

## Step 4: Entering the OTP Code

1. Enter the 6-digit code in the verification field
2. Click **"Verify"**
3. You are now granted access to view your case information

## Step 5: Viewing Case Status

The tracking page displays:

- **Current Status** with a color-coded indicator:
  - **OPEN** (blue) — your case has been received
  - **PROCESSING** (yellow) — a Case Manager is working on it
  - **FOR COMPLIANCE** (orange) — additional information is needed from you
  - **CLOSED** (green) — your case has been resolved
- **Date Submitted** — when the case was created
- **Last Updated** — most recent activity date

## Step 6: Understanding the Public Timeline

The timeline shows significant events in your case:

- Case created
- Assigned to a Case Manager
- Referred to a partner agency
- Status changes
- Case resolved

Some internal notes are not visible to the public. Only milestone events and status changes are shown.

## What Each Status Means for You

| Status | What Is Happening | What You Should Do |
|--------|-------------------|--------------------|
| OPEN | Your case is being reviewed | Wait for initial contact from a Case Manager |
| PROCESSING | Your case is actively being worked on | Respond promptly to any requests |
| FOR COMPLIANCE | More documents or information needed | Check what is needed and submit quickly |
| CLOSED | Your case has been resolved | Provide feedback if you wish |

## Need Help?

If you have trouble with the tracking portal, contact the DMW Region VII hotline or visit the office in person.';
    }

    private function article5(): string
    {
        return '# Providing Feedback on Your Case

Your feedback helps DMW Region VII improve the quality of services provided to OFWs and their families.

## When Can You Give Feedback?

Feedback becomes available **after your case is closed**. Once the Case Manager marks the case as CLOSED, you will receive a notification with a link to the feedback form.

## Accessing the Feedback Form

1. Click the link in your notification email or SMS
2. Alternatively, go to the **Track Case** page and look for the feedback prompt
3. The form opens with a series of rating questions

## Overall Satisfaction Rating

Rate your overall experience on a scale of **1 to 5**:

| Rating | Meaning |
|--------|---------|
| 1 | Very dissatisfied |
| 2 | Dissatisfied |
| 3 | Neutral |
| 4 | Satisfied |
| 5 | Very satisfied |

## SERVQUAL Service Quality Dimensions

The feedback form evaluates five dimensions of service quality:

### Tangibles
Physical facilities, equipment, and appearance of materials. Rate how the DMW offices, online portal, and communication materials met your expectations.

### Reliability
The ability to deliver the promised service accurately and dependably. Did the Case Manager follow through on commitments? Was the process consistent?

### Responsiveness
Willingness to help and provide prompt service. How quickly were your inquiries answered? Were staff members eager to assist?

### Assurance
Staff knowledge and courtesy, and their ability to inspire trust. Did you feel confident in the competence of the people handling your case?

### Empathy
Caring, individual attention provided to you. Did staff members understand your specific situation and treat you with compassion?

## Why Your Feedback Matters

- **Service improvement** — identifies areas where DMW and partner agencies can improve
- **Performance monitoring** — helps track Case Manager and agency performance
- **Accountability** — ensures service providers are meeting quality standards
- **Data-driven decisions** — helps leadership allocate resources where they are most needed

## Privacy of Your Feedback

Your feedback is kept confidential. It is used for statistical and quality improvement purposes only. Your personal information is not shared publicly with your responses.

> **Thank you:** By taking a few minutes to provide feedback, you are helping improve services for all OFWs and their families.';
    }

    private function article6(): string
    {
        return '# Understanding Case Statuses and Tracker Numbers

This guide explains the numbering system and status lifecycle used in the One Window Bayanihan system.

## Case Numbers vs Tracker Numbers

The system uses two different identifiers:

| Identifier | Format | Purpose | Who Sees It |
|------------|--------|---------|-------------|
| Case Number | UUID (internal) | Internal DMW record keeping | Case Managers and admins |
| Tracker Number | OWBAP-XXXXXXX | Public tracking | OFWs and the public |

The **Tracker Number** is specifically designed for public use. It is shorter, easier to communicate, and does not expose internal system identifiers.

## Case Lifecycle

Every case moves through these stages:

### 1. OPEN
The case has been submitted and is awaiting review. A Case Manager has not yet been assigned or initial assessment has not started.

### 2. PROCESSING
A Case Manager is actively working on the case. This may involve:
- Reviewing submitted documents
- Contacting the client for additional information
- Coordinating with partner agencies
- Creating referrals

### 3. FOR COMPLIANCE
The case is waiting for the OFW or another party to provide additional documents or information. The Case Manager has specified what is needed. The clock pauses until compliance is received.

### 4. CLOSED
The case has been resolved. All referrals are in terminal states (COMPLETED or REJECTED). Final documentation has been completed. The client may now provide feedback.

## Referral Statuses

When a case is referred to a partner agency, the referral follows its own lifecycle:

| Status | Meaning |
|--------|---------|
| PENDING | Awaiting agency acceptance |
| PROCESSING | Agency is actively working on the referral |
| FOR COMPLIANCE | Agency needs more information from the client |
| COMPLETED | Agency has rendered all required services |
| REJECTED | Agency declined the referral (with reason) |

## Milestones and Timeline

Every status change and significant action creates a **timeline entry**. This provides a complete, immutable history of the case. Both internal users and the public (via the tracking portal) can view the timeline, though internal notes remain confidential.

## Why This Matters for OFWs

- Your **Tracker Number** is your key to checking case status anytime
- **FOR COMPLIANCE** means action is needed from you — check what documents are required
- **CLOSED** means your case is resolved — look for the feedback invitation
- Each status change gives you visibility into how your case is progressing';
    }

    private function article7(): string
    {
        return '# OFW Assistance Services Available from DMW Region VII

DMW Region VII coordinates a wide range of assistance services for Overseas Filipino Workers through its network of partner agencies.

## Core Mandate of DMW Region VII

The Department of Migrant Workers (DMW) is the primary government agency responsible for protecting the rights and promoting the welfare of OFWs. Region VII (Central Visayas) serves OFWs from Cebu, Bohol, Negros Oriental, and Siquijor.

## Partner Agencies and Their Services

### OWWA (Overseas Workers Welfare Administration)

| Service | Description |
|---------|-------------|
| Welfare Assistance | Financial aid for OFWs in distress |
| Repatriation | Emergency return to the Philippines |
| Death and Disability Benefits | Compensation for work-related incidents |
| Education and Training | Scholarships for OFWs and dependents |
| Reintegration Programs | Livelihood and entrepreneurship support |

### DOLE (Department of Labor and Employment)

| Service | Description |
|---------|-------------|
| Legal Assistance | Free legal counsel and representation |
| Labor Standards Enforcement | Ensuring compliance with labor laws |
| Employment Facilitation | Job placement and referral services |
| Conciliation and Mediation | Resolving labor disputes |

### TESDA (Technical Education and Skills Development Authority)

| Service | Description |
|---------|-------------|
| Skills Training | Vocational courses for returning OFWs |
| Livelihood Training | Small business management skills |
| Certification | National competency assessments |

### DSWD (Department of Social Welfare and Development)

| Service | Description |
|---------|-------------|
| Social Services | Counseling and psychosocial support |
| Emergency Assistance | Food, transportation, and temporary shelter |
| Family Welfare | Support for OFW families |

### DOH (Department of Health)

| Service | Description |
|---------|-------------|
| Medical Assistance | Financial help for medical treatment |
| Health Insurance | PhilHealth coverage for OFWs |
| Mental Health Support | Counseling and psychiatric services |

## Types of Assistance Available

Through the One Window Bayanihan system, OFWs can access:

- **Legal Aid** — contract violations, illegal recruitment, labor disputes
- **Repatriation** — emergency return, transportation assistance
- **Medical Assistance** — hospitalization, medication, checkups
- **Welfare Support** — financial aid, food, temporary shelter
- **Livelihood Programs** — skills training, business startup help
- **Employment Services** — job matching, pre-employment requirements

## How to Access Services

1. **Submit a case** through the One Window Bayanihan portal
2. A Case Manager assesses your needs
3. The system **refers your case** to the appropriate agency
4. The agency processes your request and provides the service
5. You can **track progress** using your tracker number

> **Note:** You do not need to visit multiple offices. The system handles inter-agency coordination on your behalf.';
    }

    // =========================================================================
    // Section C: Case Manager Guides
    // =========================================================================

    private function article8(): string
    {
        return '# Case Documentation Best Practices

Proper documentation is the foundation of effective case management. This guide covers best practices for maintaining clear, complete, and audit-ready case records.

## Writing Effective Case Summaries

A good case summary is:

- **Clear** — use plain language that anyone can understand
- **Factual** — state what happened, not what you think might have happened
- **Chronological** — present events in the order they occurred
- **Complete** — include all relevant details without unnecessary information

### Case Summary Template

```
Summary:
- Client reported that [issue] occurred on [date]
- Previous actions taken: [list]
- Current status: [what is happening now]
- Required next steps: [what needs to happen]
```

## Organizing Uploaded Documents

Follow these conventions for document management:

### Naming Convention
Use a consistent format: `[DocumentType]_[ClientName]_[Date].[ext]`

Examples:
- `Passport_Santos_20260115.pdf`
- `EmploymentContract_Cruz_20260201.pdf`
- `MedicalCertificate_Reyes_20260310.pdf`

### Document Categories
Organize documents by type:

- **Identification** — passport, government ID, birth certificate
- **Employment** — contract, certificate of employment, pay slips
- **Evidence** — photos, correspondence, supporting documents
- **Reports** — assessment reports, referral outcomes
- **Correspondence** — letters, official communications

### Versioning
When a document is updated:
1. Upload the new version with the updated date in the filename
2. Keep the previous version for the audit trail
3. Add a note explaining what changed and why

## Required vs Optional Documentation

| Case Type | Required | Optional |
|-----------|----------|----------|
| Employment Issue | Employment contract, ID | Photos, correspondence |
| Repatriation | Travel documents, request form | Medical certificate |
| Welfare Concern | Client statement, ID | Supporting evidence |
| Legal Assistance | Complaint affidavit, evidence | Witness statements |

## Maintaining a Complete Audit Trail

- All case notes are **append-only** — never delete or edit entries
- Each entry is **timestamped** and attributed to the user who created it
- Use **descriptive notes** — "Called client to confirm address" instead of "Follow-up call"
- Record all **decisions and rationale** — why a particular action was taken

## Common Documentation Pitfalls

| Pitfall | Why It Matters | How to Avoid |
|---------|---------------|--------------|
| Incomplete information | Delays processing | Use checklists |
| Unverified claims | Weakens the case | Always verify facts |
| Vague descriptions | Hard to understand | Be specific |
| Missing dates | Breaks the timeline | Always include dates |
| Opinion instead of fact | Compromises objectivity | State facts, separate opinion |

## Cross-Referencing with Milestones

Each case milestone should reference supporting documents:

- When a referral is created, link to the referral document
- When documents are reviewed, note which documents and key findings
- When a decision is made, reference the evidence that supported it

This creates a clear chain of evidence that supports every action taken on the case.';
    }

    private function article9(): string
    {
        return '# Using the Dashboard for Daily Monitoring

The dashboard is your primary tool for staying on top of your caseload. This guide explains how to use each section effectively.

## Understanding KPI Stat Cards

The top of the dashboard displays four key metrics:

| KPI | What It Shows | Why It Matters |
|-----|---------------|----------------|
| Open Cases | Total active cases assigned to you | Workload volume |
| Pending Referrals | Referrals awaiting agency action | Items needing follow-up |
| Completed This Month | Cases closed this calendar month | Productivity tracking |
| Overdue Referrals | Referrals past their SLA deadline | Items needing escalation |

Each card shows the current count and may include a trend indicator (up or down compared to the previous period).

## Reviewing Recent Cases

Below the KPIs is a list of recently updated cases:

- Each row shows the **tracker number**, **client name**, **status**, and **last updated** time
- Click any row to open the case details
- Use the **"View All Cases"** link to open the full case list with advanced filters

## Monitoring Pending Referrals

The pending referrals section is critical for proactive case management:

1. Review each pending referral in the list
2. Check how long it has been waiting
3. If approaching the SLA deadline, contact the agency focal person
4. Use the **reminder** function to prompt agency action

## Identifying Overdue Referrals

Overdue referrals are highlighted with color indicators:

- **Yellow** — approaching SLA deadline (within 24 hours)
- **Orange** — past SLA deadline (1-3 days overdue)
- **Red** — significantly overdue (more than 3 days past SLA)

Click on any overdue referral to see details and take action.

## Interpreting the Case Trends Chart

The chart at the bottom of the dashboard shows case activity over time:

- **X-axis**: time (days, weeks, or months)
- **Y-axis**: case count
- Multiple colored lines for different metrics (new cases, closed cases, referrals)

Use the chart to identify:
- Peak periods for new cases
- Trends in case resolution
- Seasonal patterns that may affect workload

## Setting Up a Daily Workflow

A structured daily routine maximizes your effectiveness:

### Morning (First 30 Minutes)
1. Review the **dashboard KPIs** for an overview
2. Check **new cases** assigned overnight
3. Review **pending referrals** for updates

### Mid-Day Check-In
1. Process any **new referrals** received from agencies
2. Update **case records** with morning activities
3. Address any **urgent items**

### End of Day (Last 15 Minutes)
1. Review **overdue items** and plan follow-up
2. Update case notes for the day
3. Plan priorities for the next day

## Customizing Your View

Some dashboard elements can be customized:

- **Date range** — adjust the trends chart period
- **Sort order** — change how recent cases are sorted
- **Filters** — filter by case type, urgency, or agency';
    }

    private function article10(): string
    {
        return '# Case Closure Checklist and Procedures

Closing a case properly ensures complete documentation and a smooth transition for the client. Follow this checklist to avoid common errors.

## Pre-Closure Verification

Before closing any case, verify each of the following:

### 1. All Referrals Are in Terminal States

Check every referral created for this case:

| Status | Allow Closure? |
|--------|---------------|
| COMPLETED | Yes |
| REJECTED | Yes |
| PENDING | No — must be resolved first |
| PROCESSING | No — must be completed or reassigned |
| FOR COMPLIANCE | No — must be resolved first |

If any referral is not in a terminal state, contact the agency or reassign the referral before proceeding.

### 2. Client Confirmation Received

- Confirm that the client has been informed of the case outcome
- Document the client\'s acknowledgment (verbal confirmation is acceptable, written is preferred)
- If the client cannot be reached, document all attempts made

### 3. All Required Documents Are Uploaded

Ensure the case file contains:

- **Initial intake documents** — verified and complete
- **Referral documents** — referral forms and agency responses
- **Milestone records** — all significant actions documented
- **Final case summary** — outcome documentation

### 4. Final Case Summary Completed

Write a comprehensive closure summary including:

- Brief overview of the case
- Actions taken by DMW and partner agencies
- Outcome and resolution details
- Any pending follow-up items or recommendations
- Client feedback if available

## The Closure Process

1. Navigate to the **case details page**
2. Click the **"Close Case"** button
3. Read the confirmation dialog carefully
4. Confirm by clicking **"Yes, Close Case"**
5. The case status changes to **CLOSED**

> **Warning:** Closing a case is irreversible. Make sure all steps above are completed before confirming.

## Closing vs Archiving

| Action | What It Does | When to Use |
|--------|-------------|-------------|
| Close | Marks the case as resolved, no further action needed | Case is fully resolved |
| Archive | Hides the case from active lists but preserves all data | Case is closed and no longer needed in regular view |

After closing, the case is automatically archived after a set period.

## Post-Closure Procedures

### Client Feedback

Once closed, the client receives a notification inviting them to provide feedback:

- **SERVQUAL survey** — rates service quality across five dimensions
- Feedback is optional but encouraged
- Results are anonymized for quality reporting

### Documentation Review

- Verify the case file is complete
- Ensure all notes are finalized
- Confirm the audit trail is unbroken

### Performance Tracking

Closed cases contribute to monthly performance metrics:
- Cases closed per Case Manager
- Average processing time
- Client satisfaction scores
- Agency performance data

## Common Closure Errors

- Closing with **active referrals** — always resolve referrals first
- Missing **final summary** — write the summary before closing
- Forgetting to **notify the client** — inform them of the outcome
- Skipping **document verification** — ensure all files are uploaded';
    }

    private function article11(): string
    {
        return '# Managing Overdue Referrals

Overdue referrals can delay case resolution and affect client satisfaction. This guide covers how to identify, manage, and prevent overdue referrals.

## Accessing the Overdue Referral Dashboard

Navigate to **"Overdue Referrals"** in the sidebar. This page shows:

- All referrals past their SLA deadline
- Sortable by **days overdue**, **agency**, or **service type**
- Color-coded urgency indicators
- Quick action buttons for follow-up

## Understanding SLA Tracking

Each service has a defined **Service Level Agreement (SLA)** measured in processing days:

| SLA Status | Color | Meaning |
|------------|-------|---------|
| On Track | Green | Within SLA period |
| Approaching | Yellow | Less than 24 hours until deadline |
| Overdue | Orange | 1-3 days past deadline |
| Critical | Red | More than 3 days past deadline |

The SLA is calculated from the date the agency accepts the referral.

## Sending Reminder Notifications

1. Open the overdue referral
2. Click **"Send Reminder"**
3. The agency focal person receives an automatic notification
4. A log entry is added to the case timeline

> **Tip:** Send a reminder as soon as a referral approaches the SLA deadline, not after it becomes overdue.

## Escalation Procedures for Chronically Overdue Referrals

If a referral remains overdue despite reminders, follow the escalation ladder:

### Level 1: Direct Contact
Contact the agency focal person directly by phone or internal message. Clarify the reason for the delay and agree on a revised timeline.

### Level 2: Agency Supervisor
If the focal person is unresponsive, escalate to the agency supervisor. Provide a summary of the referral history and previous communication attempts.

### Level 3: DMW Coordinator
Notify the DMW coordinator responsible for agency relations. They can facilitate a resolution at the management level.

### Level 4: Director Review
For critical cases more than 10 days overdue, escalate to the DMW Region VII Director for formal intervention.

## Documenting Escalations

Every escalation step must be documented:

- Record the date and method of contact
- Note who was contacted and their response
- Update the referral notes with the outcome
- Attach any relevant correspondence

## Preventing Overdue Referrals

### Proactive Communication
- Introduce yourself to agency focal persons early
- Confirm receipt of referrals within 24 hours
- Check in mid-way through the SLA period

### Clear Referral Notes
Write referrals that set the agency up for success:

- Specify exactly what services are needed
- Include relevant case context
- Attach all supporting documents upfront
- State any deadlines or urgency clearly

### Realistic Deadlines
- Understand each agency\'s typical processing times
- Discuss timelines with the agency if unsure
- Build in buffer time for complex cases

## Monitoring Agency Performance

Use the dashboard to track agency performance trends:

- Average processing time per agency
- On-time completion rate
- Number of overdue referrals over time

Share this data with agency supervisors during regular coordination meetings. Use it to identify agencies that may need additional support or training.';
    }

    // =========================================================================
    // Section D: Agency Focal Person Guides
    // =========================================================================

    private function article12(): string
    {
        return '# Managing Your Agency Services Profile

Keeping your agency services profile accurate helps Case Managers send you the right referrals and prevents mismatched assignments.

## Accessing the Services Page

1. Log in to your agency account
2. Click **"Services"** in the sidebar
3. You will see a list of all services your agency currently offers

## Adding a New Service Offering

When your agency gains the capacity to offer a new service:

1. Click **"Add Service"**
2. Fill in the fields:
   - **Service name** — use clear, specific names (e.g., "Financial Assistance for Repatriated OFWs" rather than "Financial Help")
   - **Description** — explain what the service covers, eligibility criteria, and any limitations
   - **Processing days** — the estimated number of days to complete this service (this becomes the SLA)
3. Click **"Save"**

## Editing Existing Services

Keep service descriptions current:

1. Find the service in the list
2. Click the **edit** icon
3. Update the description, processing days, or any details
4. Click **"Save Changes"**

Review your services at least once a quarter. Remove outdated descriptions and update processing times based on actual performance.

## Setting Realistic Processing Time Targets

The **processing days** field sets the SLA for this service. Consider:

- Actual average completion time over the past 3 months
- Current staffing levels and caseload
- Complexity of the service
- Seasonal factors that might affect processing speed

> **Tip:** It is better to set a slightly longer SLA and deliver early than to set an aggressive SLA and frequently run overdue.

## Activating and Deactivating Services

When your agency cannot accept new referrals for a particular service:

1. Find the service in the list
2. Toggle the **active/inactive** switch
3. Deactivated services will not appear in Case Manager referral options

Reasons to temporarily deactivate a service:
- Staff shortage or training period
- High backlog that needs to clear
- Service temporarily unavailable
- Budget or resource constraints

## How Accurate Listings Improve Referral Matching

When services are accurate, Case Managers can:

- Find the right agency for each client need
- Set appropriate expectations for processing time
- Reduce rejected referrals due to mismatched capabilities
- Improve overall case resolution times

Accurate service listings benefit everyone: Case Managers work more efficiently, agencies receive relevant referrals, and clients get faster service.';
    }

    private function article13(): string
    {
        return '# Adding Milestones to Referrals: Complete Guide

Milestones create a detailed record of progress on each referral. They are append-only, meaning they cannot be edited or deleted once created.

## What Are Milestones?

Milestones are timestamped entries that record significant events or progress points during referral processing. Together they form a chronological timeline of all actions taken.

### Key Properties

| Property | Description |
|----------|-------------|
| Append-only | Once added, milestones cannot be edited or deleted |
| Timestamped | Each milestone is marked with the exact date and time |
| Attributed | The user who added the milestone is recorded |
| Visible | Both agency and Case Manager can view the timeline |

## When to Add Milestones

Add a milestone at each significant point in the referral lifecycle:

- **Referral accepted** — confirm the referral has been received and reviewed
- **Initial assessment** — document the preliminary evaluation
- **Client contact** — record interactions with the client
- **Document review** — note when documents are received and verified
- **Service rendered** — confirm the service has been provided
- **Status change** — whenever the referral status changes
- **Completion** — mark the referral as fully processed

## Writing Effective Milestone Titles

A good title is:

- **Action-oriented** — start with a verb (e.g., "Conducted", "Reviewed", "Processed")
- **Specific** — describe exactly what was done
- **Brief** — 5-10 words that summarize the action

Examples:
- "Conducted initial client interview"
- "Verified employment documents submitted"
- "Processed financial assistance request"
- "Completed final review of case file"

## Writing Useful Milestone Descriptions

The description provides context and detail:

### Include
- What was done
- Who was involved
- Any decisions made
- Next steps planned
- Relevant document references

### Avoid
- Vague statements like "Continued processing"
- Opinions or subjective assessments
- Personal comments about the client or agency

### Example

```
Title: Reviewed repatriation documents
Description: Verified passport, flight itinerary, and 
Repatriation Assistance Request Form submitted by the 
client. All documents are complete and compliant. 
Forwarded to OWWA for processing. Estimated completion: 
3 working days.
```

## Milestone Frequency Best Practices

- Add at least **one milestone per week** for active referrals
- Add a milestone **immediately after any status change**
- Add a milestone **after any client interaction**
- For long-running referrals, add regular **progress update** milestones

## Example Milestone Timeline

Here is what a complete milestone history might look like for a typical case:

```
Day 1 — Referral Accepted
  Initial review of case documents completed.

Day 2 — Client Interview Conducted
  Interviewed client via phone. Verified case details.
  Client requested financial assistance.

Day 3 — Documents Verified
  All required documents checked and approved.

Day 5 — Financial Assistance Processed
  Assistance amount approved and processed.
  Client notified of disbursement timeline.

Day 7 — Referral Completed
  All services rendered. Final documentation filed.
```

## Why Milestones Matter

- **Transparency** — Case Managers see exactly what is happening
- **Accountability** — creates a clear record of who did what and when
- **Audit trail** — provides evidence of proper processing
- **Performance tracking** — helps identify bottlenecks in workflows';
    }

    private function article14(): string
    {
        return '# Best Practices for Timely Referral Processing

Processing referrals promptly is essential for client satisfaction and effective inter-agency collaboration. This guide provides a daily checklist and strategies for staying on top of your workload.

## Daily Checklist

### Start of Day (First 30 Minutes)
1. Check your **dashboard** for new referrals received overnight
2. Review **pending items** that need action
3. Check for **overdue referrals** that require immediate attention
4. Prioritize **urgent cases** based on the referral notes

### Mid-Day (After Lunch)
1. Process any **new referrals** that arrived in the morning
2. Update **status changes** on active referrals
3. Add **milestone entries** for work completed
4. Respond to **comments** from Case Managers

### End of Day (Last 15 Minutes)
1. Review what was accomplished today
2. Plan the next day priorities
3. Ensure all status updates are reflected in the system
4. Document any delays or blockers

## Key Performance Standards

| Action | Target Timeline |
|--------|----------------|
| Accept or reject a new referral | Within 24 hours |
| Initial client contact | Within 48 hours of acceptance |
| Status update after any action | Same day |
| Response to Case Manager query | Within 24 hours |
| Milestone entry for significant progress | Same day |

## Accepting or Rejecting Referrals

When a new referral arrives:

1. Open the referral immediately to review details
2. Check if your agency can provide the requested service
3. If yes, **accept** and set status to PROCESSING
4. If no, **reject** with a clear reason — specify what the Case Manager should do next

> **Important:** Rejecting without a reason causes delays. The Case Manager needs to understand why so they can find the right agency.

## Prioritizing Urgent Referrals

Some referrals need faster processing:

- **Safety concerns** — cases involving abuse, threats, or danger
- **Legal deadlines** — court dates, contract expiration, visa expiry
- **Medical emergencies** — health-related cases needing immediate attention
- **High-priority flags** — set by the Case Manager in the referral notes

When you receive an urgent referral, acknowledge it within 2 hours and provide an estimated timeline.

## Communicating Delays Proactively

If you anticipate a delay, communicate early:

1. Inform the Case Manager as soon as you know
2. Explain the reason for the delay clearly
3. Provide a revised estimated completion date
4. Add a milestone entry documenting the update

> **Proactive communication builds trust.** Case Managers prefer an honest update over silence.

## Using Milestone Updates to Show Progress

Even if a referral is taking longer than expected, regular milestone updates demonstrate progress:

- "Completed initial assessment — awaiting client documents"
- "Received additional documents — starting review"
- "Review in progress — 50% complete"
- "Awaiting supervisor approval — expected tomorrow"

## Strategies for Managing High Caseloads

### Batch Similar Tasks
Group similar activities together: make all client calls in one block, process all document reviews in another.

### Use the Priority Matrix
| Urgent & Important | Not Urgent But Important |
|--------------------|--------------------------|
| Do immediately | Schedule for later today |
| **Urgent But Not Important** | **Neither Urgent Nor Important** |
| Delegate if possible | Do when time allows |

### Communicate Capacity
If your caseload is too high, discuss with your supervisor. It is better to flag capacity issues than to let referrals accumulate unattended.';
    }

    private function article15(): string
    {
        return '# Communication Guidelines for Inter-Agency Coordination

Effective communication between DMW Case Managers and Agency Focal Persons is critical for timely case resolution. This guide establishes professional standards for all interactions.

## Using Referral Comments for Structured Communication

Every referral has a **comments section** that serves as the primary communication channel:

### Best Practices for Comments

- **Start a new thread** for each distinct topic or issue
- **Keep comments professional and factual**
- **Tag the relevant person** when a response is needed
- **Close the loop** — confirm when an issue is resolved
- **Avoid casual conversation** — keep comments focused on the referral

### Example

```
[Case Manager]: Referral created for financial assistance. 
Client needs urgent support for repatriation. Estimated 
timeline: 5 working days. Please confirm receipt.

[Agency Focal]: Confirmed receipt. Initial assessment 
scheduled for tomorrow. Will update with findings.

[Agency Focal]: Assessment complete. Client qualifies 
for assistance. Processing now.

[Case Manager]: Thank you for the update. Please notify 
when disbursement is completed.
```

## Threaded Replies for Organized Conversations

When replying to a comment:

1. Click **"Reply"** on the specific comment
2. Your response is indented under the original comment
3. This keeps related messages together
4. Multiple topics can be discussed simultaneously without confusion

## When to Escalate to Phone or Email

Some situations require real-time communication:

| Situation | Channel | Reason |
|-----------|---------|--------|
| Urgent safety concern | Phone | Immediate action needed |
| Complex coordination | Phone or Video Call | Multiple parties need to discuss |
| System outage | Phone | Cannot use the platform |
| Repeated delays | Phone + Email | Formal discussion needed |
| Sensitive matters | Phone or In-Person | Confidentiality concerns |

After any phone or in-person conversation, **document the discussion** in the referral comments. Include the date, who was involved, key decisions, and agreed next steps.

## Professional Communication Standards

### Do
- Address colleagues respectfully
- Respond within agreed timelines
- Acknowledge receipt of messages
- Be concise and clear
- Use proper grammar and spelling

### Do Not
- Use informal language or slang
- Make personal remarks
- Discuss cases outside the system
- Share confidential information through unsecured channels
- Ignore or delay responses

## Mandatory Documentation of Decisions

Every decision related to a referral must be documented in the system:

- **Accept/reject decisions** — recorded automatically with your response
- **Status changes** — require a brief explanation
- **Escalation requests** — full documentation required
- **Client communications** — summary of discussion and outcomes

## Conflict Resolution

Disagreements may arise during coordination. Follow this process:

### Step 1: Direct Discussion
Discuss the issue directly with the other party. Share your perspective and listen to theirs. Most issues are resolved at this level.

### Step 2: Supervisor Involvement
If direct discussion does not resolve the issue, each party should involve their respective supervisor. The supervisors can facilitate a resolution.

### Step 3: DMW Director
For unresolved conflicts affecting case processing, escalate to the DMW Region VII Director for final resolution.

### Documentation
At each step, document:
- What the disagreement is about
- What steps have been taken to resolve it
- What was agreed or decided
- Any outstanding issues';
    }

    // =========================================================================
    // Section E: System Administrator Guides
    // =========================================================================

    private function article16(): string
    {
        return '# Managing the Helpdesk Knowledge Base

The helpdesk knowledge base provides self-service support content for all system users. This guide covers how to create, organize, and maintain articles.

## Accessing the Helpdesk CMS

Navigate to **Admin > Helpdesk** to access the content management system. The CMS has three main sections:

- **Articles** — the content items themselves
- **Categories** — organizational hierarchy
- **Tags** — cross-referencing keywords

## Creating a New Article

1. Click **"New Article"**
2. Enter the **title** — a clear, descriptive headline
3. Write the content in the **markdown editor**
4. The editor includes a **live preview** panel showing how the article will look
5. Assign a **category** from the dropdown
6. Select relevant **tags** for cross-referencing
7. Set **visibility** options:
   - **Public** — anyone can view (default)
   - **Authenticated** — logged-in users only
   - **Role Restricted** — specific roles only
8. Optionally mark as **featured** to highlight it on the helpdesk homepage
9. Click **"Save as Draft"** to continue editing later, or **"Publish"** to make it live

## Writing Effective Helpdesk Articles

### Structure

- Start with a **heading** that matches the article title
- Use **subheadings** to organize content into sections
- Include a **brief summary** at the beginning
- Use **numbered steps** for procedures
- Use **bullet lists** for items and options
- Use **tables** for structured data

### Style

- Write in **plain language** accessible to non-technical users
- Use **bold text** for UI elements and buttons
- Use `code formatting` for field names, file paths, and commands
- Keep paragraphs short and scannable
- Add **tips and warnings** in callout boxes

## Organizing with Categories and Tags

### Categories
Categories create the main navigation structure. Articles should be placed in the most specific category available. A category can have subcategories for more precise organization.

### Tags
Tags provide cross-referencing across categories. Add multiple tags to each article so users can find related content. Common tags include: `cases`, `referrals`, `tracking`, `compliance`, `training`.

## Previewing and Publishing Workflow

1. **Draft** — create and edit the article
2. **Preview** — use the live preview to check formatting
3. **Publish** — make the article visible to users
4. **Update** — edit published articles as needed (creates a revision)

## Managing Revisions

Every time an article is saved, a **revision** is created:

- Revisions preserve the complete article content at that point
- You can **roll back** to any previous revision
- Each revision shows **who made the change** and **when**
- Revisions include **edit notes** describing what changed

To roll back:
1. Open the article
2. Go to the **"Revisions"** tab
3. Find the revision you want to restore
4. Click **"Restore"** — the current content is replaced

## Viewing Article Feedback

Users can rate articles as helpful or not helpful:

1. Open the article in the CMS
2. View the **feedback count** (helpful vs unhelpful)
3. Review detailed comments if users have left them
4. Use feedback to identify articles that need improvement

## Maintenance Best Practices

- Review all articles **quarterly** for accuracy
- Update articles when system features change
- Archive outdated articles instead of deleting them
- Monitor feedback to identify gaps in coverage
- Cross-link related articles for easy navigation';
    }

    private function article17(): string
    {
        return '# Understanding and Using the Audit Log

The audit log records every significant action taken in the system, providing a complete trail of who did what and when.

## What the Audit Log Records

The system logs the following categories of events:

### Authentication Events
- **LOGIN** — successful user login
- **LOGOUT** — user logout
- **LOGIN_FAILED** — failed login attempt
- **OTP_SENT** — OTP code generated and sent
- **OTP_VERIFIED** — successful OTP verification

### Case Events
- **CASE_CREATED** — new case submitted
- **CASE_UPDATED** — case details modified
- **CASE_CLOSED** — case marked as resolved

### Referral Events
- **REFERRAL_CREATED** — referral sent to agency
- **REFERRAL_ACCEPTED** — agency accepted referral
- **REFERRAL_REJECTED** — agency rejected referral
- **REFERRAL_STATUS_CHANGED** — status updated
- **MILESTONE_ADDED** — milestone entry created

### Document Events
- **DOCUMENT_UPLOADED** — file added to a case
- **DOCUMENT_DOWNLOADED** — file accessed by a user
- **DOCUMENT_DELETED** — file removed

### Administration Events
- **USER_CREATED** — new user account created
- **USER_UPDATED** — user details changed
- **USER_DEACTIVATED** — account deactivated
- **SETTINGS_CHANGED** — system configuration updated

## Accessing Audit Logs

1. Navigate to **"Audit Logs"** in the sidebar (admin users only)
2. The page displays a paginated list of events in reverse chronological order
3. Each entry shows: **timestamp**, **user**, **action**, **module**, and **details**

## Filtering Audit Logs

Use filters to narrow down specific events:

### By Action Type
Select from: CREATE, UPDATE, DELETE, VIEW, LOGIN, LOGOUT, and others. This helps isolate specific kinds of activities.

### By Module
Filter by system module: Cases, Referrals, Users, Documents, Settings, Auth. This shows events related to a specific area.

### By User
Enter a user name or email to see all actions performed by that person.

### By Date Range
Set a start and end date to view events within a specific period.

## Understanding Audit Events in Context

Each audit entry contains:

| Field | Description |
|-------|-------------|
| Timestamp | Exact date and time of the action |
| User | Name and email of the person who performed the action |
| Action | The type of action (CREATE, UPDATE, DELETE, etc.) |
| Module | Which part of the system was affected |
| Target ID | The ID of the record that was affected |
| IP Address | The network address the action came from |
| User Agent | The browser or device used |
| Details | Additional context in JSON format |

## Security Monitoring Use Cases

### Detecting Unusual Access Patterns
- Multiple failed logins from the same IP
- Logins at unusual hours
- Access to records outside a user\'s normal scope
- Bulk downloads of documents

### Investigating Incidents
If a case record is modified unexpectedly:
1. Filter the audit log by that case ID
2. See every action taken on that record
3. Identify who made changes and when
4. Review the details of each change

### Compliance Verification
- Confirm that only authorized users access sensitive records
- Verify that required actions were performed within timelines
- Document that procedures were followed correctly

## Best Practices

- Review audit logs **weekly** for security monitoring
- **Export** logs periodically for long-term retention
- Investigate **unusual patterns** promptly
- Use audit data for **training** — identify common errors
- Retain logs according to **data privacy requirements**';
    }

    private function article18(): string
    {
        return '# Configuring System Settings and AI Chatbot

This guide covers the system settings available to administrators, including the AI chatbot configuration.

## Accessing System Settings

Navigate to **Admin > System Settings** to access the configuration panel.

## OTP Debug Mode

This setting controls whether OTP codes are auto-filled during development.

| Setting | Effect |
|---------|--------|
| Enabled | OTP field is auto-populated with a debug code — no real SMS/email sent |
| Disabled | Normal OTP behavior — real codes sent to user email |

> **Critical Warning:** Only enable OTP Debug Mode in local development environments. Never enable this in production. It bypasses all real OTP verification and creates a security vulnerability.

## Session Timeout Configuration

Controls how long a user session remains active without activity:

- **Default:** 120 minutes
- Adjust based on your security requirements
- Shorter timeouts (30-60 min) for sensitive environments
- Longer timeouts (up to 480 min) may be acceptable in controlled office settings

When a session expires, the user is automatically logged out and must re-authenticate.

## File Upload Limits

| Setting | Description | Recommended |
|---------|-------------|-------------|
| Max File Size | Largest single file allowed | 10MB |
| Allowed Types | Accepted file formats | PDF, JPG, PNG |

Files exceeding the size limit will be rejected with an error message. Ensure users are informed of these limits through the helpdesk documentation.

## AI Chatbot Configuration

The system includes an AI-powered chatbot that can answer user questions based on helpdesk articles.

### Provider Selection

Choose your AI provider:

- **OpenAI** — GPT-4 models, most capable but requires API key
- **Anthropic** — Claude models, strong on safety and reasoning
- **Custom** — connect to your own LLM endpoint

### API Key Management

- Enter the API key in the **masked input** field
- The key is stored encrypted in the database
- Once saved, the key is masked for security
- To change the key, enter a new value (the old one is overwritten)

### Chatbot Instruction Prompt

This **system message** defines how the chatbot behaves:

- Set the chatbot personality and tone
- Define what topics it can discuss
- Set boundaries on what it should not answer
- Instruct it to cite helpdesk articles as sources

Example prompt:

```
You are a helpful assistant for the One Window Bayanihan 
system. Answer questions about case management, referrals, 
and system features. Use the helpdesk articles as your 
source of information. If you do not know the answer, 
say so and direct the user to contact their supervisor 
or system administrator.
```

### Model Parameters

| Parameter | Purpose | Recommended |
|-----------|---------|-------------|
| Temperature | Controls randomness of responses (0.0-1.0) | 0.3 for factual answers |
| Token Limit | Maximum response length | 1024 tokens |

Lower temperature values (0.1-0.3) produce more focused, deterministic responses suitable for factual Q&A. Higher values (0.7-1.0) produce more creative responses.

## Testing Configuration Changes

After making configuration changes:

1. Click **"Save"** to apply changes
2. Test the affected feature
3. For OTP changes, log out and verify the login flow
4. For chatbot changes, open the chatbot and ask a test question
5. For upload limits, try uploading files of different sizes

## Configuration Best Practices

- Document all configuration changes in your internal change log
- Test changes in a staging environment first
- Set up monitoring alerts for critical settings changes
- Review all settings during quarterly system audits
- Keep API keys secure and rotate them periodically';
    }

    private function article19(): string
    {
        return '# Monitoring Queue Jobs and System Health

The One Window Bayanihan system uses a database-driven queue for background job processing. This guide covers how to monitor and maintain the queue and overall system health.

## Understanding the Queue System

The system uses `QUEUE_CONNECTION=database`, meaning all queued jobs are stored in the `jobs` database table. A worker process (`queue:listen`) picks up jobs and processes them in the background.

### What Uses the Queue

- **Email notifications** — OTP codes, case status updates
- **SMS notifications** — tracker numbers, alerts
- **Document processing** — file validation, storage uploads
- **Audit log entries** — some are queued for performance

## Checking Queue Health

The **failed_jobs** table stores any jobs that could not be processed after retries:

```
php artisan queue:failed
```

This lists all failed jobs with their ID, connection, queue, and failure reason.

## Monitoring Failed Jobs

### Viewing Failure Details

Each failed job record contains:
- The **exception message** explaining what went wrong
- The **job payload** with the data being processed
- The **failed_at** timestamp

### Common Failure Causes

| Issue | Typical Cause | Solution |
|-------|---------------|----------|
| Email sending failure | SMTP configuration issue | Check mail settings |
| Document upload failure | Storage connectivity | Verify storage config |
| Database deadlock | Concurrent job conflicts | Retry the job |
| Model not found | Record deleted before job ran | Check data integrity |

### Retrying Failed Jobs

To retry a specific failed job:

```
php artisan queue:retry <job_id>
```

To retry all failed jobs:

```
php artisan queue:retry all
```

## Restarting the Queue Worker

The queue worker runs continuously, processing jobs as they arrive. If it stops or becomes unresponsive:

1. Stop the current worker (Ctrl+C)
2. Start it again:

```
php artisan queue:listen
```

For production environments, use a process monitor (like Supervisor on Linux) to keep the worker running automatically.

## Cache and Session Management

The system uses `CACHE_STORE=database` for cache storage:

- OTP codes are stored in the `cache` table with TTL
- Session data is also stored in the database (if using database sessions)
- System settings are cached for performance

### Clearing the Cache

```
php artisan cache:clear
```

Use this command when:
- System configuration changes do not take effect
- Cached data appears stale
- After deploying updates

## Common System Health Checks

Run these checks regularly to ensure system health:

### 1. Database Connection
```
php artisan db:monitor
```
Verify the database is reachable and responsive.

### 2. Queue Worker Status
Check that the queue worker is running. If not, jobs will accumulate in the jobs table without being processed.

### 3. Storage Link
```
php artisan storage:link
```
Ensure the public storage symlink exists. Without it, uploaded files cannot be accessed.

### 4. Config Cache
If using config caching in production:
```
php artisan config:cache
```
Remember to clear and re-cache after configuration changes:
```
php artisan config:clear
```

### 5. Application Health
```
php artisan about
```
Displays the application version, environment, cache status, and other relevant information.

## Using php artisan queue:monitor

Laravel provides a queue monitor command:

```
php artisan queue:monitor redis:default,redis:default --max=100
```

For this system, use the database connection:

```
php artisan queue:monitor database:default
```

This command checks queue sizes and can trigger events when queues exceed thresholds.

## Setting Up Monitoring Alerts

For proactive monitoring:

1. **Check failed jobs daily** — review and retry as needed
2. **Set up a cron job** to restart the queue worker periodically
3. **Monitor disk space** — logs and uploads can consume space
4. **Check system logs** — look for error patterns

## Troubleshooting Queue Issues

| Symptom | Possible Cause | Check |
|---------|---------------|-------|
| Jobs not processing | Queue worker not running | Run queue:listen |
| Jobs repeatedly failing | Code error or dependency issue | Check failed_jobs table |
| Slow processing | High job volume or resource contention | Check server resources |
| Memory errors | Job memory leak | Increase memory limit or fix job';
    }

    // =========================================================================
    // Section F: General / Reference
    // =========================================================================

    private function article20(): string
    {
        return '# Privacy and Data Protection in One Window Bayanihan

The One Window Bayanihan system is committed to protecting the privacy and personal data of all users in compliance with the Data Privacy Act of 2012 (Republic Act 10173).

## Data Privacy Act of 2012 (RA 10173)

The Data Privacy Act protects the fundamental right to privacy of communication while ensuring the free flow of information. It establishes the principles for processing personal data and the rights of data subjects in the Philippines.

## What Personal Data the System Collects

### For OFWs and Clients
- Full name and date of birth
- Contact information (phone number, email address)
- Current and permanent address
- Employment information (employer, position, industry, country)
- Next of kin details (name, relationship, contact number)
- Case-related information and supporting documents
- Tracker number and case history

### For System Users
- Name and email address
- Role and agency affiliation
- Login activity and audit logs
- Actions performed within the system

## How Data Is Protected

### Encryption

| Stage | Technology | What It Protects |
|-------|-----------|------------------|
| In Transit | TLS 1.2+ | All data moving between your browser and our servers |
| At Rest | AES-256 | All data stored in the database |
| Backups | AES-256 | All backup copies of the data |

### Access Controls

The system implements multiple layers of access control:

**Role-Based Access Control (RBAC)**
Each user role has specific permissions:
- CASE_MANAGER — access to assigned cases and referrals
- AGENCY — access to referrals sent to their agency only
- ADMIN — full system access (audited)

**Lane Isolation**
Users can only see data relevant to their role and assignments:
- Case Managers see only their own cases
- Agency users see only referrals to their agency
- Admin access is fully logged and auditable

**Audit Trails**
Every access to sensitive data is recorded:
- Who accessed the record
- When it was accessed
- What action was taken
- From which IP address

## User Rights Under the DPA

As a data subject, you have the following rights:

| Right | Description | How to Exercise |
|-------|-------------|-----------------|
| Right to be Informed | Know what data is collected and why | See this article or contact DPO |
| Right to Access | Request a copy of your personal data | Submit a request to DMW DPO |
| Right to Correction | Correct inaccurate data | Contact your Case Manager |
| Right to Deletion Objection | Object to data processing in certain cases | Submit a formal request |
| Right to Data Portability | Receive your data in a portable format | Available upon request |

To exercise any of these rights, contact the DMW Region VII Data Protection Officer (DPO).

## Data Retention and Disposal

| Data Type | Retention Period | Disposal Method |
|-----------|-----------------|-----------------|
| Active case records | Until case closure + 5 years | Anonymized after retention |
| User accounts | Duration of employment/engagement | Deactivated, then deleted after 1 year |
| Audit logs | 3 years | Archived, then deleted |
| System backups | 30 days rolling | Overwritten automatically |

## Who Has Access to Your Data

- **DMW Case Managers** — processing your case
- **Agency Focal Persons** — only for referrals to their specific agency
- **System Administrators** — for maintenance and support (fully logged)
- **DMW DPO** — for privacy compliance and data subject requests

Your data is never shared with unauthorized third parties.

## Reporting Privacy Concerns

If you believe your privacy has been violated:

1. Contact the **DMW Region VII Data Protection Officer**
2. Email or visit the DMW office in person
3. You may also file a complaint with the **National Privacy Commission (NPC)**

> **The protection of your personal data is our priority. We are committed to handling your information with the utmost care and in full compliance with the law.**';
    }

    private function article21(): string
    {
        return '# Glossary of Terms

This glossary provides definitions for terms, acronyms, and abbreviations used throughout the One Window Bayanihan system.

## A

**ADMIN.** A system role with full administrative privileges. Administrators can manage users, agencies, services, system settings, and helpdesk content.

**Agency Focal Person.** A user assigned to a partner agency who receives and processes referrals from DMW Case Managers. The primary point of contact for inter-agency coordination.

**AGENCY (role).** The system role assigned to Agency Focal Persons. Users with this role can view referrals sent to their agency, update referral statuses, and add milestone entries.

**Append-only.** A data integrity principle where records can only be added, never modified or deleted. Case notes, audit logs, and milestone entries are all append-only.

**Audit Log.** A chronological record of all significant actions taken in the system, used for security monitoring and compliance verification.

## C

**Case Manager.** A DMW employee responsible for handling cases from intake through resolution. Case Managers create referrals, monitor progress, and maintain case documentation.

**Case Number.** An internal UUID identifier assigned to each case for DMW record-keeping purposes. Not visible to the public.

**CASE_MANAGER (role).** The system role assigned to DMW Case Managers.

## D

**DICT (Department of Information and Communications Technology).** The Philippine government agency responsible for ICT policy and implementation.

**DMW (Department of Migrant Workers).** The lead government agency for protecting the rights and promoting the welfare of Overseas Filipino Workers. Region VII covers Central Visayas.

**DOH (Department of Health).** The national health authority that provides medical assistance and health services to OFWs through the system.

**DOLE (Department of Labor and Employment).** The government agency providing legal assistance, labor standards enforcement, and employment facilitation services.

**DSWD (Department of Social Welfare and Development).** The agency providing social services, emergency assistance, and family welfare support.

## F

**FK (Foreign Key).** A database constraint that ensures referential integrity between related tables.

**Focal Person.** Short for Agency Focal Person. See Agency Focal Person.

## H

**Helpdesk.** The knowledge base system within One Window Bayanihan containing articles, guides, and FAQs for users.

## I

**Inertia.** The JavaScript framework used to build the single-page application frontend. Connects Laravel backend with React frontend.

**IP Whitelist.** A security measure that restricts access to specified IP addresses only. Used for administrator accounts.

## K

**KPI (Key Performance Indicator).** Measurable metrics displayed on the dashboard, including open cases, pending referrals, and completion rates.

## L

**Lane Isolation.** A security principle where users can only access data directly relevant to their role and assignments.

**Laravel.** The PHP framework powering the application backend.

## M

**MFA (Multi-Factor Authentication).** An authentication method requiring two or more verification factors. In this system, password + OTP.

**Milestone.** A timestamped, append-only entry recording significant events or progress points in a referral.

## N

**Next of Kin.** A designated family member or relative who can be contacted regarding an OFW case.

## O

**OFW (Overseas Filipino Worker).** A Filipino citizen working abroad who is eligible for DMW assistance services.

**OTP (One-Time Password).** A 6-digit code sent via email for multi-factor authentication. Expires after 5 minutes.

**OWWA (Overseas Workers Welfare Administration).** The agency providing welfare assistance, repatriation services, and reintegration programs for OFWs.

## P

**Pending.** A referral status indicating the referral has been sent but not yet acted upon by the agency.

**Processing.** A referral status indicating the agency is actively working on the referral.

## R

**RBAC (Role-Based Access Control).** An access control model where permissions are assigned based on user roles. See CASE_MANAGER, AGENCY, ADMIN.

**Referral.** A formal request from a Case Manager to a partner agency to provide specific services to a client.

**RLS (Row-Level Security).** A database security feature that restricts which rows a user can access based on their role and assignments.

## S

**SERVQUAL.** A service quality framework measuring five dimensions of service: Tangibles, Reliability, Responsiveness, Assurance, and Empathy.

**SLA (Service Level Agreement).** The target processing time for a service, measured in working days from referral acceptance to completion.

**Supabase.** The PostgreSQL database platform used for data storage.

## T

**Tailwind CSS.** The utility-first CSS framework used for the application user interface.

**Tracker Number.** A public-facing identifier in the format OWBAP-XXXXXXX used by clients to track case status without logging in.

## U

**UUID.** A Universally Unique Identifier used as the primary key for most database records in the system.';
    }

    private function article22(): string
    {
        return '# Troubleshooting Common Issues

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
   ```
   php artisan queue:listen
   ```
2. Check the **failed_jobs** table for any failed notification jobs
3. Retry any failed jobs:
   ```
   php artisan queue:retry all
   ```
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
4. Note the time the issue occurred — this helps with audit log investigation';
    }
}
