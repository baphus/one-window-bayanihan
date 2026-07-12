<x-mail::message>
# New Contact Form Message

You have received a message from the **{{ config('app.name') }}** contact form.

---

**From:** {{ $senderName }}
**Email:** {{ $senderEmail }}

---

### Message

{{ $messageBody }}

---

<div style="margin: 24px 0; color: #6b7280; font-size: 13px;">
    <em>This message was submitted through the public contact form. You can reply directly to this email to respond to the sender.</em>
</div>

Regards,<br>
**{{ config('app.name') }}**
</x-mail::message>
