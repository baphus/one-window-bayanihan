<x-mail::message>
# Your Submission Could Not Be Processed

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 20px 0;">
    Dear {{ $case->client->first_name ?? 'Sir/Madam' }},
</p>

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 20px 0;">
    We regret to inform you that your submitted case could not be processed at this time.
</p>

<p style="font-size: 14px; font-weight: 600; color: #18181b; margin: 0 0 8px 0;">Reason</p>

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 28px 0;">
    {{ $reason }}
</p>

<p style="font-size: 14px; font-weight: 600; color: #18181b; margin: 0 0 8px 0;">What You Can Do</p>

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 20px 0;">
    If you believe this was a mistake or have additional information, you may submit a new case or contact the DMW Region VII office directly:
</p>

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 28px 0;">
    <strong>Department of Migrant Workers — Region VII</strong><br>
    Email: dmw.region7@dmw.gov.ph<br>
    Hotline: 1348 (MWOB Hotline)
</p>

<x-mail::contact-footer />
</x-mail::message>
