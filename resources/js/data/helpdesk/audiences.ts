/**
 * "Who are you?" audience routing shown on the /help landing page.
 * Each entry links to an existing category view — no new routes.
 * The row is identical for every visitor regardless of authentication.
 */
export interface AudienceEntry {
  key: string;
  label: string;
  description: string;
  icon: string;
  href: string;
}

export const audienceEntries: AudienceEntry[] = [
  {
    key: "ofw",
    label: "OFWs & families",
    description:
      "Submit a case, track its progress, and know your rights and available assistance.",
    icon: "badge",
    href: "/help?category=ofw-assistance",
  },
  {
    key: "staff",
    label: "DMW staff",
    description:
      "Case management procedures, referrals, dashboards, and daily workflow guides.",
    icon: "folder_shared",
    href: "/help?category=case-management",
  },
  {
    key: "agency",
    label: "Partner agencies",
    description:
      "Referral processing, status updates, and inter-agency coordination guides.",
    icon: "handshake",
    href: "/help?category=agency-partnership",
  },
];
