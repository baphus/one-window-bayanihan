<x-mail::message>
<h1 style="font-size: 20px; font-weight: 800; color: #18181b; margin: 0 0 16px 0;">New Contact Form Message</h1>

<x-mail::detail-table :rows="[
    ['label' => 'From', 'value' => $senderName],
    ['label' => 'Email', 'value' => $senderEmail],
]" />

<h3 style="font-size: 16px; font-weight: 700; color: #18181b; margin: 24px 0 12px 0;">Message</h3>
<p style="font-size: 15px; line-height: 1.6; color: #52525b; margin: 0 0 24px 0; white-space: pre-wrap;">{{ $messageBody }}</p>

<x-mail::contact-footer />
</x-mail::message>
