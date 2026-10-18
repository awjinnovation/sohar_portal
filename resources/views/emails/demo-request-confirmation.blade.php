{{-- Thank-you email to the requester, in their language (Arabic = RTL). Table layout + inline styles; no remote images. --}}
@php
    $night = '#0C1820';
    $night2 = '#132530';
    $saffron = '#E9A21B';
    $ink = '#0E1C24';
    $muted = '#4F6068';
    $line = '#E1E7E5';
    $dir = $rtl ? 'rtl' : 'ltr';
    $start = $rtl ? 'right' : 'left';
    $end = $rtl ? 'left' : 'right';
    $font = $rtl ? "Tahoma,'Readex Pro','Segoe UI',Arial,sans-serif" : "'Readex Pro','Segoe UI',Tahoma,Arial,sans-serif";
    $display = $rtl ? "Tahoma,Arial,sans-serif" : "Georgia,'Times New Roman',serif";
@endphp
<!DOCTYPE html>
<html lang="{{ $lc }}" dir="{{ $dir }}" xmlns="http://www.w3.org/1999/xhtml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="x-apple-disable-message-reformatting">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>{{ __('landing.mail.confirm_subject', [], $lc) }}</title>
<!--[if mso]><noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript><![endif]-->
<style>
  @media only screen and (max-width:620px){
    .container{width:100% !important}
    .px{padding-left:20px !important;padding-right:20px !important}
    .h1{font-size:24px !important;line-height:32px !important}
  }
</style>
</head>
<body dir="{{ $dir }}" style="margin:0;padding:0;background-color:#EEF1F0;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%">
<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:#EEF1F0">{{ __('landing.mail.confirm_intro', ['organization' => $r->organization], $lc) }}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" dir="{{ $dir }}" style="background-color:#EEF1F0">
<tr><td align="center" style="padding:28px 12px">
  <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
  <table role="presentation" class="container" width="600" cellpadding="0" cellspacing="0" border="0" dir="{{ $dir }}" style="width:600px;max-width:600px;background-color:#FFFFFF;border-radius:14px;overflow:hidden;border:1px solid {{ $line }}">

    <tr><td class="px" bgcolor="{{ $night }}" align="{{ $start }}" style="background-color:{{ $night }};padding:26px 32px 22px 32px;text-align:{{ $start }}">
      <span dir="ltr" style="font-family:Georgia,'Times New Roman',serif;font-size:26px;line-height:30px;font-weight:bold;color:#FFFFFF;letter-spacing:-0.5px">Mawsim</span>
      <span style="font-size:20px;line-height:30px;color:{{ $saffron }};padding:0 6px">&middot;</span>
      <span dir="rtl" lang="ar" style="font-family:Tahoma,Arial,sans-serif;font-size:21px;line-height:30px;font-weight:bold;color:{{ $saffron }}">موسم</span>
      <div style="font-size:9px;line-height:12px;letter-spacing:9px;color:{{ $saffron }};padding-top:14px">&#9679;<span style="color:#FFE9B8">&#9679;</span>&#9679;<span style="color:#2C7DA5">&#9679;</span>&#9679;<span style="color:#FFE9B8">&#9679;</span>&#9679;<span style="color:#127A6D">&#9679;</span>&#9679;<span style="color:#FFE9B8">&#9679;</span>&#9679;</div>
    </td></tr>
    <tr><td height="4" bgcolor="{{ $saffron }}" style="background-color:{{ $saffron }};font-size:0;line-height:0;height:4px">&nbsp;</td></tr>

    <tr><td class="px" align="{{ $start }}" style="padding:30px 32px 4px 32px;font-family:{!! $font !!};color:{{ $ink }};text-align:{{ $start }}">
      <h1 class="h1" style="margin:0;font-family:{!! $display !!};font-size:28px;line-height:36px;font-weight:bold;color:{{ $ink }}">{{ __('landing.mail.confirm_title', ['name' => $r->name], $lc) }}</h1>
      <p style="margin:12px 0 0 0;font-size:16px;line-height:26px;color:{{ $ink }}">{{ __('landing.mail.confirm_intro', ['organization' => $r->organization], $lc) }}</p>
    </td></tr>

    <tr><td class="px" align="{{ $start }}" style="padding:24px 32px 0 32px;font-family:{!! $font !!};text-align:{{ $start }}">
      <div style="font-size:13px;line-height:18px;color:#875800;font-weight:bold;padding-bottom:8px;{{ $rtl ? '' : 'letter-spacing:1.2px;text-transform:uppercase;font-size:12px;' }}">{{ __('landing.mail.confirm_summary', [], $lc) }}</div>
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" dir="{{ $dir }}" style="border-top:1px solid {{ $line }}">
        @foreach($rows as $label => $value)
          <tr>
            <td width="170" valign="top" align="{{ $start }}" style="width:170px;padding:10px 0;padding-{{ $end }}:12px;border-bottom:1px solid {{ $line }};font-family:{!! $font !!};font-size:13px;line-height:20px;color:{{ $muted }};text-align:{{ $start }}">{{ $label }}</td>
            <td valign="top" align="{{ $start }}" style="padding:10px 0;border-bottom:1px solid {{ $line }};font-family:{!! $font !!};font-size:15px;line-height:21px;color:{{ $ink }};text-align:{{ $start }}">@if($value === $r->contact)<span dir="ltr">{{ $value }}</span>@else{{ $value }}@endif</td>
          </tr>
        @endforeach
      </table>
    </td></tr>

    <tr><td class="px" align="{{ $start }}" style="padding:24px 32px 30px 32px;font-family:{!! $font !!};text-align:{{ $start }}">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" dir="{{ $dir }}">
        <tr><td style="background-color:#FAEED3;border-{{ $start }}:4px solid {{ $saffron }};border-radius:4px;padding:14px 16px;font-family:{!! $font !!};text-align:{{ $start }}">
          <div style="font-size:15px;line-height:22px;font-weight:bold;color:{{ $ink }}">{{ __('landing.mail.confirm_next', [], $lc) }}</div>
          <div style="font-size:14.5px;line-height:23px;color:{{ $ink }};padding-top:4px">{!! str_replace(e('__CONTACT__'), '<span dir="ltr" style="unicode-bidi:embed;white-space:nowrap">'.e($r->contact).'</span>', e(__('landing.mail.confirm_next_body', ['contact' => '__CONTACT__'], $lc))) !!}</div>
        </td></tr>
      </table>
    </td></tr>

    <tr><td class="px" bgcolor="{{ $night2 }}" align="{{ $start }}" style="background-color:{{ $night2 }};padding:18px 32px;font-family:{!! $font !!};font-size:12.5px;line-height:20px;color:#A9BBC2;text-align:{{ $start }}">
      {{ __('landing.mail.confirm_footer', [], $lc) }}<br>
      <a href="{{ $homeUrl }}" style="color:{{ $saffron }};text-decoration:none" dir="ltr">{{ preg_replace('#^https?://#', '', url('/')) }}</a>
    </td></tr>
  </table>
  <!--[if mso]></td></tr></table><![endif]-->
  <p dir="{{ $dir }}" style="margin:14px auto 0 auto;max-width:560px;font-family:{!! $font !!};font-size:11px;line-height:17px;color:#8A989E;text-align:center">{{ __('landing.mail.confirm_ignore', [], $lc) }}</p>
</td></tr>
</table>
</body>
</html>
