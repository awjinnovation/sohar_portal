{{-- Offline check by the system's signature (docs/gate_signed_tickets.md §2) inside admin/tenants/form. --}}
@php
    $signedMode = old('s.gate.signed_check', \App\Services\Gate\SignedCheckSettings::mode($tenant));
    $signedModes = ['off' => 'موقوف', 'always' => 'طوال اليوم', 'window' => 'خلال فترة محددة كل يوم'];
@endphp
<x-admin.card id="sec-gate-signed" title="التحقق من التذاكر دون اتصال" icon="shield-check" subtitle="تحمل كل تذكرة توقيعًا من النظام، فتتحقق منه البوابة المنقطعة عن الاتصال دون حاجة إلى قائمة التذاكر.">
    <div class="row">
        <div class="col-md-4 field">
            <label class="form-label" for="f_gate_signed_check">{{ __('قبول التذكرة بتوقيع النظام عند انقطاع الاتصال') }}</label>
            <select name="s[gate][signed_check]" id="f_gate_signed_check" class="form-select {{ $errors->has('s.gate.signed_check') ? 'is-invalid' : '' }}">
                @foreach($signedModes as $value => $text)
                    <option value="{{ $value }}" @selected($signedMode === $value)>{{ __($text) }}</option>
                @endforeach
            </select>
            @error('s.gate.signed_check')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        @include('admin.tenants._field', ['key' => 'gate.signed_check_from', 'label' => 'من الساعة', 'type' => 'time', 'col' => 'col-md-4', 'ltr' => true, 'help' => 'بتوقيت الفعالية.'])
        @include('admin.tenants._field', ['key' => 'gate.signed_check_to', 'label' => 'إلى الساعة', 'type' => 'time', 'col' => 'col-md-4', 'ltr' => true, 'help' => 'إذا كانت البداية بعد النهاية امتدت الفترة إلى ما بعد منتصف الليل.'])
        <div class="col-12 form-text mt-0">{{ __('إذا لم تجد البوابة المنقطعة عن الاتصال التذكرة في قائمتها، قبلتها عند صحة توقيعها ما دامت غير مستخدمة ولا ملغاة، وعرضت أنها صالحة بتوقيع النظام، ثم أرسلت الدخول إلى النظام عند عودة الاتصال. تُوقَّع التذاكر دائمًا، ويحدد هذا الإعداد وقت قبولها بالتوقيع فقط.') }}</div>
        <div class="col-12"><h3 class="h6 fw-bold mt-3 mb-1">{{ __('مفاتيح التوقيع') }}</h3></div>
        <div class="col-12 form-text mt-0">{{ __('ينشئ النظام مفتاحًا جديدًا لكل يوم تلقائيًا، ولا تحمل أجهزة البوابات إلا مفاتيح التحقق، فلا يمكن إصدار تذكرة من جهاز مفقود. إذا اشتبهت في تسرّب المفاتيح فغيّرها كلها بهذا الزر: تتغير رموز QR التي تظهر بعد ذلك، وتبقى الصور الملتقطة قبله صالحة عند الاتصال ومن قائمة البوابة.') }}</div>
        @if(($mode ?? null) === 'current')
            <div class="col-12 mt-2">
                <button type="submit" form="signKeysForm" class="btn btn-sm" data-busy>
                    <i class="bi bi-arrow-repeat"></i> {{ __('تغيير مفاتيح التوقيع الآن') }}
                </button>
            </div>
        @endif
    </div>
</x-admin.card>
