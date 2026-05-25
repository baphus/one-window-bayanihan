<x-mail::message>
# Overdue Referral Notice

This referral has been pending for over **{{ $overdueDays }} days** without completion.

**Case Number:** {{ $referral->caseFile?->case_number ?? 'N/A' }}  
**Client:** {{ $referral->caseFile?->client?->first_name ?? '' }} {{ $referral->caseFile?->client?->last_name ?? 'N/A' }}  
**Agency:** {{ $referral->agency?->name ?? 'N/A' }}  
**Service Required:** {{ $referral->required_services }}  
**Created:** {{ $referral->created_at->format('M d, Y') }}  
**Days Overdue:** {{ $referral->created_at->diffInDays(now()) }}

<x-mail::button :url="route('referrals.show', $referral)">
View Referral
</x-mail::button>

Please take the necessary action to process or close this referral at your earliest convenience.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
