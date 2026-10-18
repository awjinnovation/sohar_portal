{!! __('landing.mail.confirm_title', ['name' => $r->name], $lc) !!}

{!! __('landing.mail.confirm_intro', ['organization' => $r->organization], $lc) !!}

{!! __('landing.mail.confirm_summary', [], $lc) !!}
@foreach($rows as $label => $value)
- {!! $label !!}: {!! $value !!}
@endforeach

{!! __('landing.mail.confirm_next', [], $lc) !!}
{!! __('landing.mail.confirm_next_body', ['contact' => $r->contact], $lc) !!}

—
{!! __('landing.mail.confirm_footer', [], $lc) !!}
{!! $homeUrl !!}

{!! __('landing.mail.confirm_ignore', [], $lc) !!}
