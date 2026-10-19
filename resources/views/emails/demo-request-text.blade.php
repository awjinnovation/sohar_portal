MAWSIM · موسم — NEW DEMO REQUEST #{{ $r->id }}

{!! $r->organization !!}
{!! $r->name !!} would like to see Mawsim.

Name:              {!! $r->name !!}
Organization:      {!! $r->organization !!}
Event type:        {!! $eventType !!}
Expected visitors: {!! $visitors !!}
Season dates:      {!! $r->season ?: '—' !!}
Contact:           {!! $r->contact !!} ({{ $r->contact_email ? 'email' : 'phone' }})
Language:          {{ $localeLabel }}

Notes:
{!! $r->notes ?: 'No notes.' !!}

@if($r->contact_email)
Reply to {!! $r->name !!}: mailto:{!! $r->contact_email !!} (or just reply to this email)
@else
No email given. Call or message: {!! $r->contact !!}
@endif

Admin: {!! $adminUrl !!}
All requests: {!! $adminListUrl !!}

—
Request #{{ $r->id }} · {{ $submittedAt }} · {{ $localeLabel }}
Sent by the Mawsim website (AWJ Systems) from the "Book a demo" form.
