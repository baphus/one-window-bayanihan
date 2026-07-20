<x-mail::message>
# Contact Form Submission

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 24px 0;">
    A message was received through the public contact form.
</p>

<x-mail::detail-table :rows="[
    ['label' => 'From', 'value' => $senderName],
    ['label' => 'Email', 'value' => $senderEmail],
    ['label' => 'Received', 'value' => now()->format('M d, Y g:i A')],
]" />

<p style="font-size: 14px; font-weight: 600; color: #18181b; margin: 28px 0 8px 0;">Message</p>

<div style="background-color: #f8fafc; border-left: 3px solid #e4e4e7; padding: 16px 20px; margin: 0 0 28px 0;">
    <p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0; white-space: pre-wrap;">{{ $messageBody }}</p>
</div>

<p style="font-size: 13px; line-height: 1.7; color: #71717a; margin: 0;">
    Reply directly to <a href="mailto:{{ $senderEmail }}" style="color: #005288;">{{ $senderEmail }}</a> to respond.
</p>

<x-mail::contact-footer />
</x-mail::message>
