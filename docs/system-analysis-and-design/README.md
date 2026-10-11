# System Analysis and Design Documentation — Reading Guide

| | |
|---|---|
| **Version** | 1.0.0 |
| **Date** | 2026-10-11 |
| **Scope** | One Window Bayanihan — the inter-agency case-management portal for distressed OFWs in Region VII, operated by DMW. This directory is the analysis-and-design manuscript in eight parts |
| **Audience** | DICT reviewers, capstone panelists, business stakeholders, auditors, and operations staff |

## What this set is

Eight documents that describe **what the system is, how it behaves, and where it stands** — written for readers who do not need to see how it is built. Together they cover: the system and its purpose, the real-world processes it supports, how the parts fit together, what information it keeps, what it can do, who may see and do what, how information moves in and out, and an honest view of strengths, risks, and next steps.

## A note on language

Plain language in this set is **deliberate**. Technical detail — file locations, interface addresses, command names, storage structures, and similar implementation evidence — has been left out on purpose so the documents can be read and reviewed by a business audience. Where a term must be used, it is explained in everyday words the first time it appears. Technical reviewers who need implementation evidence should ask the project team for the engineering records that back these documents.

## Who should read which document

| # | Document | Best for | One line on what it gives you |
|---|---|---|---|
| — | [README.md](README.md) (this file) | Everyone | How to use the set, reading order, and how statements are worded |
| 01 | [01-system-description.md](01-system-description.md) | Everyone, first | What the system is, why it exists, who it serves, and where its boundaries are |
| 02 | [02-business-process-flow.md](02-business-process-flow.md) | Process owners, reviewers | The real-world journey of a case: intake → referral → service delivery → closure |
| 03 | [03-system-flow-and-architecture.md](03-system-flow-and-architecture.md) | Technical reviewers | How the parts of the system fit together and how work travels through them |
| 04 | [04-database-schema-and-data-dictionary.md](04-database-schema-and-data-dictionary.md) | Records and data officers | What information the system keeps, what each piece means, and how records relate |
| 05 | [05-operational-scope-and-functionalities.md](05-operational-scope-and-functionalities.md) | Product owners, QA reviewers | What is fully built, what is partly built, what is only planned |
| 06 | [06-user-roles-and-access-control.md](06-user-roles-and-access-control.md) | Security and access reviewers | Who can see and do what, and how sign-in is protected |
| 07 | [07-api-and-integration-reference.md](07-api-and-integration-reference.md) | Integration and operations reviewers | How information moves between the portal, the public, the database, and outside services |
| 08 | [08-technical-findings-and-recommendations.md](08-technical-findings-and-recommendations.md) | Decision makers | What works well, what the risks are, what to confirm, and what to do next |

## Suggested reading order

Two paths, depending on why you are reading:

1. **Executive path (about one hour):** [01](01-system-description.md) → [02](02-business-process-flow.md) → [08](08-technical-findings-and-recommendations.md). What the system is, how the work flows, and the honest assessment with next steps.
2. **Reviewer path (full set):** [01](01-system-description.md) → [02](02-business-process-flow.md) → [03](03-system-flow-and-architecture.md) → [04](04-database-schema-and-data-dictionary.md) → [05](05-operational-scope-and-functionalities.md) → [06](06-user-roles-and-access-control.md) → [07](07-api-and-integration-reference.md) → [08](08-technical-findings-and-recommendations.md). Read in order — each document assumes the ones before it.

For a specific question, go straight to the matching document using the table above; each document opens with its own list of contents.

## How availability and confidence are worded

Statements about whether something exists or has been checked use four simple labels:

| Label | Meaning |
|---|---|
| **Available** | The information or feature exists and was confirmed while this set was prepared |
| **Partly available** | Present, but incomplete, or only partly confirmed |
| **Not available** | Looked for and genuinely not present |
| **To be confirmed** | An open question that needs an owner's decision or a check in the target environment |

Every statement in the set uses these four labels exactly — no synonyms — so documents can be compared word for word.

## Keeping the set current

The set is refreshed whenever the system changes materially or a new release is prepared, and figures are re-checked before they are updated. Where this set and another project document disagree, the more recently confirmed statement wins, and the difference should be reported so the stale document can be corrected.

---

*End of the reading guide.*
