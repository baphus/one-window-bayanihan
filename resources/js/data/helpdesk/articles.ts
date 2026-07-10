import type { HelpdeskArticle } from "./types";
import gettingStartedCaseManagers from "./content/getting-started-case-managers";
import gettingStartedAgencyFocal from "./content/getting-started-agency-focal";
import gettingStartedSystemAdmin from "./content/getting-started-system-admin";
import usingPublicTrackingPortal from "./content/using-public-tracking-portal";
import providingFeedbackOnYourCase from "./content/providing-feedback-on-your-case";
import understandingCaseStatusesTrackerNumbers from "./content/understanding-case-statuses-tracker-numbers";
import ofwAssistanceServicesAvailable from "./content/ofw-assistance-services-available";
import caseDocumentationBestPractices from "./content/case-documentation-best-practices";
import usingDashboardDailyMonitoring from "./content/using-dashboard-daily-monitoring";
import caseClosureChecklistProcedures from "./content/case-closure-checklist-procedures";
import managingOverdueReferrals from "./content/managing-overdue-referrals";
import managingYourAgencyServicesProfile from "./content/managing-your-agency-services-profile";
import addingMilestonesReferralsCompleteGuide from "./content/adding-milestones-referrals-complete-guide";
import bestPracticesTimelyReferralProcessing from "./content/best-practices-timely-referral-processing";
import communicationGuidelinesInterAgency from "./content/communication-guidelines-inter-agency";
import managingHelpdeskKnowledgeBase from "./content/managing-helpdesk-knowledge-base";
import understandingUsingAuditLog from "./content/understanding-using-audit-log";
import configuringSystemSettingsAiChatbot from "./content/configuring-system-settings-ai-chatbot";
import monitoringQueueJobsSystemHealth from "./content/monitoring-queue-jobs-system-health";
import privacyDataProtectionOwb from "./content/privacy-data-protection-owb";
import glossaryOfTerms from "./content/glossary-of-terms";
import troubleshootingCommonIssues from "./content/troubleshooting-common-issues";
import managingDraftCases from "./content/managing-draft-cases";
import managingComplianceRequirements from "./content/managing-compliance-requirements";
import usingReferralComments from "./content/using-referral-comments";
import creatingPublishingCases from "./content/creating-publishing-cases";
import usingReportsAnalytics from "./content/using-reports-analytics";
import exportingCaseDataExcel from "./content/exporting-case-data-excel";
import adminCaseCategoriesIssuesStatuses from "./content/admin-case-categories-issues-statuses";
import userManagementGuide from "./content/user-management-guide";
import agencyServiceManagementGuide from "./content/agency-service-management-guide";
import referralStatusReference from "./content/referral-status-reference";

// ---------------------------------------------------------------------------
// Category slug → ID mapping (from categories.ts)
// ---------------------------------------------------------------------------
const CATEGORY: Record<string, string> = {
  "ofw-assistance": "cat-1",
  "case-management": "cat-2",
  "agency-partnership": "cat-3",
  "system-administration": "cat-4",
  faq: "cat-5",
  "case-submission": "cat-6",
  "ofw-rights": "cat-7",
  "cm-workflow": "cat-8",
  "referrals-escalations": "cat-9",
  "referral-processing": "cat-10",
  "coordination-communication": "cat-11",
  "user-account-management": "cat-12",
  "system-config": "cat-13",
};

// ---------------------------------------------------------------------------
// Tag slug → ID mapping (from tags.ts)
// ---------------------------------------------------------------------------
const TAG: Record<string, string> = {
  cases: "tag-1",
  referrals: "tag-2",
  documents: "tag-3",
  tracking: "tag-4",
  repatriation: "tag-5",
  compliance: "tag-6",
  escalation: "tag-7",
  training: "tag-8",
  troubleshooting: "tag-9",
  onboarding: "tag-10",
  chatbot: "tag-11",
  audit: "tag-12",
  privacy: "tag-13",
  dashboard: "tag-14",
  feedback: "tag-15",
  glossary: "tag-16",
};

// ---------------------------------------------------------------------------
// All 32 helpdesk articles
// ---------------------------------------------------------------------------
export const articles: HelpdeskArticle[] = [
  // ======== Featured (3) ========

  {
    id: "article-1",
    title: "Getting Started for Case Managers",
    slug: "getting-started-case-managers",
    excerpt:
      "A comprehensive onboarding guide for Case Managers covering login, dashboard navigation, case creation, referral management, and daily workflow routines.",
    content: gettingStartedCaseManagers,
    categoryId: CATEGORY["cm-workflow"],
    tagIds: [TAG["onboarding"], TAG["training"], TAG["cases"]],
    featured: true,
    publishedAt: "2025-01-15T00:00:00.000Z",
  },
  {
    id: "article-2",
    title: "Getting Started for Agency Focal Persons",
    slug: "getting-started-agency-focal",
    excerpt:
      "Step-by-step onboarding guide for Agency Focal Persons on dashboard navigation, referral acceptance, milestone tracking, and inter-agency communication.",
    content: gettingStartedAgencyFocal,
    categoryId: CATEGORY["referral-processing"],
    tagIds: [TAG["onboarding"], TAG["training"], TAG["referrals"]],
    featured: true,
    publishedAt: "2025-01-15T00:00:00.000Z",
  },
  {
    id: "article-3",
    title: "Getting Started for System Administrators",
    slug: "getting-started-system-admin",
    excerpt:
      "Administrator onboarding guide covering user management, agency registration, service configuration, system settings, helpdesk CMS, and audit log basics.",
    content: gettingStartedSystemAdmin,
    categoryId: CATEGORY["system-config"],
    tagIds: [TAG["onboarding"], TAG["training"], TAG["troubleshooting"]],
    featured: true,
    publishedAt: "2025-01-15T00:00:00.000Z",
  },

  // ======== OFW-Focused (4–7) ========

  {
    id: "article-4",
    title: "Using the Public Tracking Portal",
    slug: "using-public-tracking-portal",
    excerpt:
      "Guide for OFWs on how to access the public tracking portal to monitor case status using a tracker number and OTP verification, including color-coded status indicators and what to do if you lost or forgot your tracker number.",
    content: usingPublicTrackingPortal,
    categoryId: CATEGORY["case-submission"],
    tagIds: [TAG["tracking"], TAG["cases"]],
    featured: false,
    publishedAt: "2025-01-15T00:00:00.000Z",
  },
  {
    id: "article-5",
    title: "Providing Feedback on Your Case",
    slug: "providing-feedback-on-your-case",
    excerpt:
      "Learn how to provide feedback and rate the service you received after your case is closed, including the SERVQUAL evaluation dimensions.",
    content: providingFeedbackOnYourCase,
    categoryId: CATEGORY["ofw-rights"],
    tagIds: [TAG["cases"], TAG["documents"]],
    featured: false,
    publishedAt: "2025-01-15T00:00:00.000Z",
  },
  {
    id: "article-6",
    title: "Understanding Case Statuses and Tracker Numbers",
    slug: "understanding-case-statuses-tracker-numbers",
    excerpt:
      "Learn the difference between internal case numbers and public tracker numbers, and understand what each case and referral status means.",
    content: understandingCaseStatusesTrackerNumbers,
    categoryId: CATEGORY["case-submission"],
    tagIds: [TAG["tracking"], TAG["cases"]],
    featured: false,
    publishedAt: "2025-01-15T00:00:00.000Z",
  },
  {
    id: "article-7",
    title: "OFW Assistance Services Available from DMW Region VII",
    slug: "ofw-assistance-services-available",
    excerpt:
      "Overview of the full range of assistance services available through DMW Region VII and its partner agencies including OWWA, DOLE, TESDA, DSWD, and DOH.",
    content: ofwAssistanceServicesAvailable,
    categoryId: CATEGORY["ofw-assistance"],
    tagIds: [TAG["repatriation"], TAG["documents"], TAG["cases"]],
    featured: false,
    publishedAt: "2025-01-15T00:00:00.000Z",
  },

  // ======== Case Manager Guides (8–11) ========

  {
    id: "article-8",
    title: "Case Documentation Best Practices",
    slug: "case-documentation-best-practices",
    excerpt:
      "Best practices for writing effective case summaries, organizing uploaded documents, maintaining a complete audit trail, and avoiding common documentation pitfalls.",
    content: caseDocumentationBestPractices,
    categoryId: CATEGORY["cm-workflow"],
    tagIds: [TAG["cases"], TAG["documents"], TAG["compliance"]],
    featured: false,
    publishedAt: "2025-01-15T00:00:00.000Z",
  },
  {
    id: "article-9",
    title: "Using the Dashboard for Daily Monitoring",
    slug: "using-dashboard-daily-monitoring",
    excerpt:
      "Learn how to use the dashboard KPIs, recent cases list, pending referrals, and case trends chart for effective daily monitoring and workflow management.",
    content: usingDashboardDailyMonitoring,
    categoryId: CATEGORY["cm-workflow"],
    tagIds: [TAG["cases"], TAG["tracking"], TAG["compliance"]],
    featured: false,
    publishedAt: "2025-01-15T00:00:00.000Z",
  },
  {
    id: "article-10",
    title: "Case Closure Checklist and Procedures",
    slug: "case-closure-checklist-procedures",
    excerpt:
      "Complete checklist for closing cases including pre-closure verification, required documentation, the closure process, and post-closure client feedback procedures.",
    content: caseClosureChecklistProcedures,
    categoryId: CATEGORY["referrals-escalations"],
    tagIds: [TAG["cases"], TAG["compliance"], TAG["escalation"]],
    featured: false,
    publishedAt: "2025-01-15T00:00:00.000Z",
  },
  {
    id: "article-11",
    title: "Managing Overdue Referrals",
    slug: "managing-overdue-referrals",
    excerpt:
      "Guide for Case Managers on monitoring overdue referrals, sending reminders, escalating chronic delays, and using SLA tracking to improve agency performance.",
    content: managingOverdueReferrals,
    categoryId: CATEGORY["referrals-escalations"],
    tagIds: [TAG["referrals"], TAG["escalation"], TAG["tracking"]],
    featured: false,
    publishedAt: "2025-01-15T00:00:00.000Z",
  },

  // ======== Agency Focal Person Guides (12–15) ========

  {
    id: "article-12",
    title: "Managing Your Agency Services Profile",
    slug: "managing-your-agency-services-profile",
    excerpt:
      "Guide for agency focal persons on managing service offerings, setting realistic SLA processing days, and keeping service listings accurate for better referral matching.",
    content: managingYourAgencyServicesProfile,
    categoryId: CATEGORY["referral-processing"],
    tagIds: [TAG["referrals"], TAG["onboarding"], TAG["compliance"]],
    featured: false,
    publishedAt: "2025-01-15T00:00:00.000Z",
  },
  {
    id: "article-13",
    title: "Adding Milestones to Referrals: Complete Guide",
    slug: "adding-milestones-referrals-complete-guide",
    excerpt:
      "Complete guide on adding milestones to referrals including when to add them, how to write effective entries, frequency best practices, and example timelines.",
    content: addingMilestonesReferralsCompleteGuide,
    categoryId: CATEGORY["referral-processing"],
    tagIds: [TAG["referrals"], TAG["tracking"], TAG["compliance"]],
    featured: false,
    publishedAt: "2025-01-15T00:00:00.000Z",
  },
  {
    id: "article-14",
    title: "Best Practices for Timely Referral Processing",
    slug: "best-practices-timely-referral-processing",
    excerpt:
      "Daily checklist and best practices for agency focal persons to ensure timely referral processing, including prioritization, proactive communication, and caseload management.",
    content: bestPracticesTimelyReferralProcessing,
    categoryId: CATEGORY["coordination-communication"],
    tagIds: [TAG["referrals"], TAG["compliance"], TAG["training"]],
    featured: false,
    publishedAt: "2025-01-15T00:00:00.000Z",
  },
  {
    id: "article-15",
    title: "Communication Guidelines for Inter-Agency Coordination",
    slug: "communication-guidelines-inter-agency",
    excerpt:
      "Professional communication standards for inter-agency coordination including referral comments, escalation protocols, and conflict resolution procedures.",
    content: communicationGuidelinesInterAgency,
    categoryId: CATEGORY["coordination-communication"],
    tagIds: [TAG["referrals"], TAG["escalation"], TAG["compliance"]],
    featured: false,
    publishedAt: "2025-01-15T00:00:00.000Z",
  },

  // ======== System Administrator Guides (16–19) ========

  {
    id: "article-16",
    title: "Managing the Helpdesk Knowledge Base",
    slug: "managing-helpdesk-knowledge-base",
    excerpt:
      "Guide for administrators on managing the helpdesk knowledge base including creating articles with markdown, organizing by categories and tags, and managing revisions.",
    content: managingHelpdeskKnowledgeBase,
    categoryId: CATEGORY["system-config"],
    tagIds: [TAG["training"], TAG["troubleshooting"], TAG["onboarding"]],
    featured: false,
    publishedAt: "2025-01-15T00:00:00.000Z",
  },
  {
    id: "article-17",
    title: "Understanding and Using the Audit Log",
    slug: "understanding-using-audit-log",
    excerpt:
      "Guide to the system audit log covering what events are recorded, how to filter and search logs, and how to use audit data for security monitoring and compliance.",
    content: understandingUsingAuditLog,
    categoryId: CATEGORY["user-account-management"],
    tagIds: [TAG["troubleshooting"], TAG["compliance"]],
    featured: false,
    publishedAt: "2025-01-15T00:00:00.000Z",
  },
  {
    id: "article-18",
    title: "Configuring System Settings and AI Chatbot",
    slug: "configuring-system-settings-ai-chatbot",
    excerpt:
      "Guide for administrators on configuring system settings including OTP debug mode, session timeout, file upload limits, and the AI chatbot provider setup.",
    content: configuringSystemSettingsAiChatbot,
    categoryId: CATEGORY["system-config"],
    tagIds: [TAG["troubleshooting"], TAG["onboarding"]],
    featured: false,
    publishedAt: "2025-01-15T00:00:00.000Z",
  },
  {
    id: "article-19",
    title: "Monitoring Queue Jobs and System Health",
    slug: "monitoring-queue-jobs-system-health",
    excerpt:
      "Guide for administrators on monitoring queue health, managing failed jobs, checking system health metrics, and maintaining optimal system performance.",
    content: monitoringQueueJobsSystemHealth,
    categoryId: CATEGORY["system-config"],
    tagIds: [TAG["troubleshooting"], TAG["onboarding"], TAG["compliance"]],
    featured: false,
    publishedAt: "2025-01-15T00:00:00.000Z",
  },

  // ======== General / Reference (20–22) ========

  {
    id: "article-20",
    title: "Privacy and Data Protection in One Window Bayanihan",
    slug: "privacy-data-protection-owb",
    excerpt:
      "Overview of data privacy and protection practices in the system under RA 10173, including data collected, encryption standards, access controls, and user rights.",
    content: privacyDataProtectionOwb,
    categoryId: CATEGORY["faq"],
    tagIds: [TAG["compliance"], TAG["documents"]],
    featured: false,
    publishedAt: "2025-01-15T00:00:00.000Z",
  },
  {
    id: "article-21",
    title: "Glossary of Terms",
    slug: "glossary-of-terms",
    excerpt:
      "Comprehensive glossary of terms, acronyms, and definitions used throughout the One Window Bayanihan system.",
    content: glossaryOfTerms,
    categoryId: CATEGORY["faq"],
    tagIds: [TAG["training"], TAG["onboarding"]],
    featured: false,
    publishedAt: "2025-01-15T00:00:00.000Z",
  },
  {
    id: "article-22",
    title: "Troubleshooting Common Issues",
    slug: "troubleshooting-common-issues",
    excerpt:
      "Solutions for common issues including login problems, OTP delivery failures, document upload errors, dashboard loading issues, and notification troubleshooting.",
    content: troubleshootingCommonIssues,
    categoryId: CATEGORY["faq"],
    tagIds: [TAG["troubleshooting"], TAG["onboarding"]],
    featured: false,
    publishedAt: "2025-01-15T00:00:00.000Z",
  },

  // ======== Expanded Workflow Guides (23–32) ========

  {
    id: "article-23",
    title: "Creating and Publishing Cases: Complete Walkthrough",
    slug: "creating-publishing-cases",
    excerpt:
      "A field-by-field walkthrough for creating a draft case, completing intake details, publishing it to OPEN, and confirming the public tracker number.",
    content: creatingPublishingCases,
    categoryId: CATEGORY["cm-workflow"],
    tagIds: [TAG["cases"], TAG["training"], TAG["documents"]],
    featured: true,
    publishedAt: "2026-07-09T00:00:00.000Z",
  },
  {
    id: "article-24",
    title: "Managing Draft Cases: Save, Edit, Publish, Delete",
    slug: "managing-draft-cases",
    excerpt:
      "How Case Managers use the DRAFT workflow safely, including owner-only access, draft updates, publishing, and deleting unfinished drafts.",
    content: managingDraftCases,
    categoryId: CATEGORY["cm-workflow"],
    tagIds: [TAG["cases"], TAG["documents"], TAG["onboarding"]],
    featured: false,
    publishedAt: "2026-07-09T00:00:00.000Z",
  },
  {
    id: "article-25",
    title: "Managing Compliance Requirements on Referrals",
    slug: "managing-compliance-requirements",
    excerpt:
      "Guide to referral compliance requirements, FOR_COMPLIANCE status, uploading fulfillment documents, and returning work to processing.",
    content: managingComplianceRequirements,
    categoryId: CATEGORY["referral-processing"],
    tagIds: [TAG["referrals"], TAG["documents"], TAG["compliance"]],
    featured: false,
    publishedAt: "2026-07-09T00:00:00.000Z",
  },
  {
    id: "article-26",
    title: "Using Referral Comments and Communication",
    slug: "using-referral-comments",
    excerpt:
      "How Case Managers and agency users keep referral communication in the system using comments, replies, visibility, and milestones.",
    content: usingReferralComments,
    categoryId: CATEGORY["coordination-communication"],
    tagIds: [TAG["referrals"], TAG["escalation"], TAG["compliance"]],
    featured: false,
    publishedAt: "2026-07-09T00:00:00.000Z",
  },
  {
    id: "article-27",
    title: "Using Reports and Analytics",
    slug: "using-reports-analytics",
    excerpt:
      "A practical guide to reading status distribution, referral funnel, trend, demographics, geography, employment, cycle-time, category, and agency scorecard reports.",
    content: usingReportsAnalytics,
    categoryId: CATEGORY["cm-workflow"],
    tagIds: [TAG["dashboard"], TAG["tracking"], TAG["compliance"]],
    featured: false,
    publishedAt: "2026-07-09T00:00:00.000Z",
  },
  {
    id: "article-28",
    title: "Exporting Case Data to Excel",
    slug: "exporting-case-data-excel",
    excerpt:
      "How to export case data with the system's business-safe columns, filters, timestamps, and privacy expectations.",
    content: exportingCaseDataExcel,
    categoryId: CATEGORY["cm-workflow"],
    tagIds: [TAG["cases"], TAG["documents"], TAG["compliance"]],
    featured: false,
    publishedAt: "2026-07-09T00:00:00.000Z",
  },
  {
    id: "article-29",
    title: "Admin Reference: Managing Case Categories, Issues, and Statuses",
    slug: "admin-case-categories-issues-statuses",
    excerpt:
      "Reference guide for administrators configuring case categories, issue lists, custom statuses, active flags, sort order, and system status restrictions.",
    content: adminCaseCategoriesIssuesStatuses,
    categoryId: CATEGORY["system-config"],
    tagIds: [TAG["training"], TAG["compliance"], TAG["troubleshooting"]],
    featured: false,
    publishedAt: "2026-07-09T00:00:00.000Z",
  },
  {
    id: "article-30",
    title: "User Management Guide",
    slug: "user-management-guide",
    excerpt:
      "How administrators create, verify, edit, deactivate, and review user accounts for ADMIN, CASE_MANAGER, and AGENCY roles.",
    content: userManagementGuide,
    categoryId: CATEGORY["user-account-management"],
    tagIds: [TAG["onboarding"], TAG["training"], TAG["audit"]],
    featured: false,
    publishedAt: "2026-07-09T00:00:00.000Z",
  },
  {
    id: "article-31",
    title: "Agency and Service Management Guide",
    slug: "agency-service-management-guide",
    excerpt:
      "How administrators maintain agency records, services, processing-day targets, requirements, and active/inactive service availability.",
    content: agencyServiceManagementGuide,
    categoryId: CATEGORY["system-config"],
    tagIds: [TAG["referrals"], TAG["training"], TAG["compliance"]],
    featured: false,
    publishedAt: "2026-07-09T00:00:00.000Z",
  },
  {
    id: "article-32",
    title: "Referral Status Reference",
    slug: "referral-status-reference",
    excerpt:
      "Reference for PENDING, PROCESSING, FOR_COMPLIANCE, COMPLETED, and REJECTED referral states and the user actions behind each status.",
    content: referralStatusReference,
    categoryId: CATEGORY["referrals-escalations"],
    tagIds: [TAG["referrals"], TAG["tracking"], TAG["compliance"]],
    featured: false,
    publishedAt: "2026-07-09T00:00:00.000Z",
  },
];
