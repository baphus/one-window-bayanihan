const content = `# Glossary of Terms

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

**UUID.** A Universally Unique Identifier used as the primary key for most database records in the system.
`;
export default content;
