const content = `# Case Documentation Best Practices

Proper documentation is the foundation of effective case management. This guide covers best practices for maintaining clear, complete, and audit-ready case records.

## Writing Effective Case Summaries

A good case summary is:

- **Clear** — use plain language that anyone can understand
- **Factual** — state what happened, not what you think might have happened
- **Chronological** — present events in the order they occurred
- **Complete** — include all relevant details without unnecessary information

### Case Summary Template

\`\`\`
Summary:
- Client reported that [issue] occurred on [date]
- Previous actions taken: [list]
- Current status: [what is happening now]
- Required next steps: [what needs to happen]
\`\`\`

## Organizing Uploaded Documents

Follow these conventions for document management:

### Naming Convention
Use a consistent format: \`[DocumentType]_[ClientName]_[Date].[ext]\`

Examples:
- \`Passport_Santos_20260115.pdf\`
- \`EmploymentContract_Cruz_20260201.pdf\`
- \`MedicalCertificate_Reyes_20260310.pdf\`

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

This creates a clear chain of evidence that supports every action taken on the case.
`;
export default content;
