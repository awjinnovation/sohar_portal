{{-- Amwal Pay SmartBox (docs/payments_amwal.md). $config is signed on the
     server (SecureHash); the SmartBox callbacks send the visitor to our
     return / cancel URLs, where the result is verified again. --}}
@extends('layouts.public')
@php
    $isAr = $locale === 'ar';
    $t = fn ($ar, $en) => $isAr ? $ar : $en;
    $lang = $locale;
    // The link is signed: changing ?lang= here would invalidate it.
    $hideLangSwitch = true;
@endphp
@section('title', $t('الدفع', 'Payment'))
@section('band-sub', $t('دفع آمن عبر أموال', 'Secure payment by Amwal Pay'))

@push('head')
    {{-- The theme's .note / .btn set display, which would override [hidden]. --}}
    <style>.amwal-pay [hidden] { display: none !important; }</style>
@endpush

@section('content')
<div class="amwal-pay">
    <section class="s-card">
        <div class="result">
            <div class="result-ico info"><i class="bi bi-credit-card-2-front"></i></div>
            <h1>{{ $payment->payment_type === 'topup' ? $t('شحن المحفظة', 'Wallet top-up') : $t('شراء تذاكر', 'Ticket purchase') }}</h1>
            <x-admin.money :minor="(int) $payment->amount_minor" :currency="$payment->currency" size="hero" />
        </div>
        <dl class="rows">
            <div><dt>{{ $t('رقم المعاملة', 'Transaction ID') }}</dt><dd class="mono ltr">{{ $payment->transaction_id }}</dd></div>
        </dl>
    </section>

    <div id="amwal-loading" class="note"><i class="bi bi-hourglass-split"></i><div>{{ $t('جارٍ فتح صفحة الدفع…', 'Opening the payment page…') }}</div></div>
    <div id="amwal-error" class="note warn" hidden><i class="bi bi-exclamation-triangle-fill"></i><div><b>{{ $t('تعذّر إتمام الدفع.', 'The payment could not be completed.') }}</b> <span id="amwal-error-text"></span></div></div>
    <div id="amwal-closed" class="note" hidden><i class="bi bi-info-circle-fill"></i><div>{{ $t('أُغلقت نافذة الدفع ولم يُخصم أي مبلغ.', 'The payment window was closed. Nothing was charged.') }}</div></div>

    <div class="actions">
        <button id="amwal-pay" class="btn btn-primary" type="button" disabled><i class="bi bi-lock-fill"></i> {{ $t('ادفع الآن', 'Pay now') }}</button>
        <a id="amwal-retry" class="btn btn-neutral" href="{{ $retryUrl }}" hidden><i class="bi bi-arrow-clockwise"></i> {{ $t('أعد المحاولة', 'Try again') }}</a>
        <a id="amwal-cancel" class="btn btn-neutral" href="{{ $cancelUrl }}"><i class="bi bi-x-lg"></i> {{ $t('إلغاء الدفع', 'Cancel payment') }}</a>
    </div>
</div>
@endsection

@push('scripts')
    <script>
        (function () {
            'use strict';
            var config = @json($config);
            var returnUrl = @json($returnUrl);
            var unreachable = @json($t('تعذّر الوصول إلى بوابة الدفع. أعد المحاولة بعد قليل.', 'The payment gateway cannot be reached. Please try again shortly.'));
            var el = function (id) { return document.getElementById(id); };
            var show = function (id, on) { el(id).hidden = !on; };
            var clean = function (v) { return v === undefined || v === null || v === 'null' || v === 'undefined' ? '' : String(v); };

            function fail(message) {
                show('amwal-loading', false);
                show('amwal-closed', false);
                el('amwal-error-text').textContent = message || '';
                show('amwal-error', true);
                show('amwal-retry', true);
                el('amwal-pay').hidden = true;
            }

            function open() {
                show('amwal-error', false);
                show('amwal-closed', false);
                try {
                    window.SmartBox.Checkout.showSmartBox();
                } catch (e) {
                    fail(unreachable);
                }
            }

            window.amwalReady = function () {
                if (!window.SmartBox || !window.SmartBox.Checkout) {
                    fail(unreachable);
                    return;
                }
                SmartBox.Checkout.configure = Object.assign({}, config, {
                    // Relayed to our server, which checks the secure hash and/or asks Amwal.
                    completeCallback: function (data) {
                        var outer = (data && data.data) || {};
                        var r = outer.data || {};
                        var q = new URLSearchParams();
                        q.set('amount', clean(r.amount));
                        q.set('currencyId', clean(r.currencyId));
                        q.set('customerId', clean(r.customerId));
                        q.set('customerTokenId', clean(r.customerTokenId));
                        q.set('merchantReference', clean(r.merchantReference) || config.MerchantReference);
                        q.set('responseCode', clean(outer.responseCode));
                        q.set('transactionId', clean(r.transactionId));
                        q.set('transactionTime', clean(r.transactionTime));
                        q.set('secureHashValue', clean(r.secureHashValue));
                        window.location.href = returnUrl + (returnUrl.indexOf('?') === -1 ? '?' : '&') + q.toString();
                    },
                    errorCallback: function (data) {
                        var list = data && data.data && data.data.errorList;
                        fail(Array.isArray(list) && list.length ? String(list[0]) : '');
                    },
                    cancelCallback: function () {
                        show('amwal-loading', false);
                        show('amwal-closed', true);
                    }
                });
                show('amwal-loading', false);
                el('amwal-pay').disabled = false;
                el('amwal-pay').addEventListener('click', open);
                open();
            };
            window.amwalFailed = function () { fail(unreachable); };
        })();
    </script>
    <script src="{{ $script }}" onload="amwalReady()" onerror="amwalFailed()"></script>
@endpush
