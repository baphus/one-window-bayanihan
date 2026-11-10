<x-mail::message>
# Your Assistance Request Is Ready

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 20px 0;">
    Dear {{ $case->client->first_name ?? 'Sir/Madam' }},
</p>

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 20px 0;">
    Good news! A Case Manager from the Department of Migrant Workers (DMW) Region VII has prepared an assistance request for you.
</p>

<p style="font-size: 14px; font-weight: 600; color: #18181b; margin: 0 0 8px 0;">Request Details</p>

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 8px 0;">
    <strong>Request Reference:</strong> {{ $caseNumber }}<br>
    <strong>Tracker Number:</strong> {{ $trackerNumber }}
</p>

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 20px 0 28px 0;">
    Your Case Manager will coordinate with the relevant agencies to address your concerns. You can track your request at any time using your tracker number.
</p>

<x-mail::action-card url="{{ route('track.index', ['tracker_number' => $trackerNumber]) }}" label="Track Your Request" />

<p style="font-size: 14px; font-weight: 600; color: #18181b; margin: 32px 0 8px 0;">What Happens Next</p>

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 28px 0;">
    Your request may be referred to partner agencies (OWWA, DOLE, TESDA, DSWD, etc.) for appropriate support. You will receive email notifications as your request progresses.
</p>

<x-mail::contact-footer />
</x-mail::message>
