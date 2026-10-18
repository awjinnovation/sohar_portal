{{-- <x-admin.detail-list :items="['Label' => 'value', 'Other' => $html]" />  (values are escaped unless HtmlString) or use slot with <dt>/<dd> --}}
@props(['items' => []])
<dl {{ $attributes->merge(['class' => 'dl']) }}>
    @foreach($items as $dlLabel => $dlValue)
        <dt>{{ __((string) $dlLabel) }}</dt><dd>{{ $dlValue === null || $dlValue === '' ? '—' : $dlValue }}</dd>
    @endforeach
    {{ $slot }}
</dl>
