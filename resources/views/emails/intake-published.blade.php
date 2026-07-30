<x-mail::message>
# Your Case Has Been Accepted

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 20px 0;">
    Dear {{ $case->client->first_name ?? 'Sir/Madam' }},
</p>

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 20px 0;">
    Good news! Your self-filed case has been reviewed and accepted by the Department of Migrant Workers (DMW) Region VII.
</p>

<p style="font-size: 14px; font-weight: 600; color: #18181b; margin: 0 0 8px 0;">Case Details</p>

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 8px 0;">
    <strong>Case Number:</strong> {{ $caseNumber }}<br>
    <strong>Tracker Number:</strong> {{ $trackerNumber }}
</p>

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 20px 0 28px 0;">
    A Case Manager has been assigned and will coordinate with the relevant agencies to address your concerns. You can track the progress of your case at any time using your tracker number.
</p>

<x-mail::action-card url="{{ route('track.index', ['tracker_number' => $trackerNumber]) }}" label="Track Your Case" />

<p style="font-size: 14px; font-weight: 600; color: #18181b; margin: 32px 0 8px 0;">What Happens Next</p>

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 28px 0;">
    Your Case Manager may refer your case to partner agencies (OWWA, DOLE, TESDA, DSWD, etc.) for appropriate action. You will receive email notifications as your case progresses through each stage.
</p>

<x-mail::contact-footer />
</x-mail::message>
