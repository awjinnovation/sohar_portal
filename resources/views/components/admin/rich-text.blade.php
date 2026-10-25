{{-- Light rich-text editor (paragraphs, bold, italic, lists, small headings, links) backed by a textarea.
     The server sanitizes the HTML (App\Support\HtmlSanitizer). The textarea keeps the id for the AI copilot.
     <x-admin.rich-text name="description[ar]" id="description_ar" label="…" :value="$html" dir="rtl" /> --}}
@props(['name', 'id', 'label' => null, 'value' => null, 'dir' => 'rtl', 'help' => null, 'rows' => 8])
@php $rtErr = trim(str_replace(['[', ']'], ['.', ''], $name), '.'); @endphp
<div class="field rich-field" data-rich-text>
    @if($label)<label for="{{ $id }}" class="form-label" data-rich-label>{{ __($label) }}</label>@endif
    <div class="rich-toolbar" role="toolbar" aria-label="{{ __('تنسيق النص') }}" dir="ltr">
        <button type="button" class="rt-btn" data-cmd="bold" title="{{ __('غامق') }}" aria-label="{{ __('غامق') }}"><i class="bi bi-type-bold"></i></button>
        <button type="button" class="rt-btn" data-cmd="italic" title="{{ __('مائل') }}" aria-label="{{ __('مائل') }}"><i class="bi bi-type-italic"></i></button>
        <button type="button" class="rt-btn" data-cmd="h3" title="{{ __('عنوان فرعي') }}" aria-label="{{ __('عنوان فرعي') }}"><i class="bi bi-type-h3"></i></button>
        <span class="rt-sep"></span>
        <button type="button" class="rt-btn" data-cmd="insertUnorderedList" title="{{ __('قائمة نقطية') }}" aria-label="{{ __('قائمة نقطية') }}"><i class="bi bi-list-ul"></i></button>
        <button type="button" class="rt-btn" data-cmd="insertOrderedList" title="{{ __('قائمة مرقمة') }}" aria-label="{{ __('قائمة مرقمة') }}"><i class="bi bi-list-ol"></i></button>
        <span class="rt-sep"></span>
        <button type="button" class="rt-btn" data-cmd="link" title="{{ __('رابط') }}" aria-label="{{ __('رابط') }}" data-prompt="{{ __('اكتب عنوان الرابط') }}"><i class="bi bi-link-45deg"></i></button>
        <button type="button" class="rt-btn" data-cmd="removeFormat" title="{{ __('إزالة التنسيق') }}" aria-label="{{ __('إزالة التنسيق') }}"><i class="bi bi-eraser"></i></button>
        <button type="button" class="rt-btn ms-auto" data-cmd="source" title="{{ __('عرض HTML') }}" aria-label="{{ __('عرض HTML') }}"><i class="bi bi-code-slash"></i></button>
    </div>
    <div class="rich-area form-control {{ $errors->has($rtErr) ? 'is-invalid' : '' }}" contenteditable="true" dir="{{ $dir }}" role="textbox" aria-multiline="true" @if($label) aria-label="{{ __($label) }}" @endif style="min-height: {{ max(4, (int) $rows) * 1.6 }}rem"></div>
    <textarea name="{{ $name }}" id="{{ $id }}" rows="{{ $rows }}" dir="ltr" class="form-control rich-source d-none" data-rich>{{ $value }}</textarea>
    @error($rtErr)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    @if($help)<div class="form-text">{{ __($help) }}</div>@endif
</div>
