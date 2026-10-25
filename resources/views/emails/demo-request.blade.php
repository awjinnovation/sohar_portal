{{-- Internal "new demo request" email. Table layout + inline styles only (Gmail / Outlook / Apple Mail); no remote images. --}}
@php
    $night = '#0C1820';
    $night2 = '#132530';
    $saffron = '#E9A21B';
    $saffronSoft = '#FAEED3';
    $ink = '#0E1C24';
    $muted = '#4F6068';
    $line = '#E1E7E5';
    $font = "'Readex Pro','Segoe UI',Tahoma,Arial,sans-serif";
    $email = $r->contact_email;
    $phone = $r->contactPhone();
    $rows = [
        'Name' => $r->name,
        'Organization' => $r->organization,
        'Event type' => $eventType,
        'Expected visitors' => $visitors,
        'Season dates' => $r->season ?: '—',
        'Contact' => null, // rendered specially below
        'Language' => $localeLabel,
    ];
    $isRtlText = fn (?string $s) => $s !== null && preg_match('/\p{Arabic}/u', $s);
@endphp
<!DOCTYPE html>
<html lang="en" dir="ltr" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="x-apple-disable-message-reformatting">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>New demo request — {{ $r->organization }}</title>
<!--[if mso]><noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript><![endif]-->
<style>
  @media only screen and (max-width:620px){
    .container{width:100% !important}
    .px{padding-left:20px !important;padding-right:20px !important}
    .stack{display:block !important;width:100% !important}
    .kcell{display:block !important;width:100% !important;padding-bottom:0 !important;border-bottom:0 !important}
    .vcell{display:block !important;width:100% !important;padding-top:2px !important}
    .h1{font-size:24px !important}
  }
</style>
</head>
<body style="margin:0;padding:0;background-color:#EEF1F0;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%">
<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:#EEF1F0">{{ $r->name }} from {{ $r->organization }} · {{ $eventType }} · {{ $visitors }} visitors · {{ $r->contact }}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#EEF1F0">
<tr><td align="center" style="padding:28px 12px">
  <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
  <table role="presentation" class="container" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;background-color:#FFFFFF;border-radius:14px;overflow:hidden;border:1px solid {{ $line }}">

    {{-- Night header band with text wordmark --}}
    <tr><td class="px" bgcolor="{{ $night }}" style="background-color:{{ $night }};padding:26px 32px 22px 32px">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
          <td valign="middle" style="font-family:{!! $font !!};color:#FFFFFF">
            <span style="font-family:Georgia,'Times New Roman',serif;font-size:26px;line-height:30px;font-weight:bold;color:#FFFFFF;letter-spacing:-0.5px">Mawsim</span>
            <span style="font-size:20px;line-height:30px;color:{{ $saffron }};padding:0 6px">&middot;</span>
            <span dir="rtl" lang="ar" style="font-family:Tahoma,Arial,sans-serif;font-size:21px;line-height:30px;font-weight:bold;color:{{ $saffron }}">موسم</span>
          </td>
          <td valign="middle" align="right" style="font-family:{!! $font !!};font-size:11px;line-height:16px;letter-spacing:1.4px;text-transform:uppercase;color:#A9BBC2">Demo request</td>
        </tr>
      </table>
      {{-- string of lights --}}
      <div style="font-size:9px;line-height:12px;letter-spacing:9px;color:{{ $saffron }};padding-top:14px">&#9679;<span style="color:#FFE9B8">&#9679;</span>&#9679;<span style="color:#2C7DA5">&#9679;</span>&#9679;<span style="color:#FFE9B8">&#9679;</span>&#9679;<span style="color:#127A6D">&#9679;</span>&#9679;<span style="color:#FFE9B8">&#9679;</span>&#9679;<span style="color:#2C7DA5">&#9679;</span>&#9679;</div>
    </td></tr>
    <tr><td height="4" bgcolor="{{ $saffron }}" style="background-color:{{ $saffron }};font-size:0;line-height:0;height:4px">&nbsp;</td></tr>

    {{-- Lead --}}
    <tr><td class="px" style="padding:30px 32px 6px 32px;font-family:{!! $font !!};color:{{ $ink }}">
      <div style="font-size:12px;line-height:18px;letter-spacing:1.2px;text-transform:uppercase;color:#875800;font-weight:bold">New demo request #{{ $r->id }}</div>
      <h1 class="h1" style="margin:8px 0 0 0;font-family:Georgia,'Times New Roman',serif;font-size:28px;line-height:34px;font-weight:bold;color:{{ $ink }}" @if($isRtlText($r->organization)) dir="rtl" @endif>{{ $r->organization }}</h1>
      <p style="margin:8px 0 0 0;font-size:15px;line-height:23px;color:{{ $muted }}"><bdi>{{ $r->name }}</bdi> would like to see Mawsim for {{ $r->event_type ? 'a '.mb_strtolower($eventType) : 'their event' }}.</p>
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:16px">
        <tr>
          <td style="background-color:{{ $saffronSoft }};border-radius:999px;padding:6px 12px;font-family:{!! $font !!};font-size:13px;line-height:16px;color:#5E3D00;font-weight:bold">{{ $eventType }}</td>
          <td width="8" style="font-size:0;line-height:0">&nbsp;</td>
          <td style="background-color:#E8EDEB;border-radius:999px;padding:6px 12px;font-family:{!! $font !!};font-size:13px;line-height:16px;color:{{ $ink }}">{{ $visitors }} visitors</td>
        </tr>
      </table>
    </td></tr>

    {{-- Reply button / phone --}}
    <tr><td class="px" style="padding:22px 32px 4px 32px;font-family:{!! $font !!}">
      @if($email)
        <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td>
          <!--[if mso]>
          <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="mailto:{{ $email }}?subject={{ rawurlencode($replySubject) }}" style="height:46px;v-text-anchor:middle;width:260px;" arcsize="50%" stroke="f" fillcolor="{{ $saffron }}">
            <w:anchorlock/><center style="color:#1D1300;font-family:Arial,sans-serif;font-size:15px;font-weight:bold;">Reply to {{ $r->name }}</center>
          </v:roundrect>
          <![endif]-->
          <!--[if !mso]><!-- -->
          <a href="mailto:{{ $email }}?subject={{ rawurlencode($replySubject) }}" style="display:inline-block;background-color:{{ $saffron }};color:#1D1300;font-family:{!! $font !!};font-size:15px;line-height:20px;font-weight:bold;text-decoration:none;padding:13px 26px;border-radius:999px;mso-hide:all">Reply to {{ $r->name }} &rarr;</a>
          <!--<![endif]-->
        </td></tr></table>
        <p style="margin:10px 0 0 0;font-size:13px;line-height:19px;color:{{ $muted }}">Or just hit reply: this email's Reply-To is <a href="mailto:{{ $email }}" style="color:{{ $ink }}">{{ $email }}</a>.</p>
      @else
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:{{ $saffronSoft }};border-radius:12px">
          <tr><td style="padding:14px 18px;font-family:{!! $font !!}">
            <div style="font-size:12px;line-height:18px;letter-spacing:1px;text-transform:uppercase;color:#875800;font-weight:bold">Call or message</div>
            <div style="font-family:'IBM Plex Mono',Menlo,Consolas,monospace;font-size:22px;line-height:30px;font-weight:bold;color:{{ $ink }};padding-top:2px" dir="ltr"><a href="tel:{{ $r->contactPhoneDigits() }}" style="color:{{ $ink }};text-decoration:none">{{ $phone }}</a></div>
            <div style="font-size:13px;line-height:19px;color:{{ $muted }}">No email given. Reply by phone or WhatsApp.</div>
          </td></tr>
        </table>
      @endif
    </td></tr>

    {{-- Details --}}
    <tr><td class="px" style="padding:24px 32px 0 32px;font-family:{!! $font !!}">
      <div style="font-size:12px;line-height:18px;letter-spacing:1.2px;text-transform:uppercase;color:{{ $muted }};font-weight:bold;padding-bottom:8px">Details</div>
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top:1px solid {{ $line }}">
        @foreach($rows as $label => $value)
          <tr>
            <td class="kcell" width="170" valign="top" style="width:170px;padding:11px 12px 11px 0;border-bottom:1px solid {{ $line }};font-family:{!! $font !!};font-size:13px;line-height:20px;color:{{ $muted }}">{{ $label }}</td>
            <td class="vcell" valign="top" @if($label !== 'Contact' && $isRtlText($value)) dir="rtl" @endif style="text-align:left;padding:11px 0;border-bottom:1px solid {{ $line }};font-family:{!! $font !!};font-size:15px;line-height:21px;color:{{ $ink }}">
              @if($label === 'Contact')
                @if($email)
                  <a href="mailto:{{ $email }}" style="color:{{ $ink }};font-weight:bold">{{ $r->contact }}</a> <span style="color:{{ $muted }};font-size:13px">(email)</span>
                @else
                  <span dir="ltr" style="font-weight:bold">{{ $r->contact }}</span> <span style="color:{{ $muted }};font-size:13px">(phone)</span>
                @endif
              @else
                {{ $value }}
              @endif
            </td>
          </tr>
        @endforeach
      </table>
    </td></tr>

    {{-- Notes --}}
    <tr><td class="px" style="padding:22px 32px 6px 32px;font-family:{!! $font !!}">
      <div style="font-size:12px;line-height:18px;letter-spacing:1.2px;text-transform:uppercase;color:{{ $muted }};font-weight:bold;padding-bottom:8px">Notes</div>
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr><td style="background-color:#F6F8F7;border-left:4px solid {{ $saffron }};border-radius:4px;padding:14px 16px;font-family:{!! $font !!};font-size:15px;line-height:23px;color:{{ $r->notes ? $ink : $muted }}" @if($isRtlText($r->notes)) dir="rtl" @endif>{!! $r->notes ? nl2br(e($r->notes)) : '<em>No notes.</em>' !!}</td></tr>
      </table>
    </td></tr>

    {{-- Admin --}}
    <tr><td class="px" style="padding:22px 32px 30px 32px;font-family:{!! $font !!};font-size:14px;line-height:21px;color:{{ $muted }}">
      Track it in the admin panel: <a href="{{ $adminUrl }}" style="color:{{ $ink }};font-weight:bold">Demo request #{{ $r->id }}</a> &middot; <a href="{{ $adminListUrl }}" style="color:{{ $ink }}">All requests</a>
    </td></tr>

    {{-- Footer --}}
    <tr><td class="px" bgcolor="{{ $night2 }}" style="background-color:{{ $night2 }};padding:18px 32px;font-family:{!! $font !!};font-size:12px;line-height:19px;color:#A9BBC2">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
          <td class="stack" valign="top" style="font-family:{!! $font !!};font-size:12px;line-height:19px;color:#A9BBC2">
            Request <b style="color:#EEF3F1">#{{ $r->id }}</b> &middot; {{ $submittedAt }}<br>
            Language: {{ $localeLabel }}
          </td>
          <td class="stack" valign="top" align="right" style="font-family:{!! $font !!};font-size:12px;line-height:19px;color:#A9BBC2">
            <a href="{{ $adminListUrl }}" style="color:{{ $saffron }};text-decoration:none">Open admin list &rarr;</a>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
  <!--[if mso]></td></tr></table><![endif]-->
  <p style="margin:14px 0 0 0;font-family:{!! $font !!};font-size:11px;line-height:16px;color:#8A989E">Sent by the Mawsim website (AWJ Systems) from the &ldquo;Book a demo&rdquo; form.</p>
</td></tr>
</table>
</body>
</html>
