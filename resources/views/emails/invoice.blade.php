@component('mail::message')
# Hi Payroll Team!

**{{ $user->name }}** has asked me to send you an invoice. Please find it attached.

You can reply to this email if you have any questions.

@component('mail::button', ['url' => $google_doc_link])
View in Google Docs
@endcomponent


Thanks,<br>
{{ config('app.name') }}
@endcomponent
