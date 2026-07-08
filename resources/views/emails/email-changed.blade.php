<x-mail::message>
# Email Address Changed

Dear {{ $userName }},

Your email address for **{{ config('app.name') }}** has been changed successfully.

**New email:** {{ substr($newEmail, 0, 2) }}***{{ strchr($newEmail, '@') }}

---

### Didn't authorize this change?

If you did not authorize this change, please contact your system administrator or the DMW Region VII office immediately so we can secure your account.

<br>

Regards,<br>
**Department of Migrant Workers – Region VII**<br>
**{{ config('app.name') }}**
</x-mail::message>
