<x-mail::message>
# An Update About Your Request

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 20px 0;">
    Dear {{ $case->client->first_name ?? 'Sir/Madam' }},
</p>

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 20px 0;">
    We could not move your submitted request forward at this time. The review team shared the reason below.
</p>

<p style="font-size: 14px; font-weight: 600; color: #18181b; margin: 0 0 8px 0;">Reason</p>

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 28px 0;">
    {{ $reason }}
</p>

<p style="font-size: 14px; font-weight: 600; color: #18181b; margin: 0 0 8px 0;">What You Can Do</p>

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 20px 0;">
    If you have additional information or need clarification, you may submit a new request or contact the DMW Region VII office directly:
</p>

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 28px 0;">
    <strong>Department of Migrant Workers — Region VII</strong><br>
    Email: dmw.region7@dmw.gov.ph<br>
    Hotline: 1348 (MWOB Hotline)
</p>

<x-mail::contact-footer />
</x-mail::message>
