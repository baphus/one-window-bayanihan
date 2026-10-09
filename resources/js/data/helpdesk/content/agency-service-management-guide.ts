const content = `# Agency and Service Management Guide

Administrators maintain partner agencies and their services so case managers can route referrals correctly. This admin catalog is separate from each agency's own **Services** page, where focal persons manage their own offerings (see *Managing your agency services profile*).

![Admin agencies](/assets/helpdesk/admin-agencies.png)

![Admin services](/assets/helpdesk/admin-services.png)

## Agencies

Agency records identify the partner office or organization that receives referrals. Keep contact details and focal assignments current so referrals reach the correct people. Agency focal accounts are scoped by their linked agency — they see only their agency's referrals, client requests, services, and survey forms.

## Services

Service records describe what an agency can provide. Use clear names that case managers can recognize during referral creation. Attachments on referrals and requirements are stored in S3-compatible object storage.

## Maintenance checklist

- Confirm agency names and abbreviations.
- Remove or deactivate services that are no longer offered.
- Add new services before instructing case managers to refer to them.
- Test referral routing after major agency or service changes.
`;
export default content;
