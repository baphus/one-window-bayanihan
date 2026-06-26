import type { HelpdeskArticle } from "./types";

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
// All 22 helpdesk articles
// ---------------------------------------------------------------------------
export const articles: HelpdeskArticle[] = [
  // ======== Featured (3) ========

  {
    id: "article-1",
    title: "Getting Started for Case Managers",
    slug: "getting-started-case-managers",
    excerpt:
      "A comprehensive onboarding guide for Case Managers covering login, dashboard navigation, case creation, referral management, and daily workflow routines.",
    content: "",
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
    content: "",
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
    content: "",
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
      "Guide for OFWs on how to use the public tracking portal to monitor case status using a tracker number and OTP verification.",
    content: "",
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
    content: "",
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
    content: "",
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
    content: "",
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
    content: "",
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
    content: "",
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
    content: "",
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
    content: "",
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
    content: "",
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
    content: "",
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
    content: "",
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
    content: "",
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
    content: "",
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
    content: "",
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
    content: "",
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
    content: "",
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
    content: "",
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
    content: "",
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
    content: "",
    categoryId: CATEGORY["faq"],
    tagIds: [TAG["troubleshooting"], TAG["onboarding"]],
    featured: false,
    publishedAt: "2025-01-15T00:00:00.000Z",
  },
];
