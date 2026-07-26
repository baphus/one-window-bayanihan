# Project instructions

Project conventions, commands, and gotchas live in AGENTS.md. Read it first.

@AGENTS.md

# AWS Guidance

- Prefer the AWS MCP Server for AWS interactions — it provides sandboxed
  execution, observability, and audit logging. If unavailable, use the
  AWS CLI directly.
- Before starting a task, check whether a relevant AWS skill is available.
  Load the skill with `retrieve_skill` and prefer its guidance over
  general knowledge.
- When uncertain about specific AWS details (API parameters, permissions,
  limits, error codes), verify against documentation rather than guessing.
  State uncertainty explicitly if you cannot confirm.
- When creating infrastructure, prefer infrastructure-as-code (AWS CDK or
  CloudFormation) over direct CLI commands.
- When working with infrastructure, follow AWS Well-Architected Framework
  principles.
- Do not use em dashes in AWS resource names or descriptions. Use
  hyphens instead.

## Secret Safety

- MUST load the `aws-secrets-manager` skill first for any secret,
  credential, API key, token, or password task. MUST NOT call
  `secretsmanager get-secret-value` or `batch-get-secret-value`, and MUST
  NOT hit the Secrets Manager Agent daemon directly. MUST use
  `{{resolve:secretsmanager:secret-id:SecretString:json-key}}` with
  `asm-exec` so the secret resolves at runtime without entering context.

---

## Document control

Version: v1.1.0

Source: AWS Agent Toolkit for AWS, `rules/aws-agent-rules.md`
(https://github.com/aws/agent-toolkit-for-aws) — retrieved 2026-07-27.

This filename is fixed by the Claude Code harness and cannot carry a version
suffix, so the version and changelog are recorded in-file instead.

### Changelog

| Version | Date | Change |
|---|---|---|
| v1.0.0 | 2026-07-27 | Initial import of AWS agent rules verbatim from the Agent Toolkit for AWS, as Step 7 of toolkit setup. Default AWS Region for this workstation set to `ap-southeast-1`; Agent Toolkit control plane is `us-east-1`. |
| v1.1.0 | 2026-07-27 | Added "Project instructions" section importing `@AGENTS.md`, so project conventions load in Claude Code sessions. AGENTS.md remains the single source of truth for project rules; do not duplicate them here. |
