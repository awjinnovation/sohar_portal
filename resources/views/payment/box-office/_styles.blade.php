@push('head')
<style>
    .bo-event { display: flex; gap: 14px; align-items: center; margin-bottom: 16px; }
    .bo-event-ico { width: 48px; height: 48px; border-radius: var(--r-md); background: var(--brand-soft); color: var(--brand-text); display: grid; place-items: center; font-size: 22px; flex: none; }
    .bo-event-meta { min-width: 0; }
    .bo-event-title { font-size: 18px; font-weight: 800; line-height: 1.35; }
    .bo-event-sub { display: flex; flex-wrap: wrap; gap: 4px 14px; font-size: 13.5px; margin-top: 2px; }
    .bo-event-sub span { display: inline-flex; gap: 6px; align-items: center; }
    .bo-lines { margin-top: 4px; }
    .bo-lines dt { color: var(--text); font-weight: 600; font-size: 14.5px; }
    .bo-type-qty { font-weight: 600; margin-inline-start: 6px; }
    .bo-total dt { color: var(--text); font-weight: 800; font-size: 15.5px; }
    .bo-total .money { font-size: 22px; font-weight: 800; }
    .bo-facts { margin-top: 14px; padding-top: 12px; border-top: 1px dashed var(--hairline); }
    .bo-timer { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 12px 16px; border-radius: var(--r-lg); background: var(--warning-container); color: var(--on-warning-container); font-weight: 600; margin-bottom: var(--section-gap); }
    .bo-timer b { font-family: var(--font-num), var(--font-en), sans-serif; font-size: 20px; font-variant-numeric: tabular-nums; direction: ltr; unicode-bidi: isolate; }
    .bo-timer.is-low { background: var(--error-container); color: var(--on-error-container); }
    .bo-pay .btn { min-height: 58px; font-size: 17px; }
    .bo-pay .money { font-size: 1em; font-weight: 800; }
    .bo-pay .money .cur { opacity: .9; }
    .bo-secure { text-align: center; font-size: 13px; margin: 10px 0 0; }
    .bo-ticket { padding: 16px; }
    .bo-ticket-head { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 12px; }
    .bo-badge { display: inline-flex; align-items: center; gap: 6px; height: 28px; padding: 0 12px; border-radius: 999px; font-weight: 700; font-size: 13px; color: #fff; background: var(--type-color, var(--brand)); }
    .bo-ticket .qr-panel { width: min(100%, 240px); }
    .bo-ticket-foot { display: flex; justify-content: space-between; align-items: center; gap: 10px; font-size: 13px; }
</style>
@endpush
