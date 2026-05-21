<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HelpdeskSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $authorId = DB::table('users')->where('role', 'ADMIN')->value('id');

        if (! $authorId) {
            return;
        }

        // --- Categories (idempotent: upsert by slug) ---
        $categories = [];
        $catData = [
            ['name' => 'OFW Assistance', 'slug' => 'ofw-assistance', 'description' => 'Guides and resources for Overseas Filipino Workers on case submission, document requirements, and rights.', 'icon' => 'badge', 'sort_order' => 0],
            ['name' => 'Case Management', 'slug' => 'case-management', 'description' => 'Standard operating procedures, workflows, and guidelines for Case Managers.', 'icon' => 'folder', 'sort_order' => 1],
            ['name' => 'Agency Partnership', 'slug' => 'agency-partnership', 'description' => 'Referral processing, status updates, and coordination guides for partner agencies.', 'icon' => 'handshake', 'sort_order' => 2],
            ['name' => 'System Administration', 'slug' => 'system-administration', 'description' => 'Configuration, account management, and troubleshooting for system administrators.', 'icon' => 'settings', 'sort_order' => 3],
            ['name' => 'Frequently Asked Questions', 'slug' => 'faq', 'description' => 'Common questions and answers about the One Window Bayanihan system.', 'icon' => 'help', 'sort_order' => 4],
        ];

        foreach ($catData as $c) {
            $existing = DB::table('helpdesk_categories')->where('slug', $c['slug'])->first();
            if ($existing) {
                $categories[$c['slug']] = $existing->id;
            } else {
                $id = (string) Str::uuid();
                DB::table('helpdesk_categories')->insert(array_merge($c, [
                    'id' => $id,
                    'is_active' => true,
                    'is_deleted' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
                $categories[$c['slug']] = $id;
            }
        }

        // --- Subcategories (idempotent: upsert by slug) ---
        $subcatData = [
            ['name' => 'Case Submission', 'slug' => 'case-submission', 'parent_slug' => 'ofw-assistance', 'icon' => 'add_circle', 'sort_order' => 0, 'description' => 'Guides on submitting cases and required documents.'],
            ['name' => 'OFW Rights & Protection', 'slug' => 'ofw-rights', 'parent_slug' => 'ofw-assistance', 'icon' => 'gavel', 'sort_order' => 1, 'description' => 'Information on OFW rights, repatriation, and legal assistance.'],
            ['name' => 'Case Manager Workflow', 'slug' => 'cm-workflow', 'parent_slug' => 'case-management', 'icon' => 'assignment', 'sort_order' => 0, 'description' => 'Standard procedures for case intake and management.'],
            ['name' => 'Referrals & Escalations', 'slug' => 'referrals-escalations', 'parent_slug' => 'case-management', 'icon' => 'swap_horiz', 'sort_order' => 1, 'description' => 'Guides on creating, processing, and escalating referrals.'],
            ['name' => 'Referral Processing', 'slug' => 'referral-processing', 'parent_slug' => 'agency-partnership', 'icon' => 'handshake', 'sort_order' => 0, 'description' => 'Step-by-step guides for agency focal persons.'],
            ['name' => 'Coordination & Communication', 'slug' => 'coordination-communication', 'parent_slug' => 'agency-partnership', 'icon' => 'forum', 'sort_order' => 1, 'description' => 'Guidelines for inter-agency coordination.'],
            ['name' => 'User & Account Management', 'slug' => 'user-account-management', 'parent_slug' => 'system-administration', 'icon' => 'manage_accounts', 'sort_order' => 0, 'description' => 'Managing user accounts, roles, and permissions.'],
            ['name' => 'System Configuration', 'slug' => 'system-config', 'parent_slug' => 'system-administration', 'icon' => 'tune', 'sort_order' => 1, 'description' => 'System settings, agencies, services, and troubleshooting.'],
        ];

        foreach ($subcatData as $sc) {
            $existing = DB::table('helpdesk_categories')->where('slug', $sc['slug'])->first();
            if ($existing) {
                $categories[$sc['slug']] = $existing->id;
            } else {
                $id = (string) Str::uuid();
                DB::table('helpdesk_categories')->insert([
                    'id' => $id,
                    'name' => $sc['name'],
                    'slug' => $sc['slug'],
                    'description' => $sc['description'],
                    'parent_id' => $categories[$sc['parent_slug']],
                    'icon' => $sc['icon'],
                    'sort_order' => $sc['sort_order'],
                    'is_active' => true,
                    'is_deleted' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $categories[$sc['slug']] = $id;
            }
        }

        // --- Tags (idempotent: upsert by slug) ---
        $tagNames = ['cases', 'referrals', 'documents', 'tracking', 'repatriation', 'compliance', 'escalation', 'training', 'troubleshooting', 'onboarding'];
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

        $articles[] = [
            'title' => 'How to Submit a New Case',
            'slug' => 'how-to-submit-a-new-case',
            'category_slug' => 'ofw-assistance',
            'tag_slugs' => ['cases', 'documents'],
            'visibility' => 'public',
            'target_roles' => null,
            'content_markdown' => $this->article1(),
            'featured' => true,
            'excerpt' => 'Step-by-step guide on how to submit a new case through the One Window Bayanihan portal, including document requirements and tracking instructions.',
        ];

        $articles[] = [
            'title' => 'Tracking Your Case Status Online',
            'slug' => 'tracking-your-case-status',
            'category_slug' => 'ofw-assistance',
            'tag_slugs' => ['tracking', 'cases'],
            'visibility' => 'public',
            'target_roles' => null,
            'content_markdown' => $this->article2(),
            'featured' => true,
            'excerpt' => 'Learn how to track your case status online using your tracking number and OTP verification.',
        ];

        $articles[] = [
            'title' => 'Required Documents for OFW Cases',
            'slug' => 'required-documents-ofw-cases',
            'category_slug' => 'case-submission',
            'tag_slugs' => ['documents', 'cases'],
            'visibility' => 'public',
            'target_roles' => null,
            'content_markdown' => $this->article3(),
            'featured' => false,
            'excerpt' => 'Complete list of required documents for different types of OFW cases, including employment, repatriation, and welfare concerns.',
        ];

        $articles[] = [
            'title' => 'Understanding Your Rights as an OFW',
            'slug' => 'understanding-your-rights-as-ofw',
            'category_slug' => 'ofw-rights',
            'tag_slugs' => ['documents', 'repatriation'],
            'visibility' => 'public',
            'target_roles' => null,
            'content_markdown' => $this->article4(),
            'featured' => false,
            'excerpt' => 'Overview of the fundamental rights of Overseas Filipino Workers before, during, and after employment.',
        ];

        $articles[] = [
            'title' => 'Case Intake and Processing Workflow',
            'slug' => 'case-intake-processing-workflow',
            'category_slug' => 'cm-workflow',
            'tag_slugs' => ['cases', 'onboarding', 'compliance'],
            'visibility' => 'public',
            'target_roles' => null,
            'content_markdown' => $this->article5(),
            'featured' => true,
            'excerpt' => 'Standard operating procedure for case intake, assessment, referral, management, and closure for Case Managers.',
        ];

        $articles[] = [
            'title' => 'Creating and Managing Referrals',
            'slug' => 'creating-managing-referrals',
            'category_slug' => 'referrals-escalations',
            'tag_slugs' => ['referrals', 'compliance'],
            'visibility' => 'public',
            'target_roles' => null,
            'content_markdown' => $this->article6(),
            'featured' => false,
            'excerpt' => 'Guide for Case Managers on creating, monitoring, and closing referrals to partner agencies.',
        ];

        $articles[] = [
            'title' => 'Escalation Procedures',
            'slug' => 'escalation-procedures',
            'category_slug' => 'referrals-escalations',
            'tag_slugs' => ['escalation', 'compliance'],
            'visibility' => 'public',
            'target_roles' => null,
            'content_markdown' => $this->article7(),
            'featured' => false,
            'excerpt' => 'Standard escalation procedures for Case Managers, including when to escalate and how to document escalation requests.',
        ];

        $articles[] = [
            'title' => 'Processing Received Referrals',
            'slug' => 'processing-received-referrals',
            'category_slug' => 'referral-processing',
            'tag_slugs' => ['referrals', 'compliance', 'onboarding'],
            'visibility' => 'public',
            'target_roles' => null,
            'content_markdown' => $this->article8(),
            'featured' => true,
            'excerpt' => 'Step-by-step guide for agency focal persons on receiving, processing, and closing case referrals.',
        ];

        $articles[] = [
            'title' => 'Updating Referral Status and Milestones',
            'slug' => 'updating-referral-status-milestones',
            'category_slug' => 'referral-processing',
            'tag_slugs' => ['referrals', 'tracking', 'compliance'],
            'visibility' => 'public',
            'target_roles' => null,
            'content_markdown' => $this->article9(),
            'featured' => false,
            'excerpt' => 'Instructions for updating referral statuses and adding milestone events to track case progress.',
        ];

        $articles[] = [
            'title' => 'Inter-Agency Coordination Guidelines',
            'slug' => 'inter-agency-coordination-guidelines',
            'category_slug' => 'coordination-communication',
            'tag_slugs' => ['referrals', 'compliance', 'escalation'],
            'visibility' => 'public',
            'target_roles' => null,
            'content_markdown' => $this->article10(),
            'featured' => false,
            'excerpt' => 'Guidelines for effective communication and coordination between DMW Case Managers and partner agency focal persons.',
        ];

        $articles[] = [
            'title' => 'User Account Management Guide',
            'slug' => 'user-account-management-guide',
            'category_slug' => 'user-account-management',
            'tag_slugs' => ['onboarding', 'troubleshooting'],
            'visibility' => 'public',
            'target_roles' => null,
            'content_markdown' => $this->article11(),
            'featured' => true,
            'excerpt' => 'Comprehensive guide for administrators on creating, managing, and deactivating user accounts across all roles.',
        ];

        $articles[] = [
            'title' => 'System Configuration and Settings',
            'slug' => 'system-configuration-settings',
            'category_slug' => 'system-config',
            'tag_slugs' => ['troubleshooting', 'onboarding'],
            'visibility' => 'public',
            'target_roles' => null,
            'content_markdown' => $this->article12(),
            'featured' => false,
            'excerpt' => 'Configuration reference for system settings, agency management, service management, and common troubleshooting.',
        ];

        $articles[] = [
            'title' => 'Frequently Asked Questions',
            'slug' => 'frequently-asked-questions',
            'category_slug' => 'faq',
            'tag_slugs' => ['cases', 'tracking', 'referrals', 'documents'],
            'visibility' => 'public',
            'target_roles' => null,
            'content_markdown' => $this->article13(),
            'featured' => true,
            'excerpt' => 'Commonly asked questions and answers about the One Window Bayanihan system for all user groups.',
        ];

        foreach ($articles as $articleData) {
            $articleId = (string) Str::uuid();
            $tagIds = array_map(fn ($s) => $tags[$s], $articleData['tag_slugs']);
            $categoryId = $categories[$articleData['category_slug']] ?? null;

            $existing = DB::table('helpdesk_articles')->where('slug', $articleData['slug'])->first();
            if ($existing) {
                // Update existing article (category may have changed to a subcategory)
                DB::table('helpdesk_articles')
                    ->where('id', $existing->id)
                    ->update([
                        'category_id' => $categoryId,
                        'title' => $articleData['title'],
                        'content_markdown' => $articleData['content_markdown'],
                        'excerpt' => $articleData['excerpt'],
                        'featured' => $articleData['featured'],
                        'visibility' => $articleData['visibility'],
                        'target_roles' => $articleData['target_roles'] ? json_encode($articleData['target_roles']) : null,
                        'updated_at' => $now,
                    ]);
                $this->command->info("Updated article: {$articleData['title']} -> category_id={$categoryId}");

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
                'visibility' => $articleData['visibility'],
                'target_roles' => $articleData['target_roles'] ? json_encode($articleData['target_roles']) : null,
                'author_id' => $authorId,
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->command->info("Created article: {$articleData['title']} -> category_id={$categoryId}");

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
    }

    private function article1(): string
    {
        return '# How to Submit a New Case

This guide walks you through submitting a new case through the One Window Bayanihan system.

## Step 1: Prepare Required Documents

Before submitting, ensure you have the following documents ready:

- **Government-issued ID** (Passport, UMID, Driver\'s License)
- **Proof of employment contract** (if applicable)
- **Supporting evidence** related to your concern (photos, correspondence, etc.)

## Step 2: Access the System

1. Visit the One Window Bayanihan portal
2. Click **"Submit a Case"** on the homepage
3. Select your **client type** (OFW or Next of Kin)

## Step 3: Fill Out the Form

Complete all required fields in the case submission form:

```
Required Information:
+-- Personal Details (name, contact, address)
+-- Employment Information
+-- Case Summary (detailed description)
+-- Supporting Documents (upload)
```

## Step 4: Submit and Track

After submission, you will receive a **tracking number** via SMS and email. Use this number to monitor your case status on the **Track Case** page.

> **Tip:** Keep your tracking number safe -- you will need it for all future inquiries about your case.';
    }

    private function article2(): string
    {
        return '# Tracking Your Case Status Online

Monitor your case status anytime using the online tracking feature.

## How to Track

1. Go to the **Track Case** page on the homepage
2. Enter your **tracking number** (format: OW-XXXXXXX)
3. Enter the **OTP code** sent to your registered mobile number
4. View your case status and timeline

## Understanding Statuses

| Status | Meaning |
|--------|---------|
| OPEN | Your case has been received and is being reviewed |
| PROCESSING | A Case Manager is working on your case |
| FOR COMPLIANCE | Additional documents or information are needed |
| CLOSED | Your case has been resolved |

## What You Will See

The tracking page displays:
- Current case status with color indicator
- Complete case timeline with milestone dates
- Assigned agency or Case Manager details
- Referral status updates (if applicable)

## Need Help?

If you have trouble tracking your case, contact our support team through the **Contact** page or call the DMW Region VII hotline.';
    }

    private function article3(): string
    {
        return '# Required Documents for OFW Cases

Ensure you have the following documents ready when submitting your case.

## Primary Documents

### For All Cases
- Valid Government-issued ID
- Proof of address
- Contact information (phone, email)

### For Employment-Related Cases
- Employment contract
- Certificate of employment
- Pay slips (last 3 months)
- Employment termination letter (if applicable)

### For Repatriation Cases
- Travel document / Passport
- Airline ticket or itinerary
- Repatriation Assistance Request Form

### For Welfare Concerns
- Medical certificate (if health-related)
- Police report (if abuse or crime-related)
- Affidavit of complaint (if applicable)

## Document Guidelines

- All documents must be **clear and readable**
- Upload files in **PDF, JPG, or PNG** format
- Maximum file size: **10MB per document**
- Label your documents clearly (e.g., "Passport_Name.pdf")

## After Submission

Once your case is filed, a Case Manager may request additional documents. You will be notified via SMS and email if any further documentation is needed.';
    }

    private function article4(): string
    {
        return '# Understanding Your Rights as an OFW

As an Overseas Filipino Worker, you are protected by Philippine laws and international labor standards.

## Your Basic Rights

### Before Employment
- Right to a **verified employment contract** (POEA-approved)
- Right to **pre-departure orientation and training**
- Right to **fair and transparent recruitment** practices

### During Employment
- Right to **fair wages** as specified in your contract
- Right to **safe working conditions**
- Right to **medical care and insurance**
- Right to **communication with family and authorities**

### Upon Return
- Right to **repatriation assistance**
- Right to **reintegration and livelihood support**
- Right to **file complaints** against erring employers or agencies

## Where to Seek Help

| Concern | Contact |
|---------|---------|
| Contract violations | DMW Region VII |
| Emergency repatriation | OWWA |
| Legal assistance | DOLE |

> **Remember:** You are never alone. The DMW and its partner agencies are here to protect your rights throughout your employment journey.';
    }

    private function article5(): string
    {
        return '# Case Intake and Processing Workflow

This document outlines the standard workflow for case intake and processing.

## Workflow Overview

```
New Case Created
      |
      v
Intake Review <-- > More Info Needed
      |
      v
Assessment and Classification
      |
      v
Referral to Agency (if needed)
      |
      v
Ongoing Case Management
      |
      v
Resolution and Closure
```

## Step-by-Step Process

### 1. Intake Review (Day 1)
- Verify client identity and documents
- Assess case urgency and priority
- Assign case category and tags

### 2. Assessment (Day 1-3)
- Review client full background
- Determine required services
- Identify which agency to refer to

### 3. Case Management (Ongoing)
- Monitor referral progress
- Document all case activities
- Communicate with client and agency

### 4. Closure
- Verify all services rendered
- Get client confirmation
- Complete final documentation

## Key Timelines

| Activity | Target |
|----------|--------|
| Initial response to client | Within 24 hours |
| Case assessment completed | Within 3 days |
| Referral processing | Within 5 days |
| Case resolution | As per case complexity |';
    }

    private function article6(): string
    {
        return '# Creating and Managing Referrals

Learn how to create and manage referrals to partner agencies.

## Creating a Referral

1. Open the case you want to refer
2. Click **"Create Referral"**
3. Select the **agency** and **service** needed
4. Add referral notes and instructions
5. Submit -- the agency will be notified

## Referral Statuses

| Status | Meaning |
|--------|---------|
| PENDING | Awaiting agency acceptance |
| PROCESSING | Agency is working on the referral |
| FOR_COMPLIANCE | Additional requirements needed |
| COMPLETED | Services rendered |
| REJECTED | Agency declined the referral |

## Best Practices

- **Be specific** about required services in referral notes
- **Attach relevant documents** to avoid back-and-forth
- **Set realistic deadlines** based on service complexity
- **Monitor regularly** -- use the dashboard for updates
- **Communicate** with agency focal persons directly for urgent matters';
    }

    private function article7(): string
    {
        return '# Escalation Procedures

This guide outlines when and how to escalate cases within the One Window Bayanihan system.

## When to Escalate

### Urgent Escalation
- Client safety at risk
- Legal deadlines approaching
- High-profile or sensitive cases
- Agency non-response beyond 72 hours

### Standard Escalation
- Case stuck in processing beyond timeline
- Disagreement between parties
- Complex multi-agency coordination needed

## Escalation Levels

| Level | Trigger | Action |
|-------|---------|--------|
| Level 1 | Incomplete information | Contact client/agency for details |
| Level 2 | Agency non-response | Send notice to agency supervisor |
| Level 3 | Inter-agency dispute | Refer to DMW Region VII Director |
| Level 4 | Policy/legal issue | Coordinate with DOLE/legal team |

## Documentation Requirements

Every escalation must include:
- Case number and summary
- Timeline of previous actions
- Reason for escalation
- Supporting documents
- Recommended resolution

> **Note:** All escalations are logged in the system audit trail for transparency and accountability.';
    }

    private function article8(): string
    {
        return '# Processing Received Referrals

Guide for agency focal persons on receiving and processing case referrals.

## Receiving a Referral

When a new referral arrives:
1. You will receive a **notification** via the system dashboard
2. Review the **referral details** and attached documents
3. **Accept** or **reject** the referral within 24 hours

## Processing Steps

```
Referral Received
      |
      v
Accept Referral
      |
      v
Assess Requirements
      |
      v
Provide Service <-- > Request More Info
      |
      v
Mark as Completed
```

## Status Updates

Keep the referring Case Manager informed by updating the referral status:

- Set to PROCESSING when work begins
- Set to FOR_COMPLIANCE if additional documents are needed
- Set to COMPLETED when all services are rendered
- Add milestone updates for significant progress points

## Tips for Efficient Processing

1. Check the dashboard **daily** for new referrals
2. Respond to compliance requests **within 48 hours**
3. Upload **proof of service** documents when closing referrals
4. Communicate **proactively** with the case manager';
    }

    private function article9(): string
    {
        return '# Updating Referral Status and Milestones

Learn how to keep referral records up to date with status changes and milestone entries.

## Changing Referral Status

1. Navigate to the referral details page
2. Click **"Update Status"**
3. Select the new status from the dropdown
4. Add a brief note explaining the change
5. Click **Save**

## Adding Milestones

Milestones track significant events in the referral lifecycle:

```
+-------------------------------------------+
| Milestones:                                |
| Referral Received                          |
| Initial Assessment Complete                |
| Client Interview Conducted                 |
| Documents Submitted for Processing         |
| Services Rendered                          |
| Case Closed                                |
+-------------------------------------------+
```

## Status Transitions

| From | To | When |
|------|-----|------|
| PENDING | PROCESSING | Work has started |
| PROCESSING | FOR_COMPLIANCE | More info needed |
| FOR_COMPLIANCE | PROCESSING | Compliance met |
| PROCESSING | COMPLETED | Services done |
| Any | REJECTED | Agency cannot fulfill |

> **Important:** Adding milestones creates a clear audit trail and helps all parties understand the case progress.';
    }

    private function article10(): string
    {
        return '# Inter-Agency Coordination Guidelines

Guidelines for effective coordination between DMW Region VII and partner agencies.

## Communication Channels

| Purpose | Channel | Response Time |
|---------|---------|---------------|
| Standard referral updates | System dashboard | Real-time |
| Document requests | System messaging | 24 hours |
| Urgent coordination | Phone / Email | 2 hours |
| Escalation | Formal memo | 48 hours |

## Roles and Responsibilities

### DMW Case Manager
- Initiates referrals
- Monitors progress
- Provides case context

### Agency Focal Person
- Processes referrals promptly
- Updates status regularly
- Communicates issues early

## Best Practices

1. **Respond promptly** to referral notifications
2. **Document everything** in the system
3. **Flag issues early** -- do not wait for deadlines
4. **Use milestones** to record progress
5. **Maintain professionalism** in all communications

## Conflict Resolution

If disagreements arise:
1. First, discuss directly between CM and focal person
2. If unresolved, escalate to agency supervisor
3. Final escalation to DMW Region VII Director';
    }

    private function article11(): string
    {
        return '# User Account Management Guide

Guide for system administrators on managing user accounts in the One Window Bayanihan system.

## User Roles

| Role | Description |
|------|-------------|
| CASE_MANAGER | DMW personnel who handle case processing and referrals |
| AGENCY | Partner agency focal persons who process referrals |
| ADMIN | System administrators with full access |

## Creating a New User

1. Navigate to **Admin > Users**
2. Click **"New User"**
3. Fill in the required fields:
   - Name
   - Email (must be unique)
   - Password (minimum 8 characters)
   - Role
   - Agency (for AGENCY role)
   - Contact number
4. Click **"Save"** -- the user will receive their credentials

## Account Statuses

| Status | Meaning |
|--------|---------|
| Active | User can log in and access the system |
| Inactive | User cannot log in (manual deactivation) |
| Deleted | Soft-deleted account (can be restored) |

## Best Practices

- Use **official email addresses** for all accounts
- Set **strong passwords** -- enforce complexity
- **Deactivate** instead of delete when possible
- **Review active users** quarterly
- **Document role changes** in the system';
    }

    private function article12(): string
    {
        return '# System Configuration and Settings

Reference guide for system administrators on configuring the One Window Bayanihan platform.

## System Settings

Access via **Admin > System Settings**:

| Setting | Description | Default |
|---------|-------------|---------|
| Debug OTP | Auto-fill OTP in development | Disabled |
| Session Timeout | User session duration | 120 minutes |
| File Upload Limit | Max document size | 10MB |
| Allowed File Types | Accepted upload formats | PDF, JPG, PNG |

## Managing Agencies

Agencies represent partner organizations that process referrals.

To add a new agency:
1. Go to **Admin > Agencies**
2. Click **"New Agency"**
3. Enter agency details (name, short code, contact info)
4. Add the **services** the agency provides
5. Assign **agency users** with the AGENCY role

## Managing Services

Services define what each agency can handle:

- Services are linked to agencies
- Each referral must specify a required service
- Keep service names clear and specific

## Troubleshooting Tips

- **OTP not sending?** Check the cache table for expired entries
- **Queue not processing?** Ensure queue:listen is running
- **Uploads failing?** Check storage link is configured';
    }

    private function article13(): string
    {
        return '# Frequently Asked Questions

## General Questions

### What is One Window Bayanihan?
One Window Bayanihan is an integrated case management system for DMW Region VII that streamlines OFW assistance, referral processing, and inter-agency coordination.

### Who can use this system?
The system serves OFWs, Case Managers, partner agency personnel, and system administrators -- each with role-specific access and features.

### Is my data secure?
Yes. The system follows government security standards. All data is encrypted in transit and at rest.

## For OFWs

### How do I submit a case?
Visit the homepage and click **"Submit a Case"**. Fill out the required information, upload your documents, and submit. You will receive a tracking number.

### How long does case processing take?
Processing time varies depending on case complexity and agency response. Most cases are assessed within 3 days.

### Can I track my case without an account?
Yes. Use the **Track Case** feature on the homepage with your tracking number and OTP sent to your mobile.

## For Case Managers

### How do I refer a case to an agency?
Open the case, click **"Create Referral"**, select the agency and service, add notes, and submit. The agency will be notified automatically.

### How do I know if a referral is completed?
The referral status updates in real-time. You can also view milestones added by the agency.

## For Agency Partners

### How do I accept a referral?
New referrals appear on your dashboard. Click **"View"** to see details, then **"Accept"** to start processing.

### What if I cannot process a referral?
You can **reject** the referral with a reason. The Case Manager will be notified and can reassign it to another agency.

## Technical Support

### I forgot my password
Contact your system administrator to reset your password.

### The system is not loading
Try clearing your browser cache or using a different browser. If the issue persists, contact support.';
    }
}
