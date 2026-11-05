@push('head')
<style>
    .vb-event { display: flex; gap: 14px; align-items: center; }
    .vb-event-ico { width: 52px; height: 52px; border-radius: var(--r-md); background: var(--brand-soft); color: var(--brand-text); display: grid; place-items: center; font-size: 24px; flex: none; }
    .vb-event-meta { min-width: 0; }
    .vb-event-title { font-size: 19px; font-weight: 800; line-height: 1.35; margin: 0; }
    .vb-event-sub { display: flex; flex-wrap: wrap; gap: 4px 14px; font-size: 13.5px; margin-top: 4px; }
    .vb-event-sub span { display: inline-flex; gap: 6px; align-items: center; }
    .vb-section-title { font-size: 16px; font-weight: 800; display: flex; align-items: center; gap: 8px; margin: 4px 0 12px; }
    .vb-lead { margin: 4px 0 14px; font-size: 14px; }
    .vb-form { display: grid; gap: 12px; }
    .vb-phone { display: flex; align-items: stretch; direction: ltr; border: 1.5px solid var(--hairline); border-radius: var(--r-md); background: var(--surface); overflow: hidden; }
    .vb-phone:focus-within, .vb-otp:focus { border-color: var(--brand); outline: none; }
    .vb-cc { display: grid; place-items: center; padding: 0 14px; background: var(--brand-soft); color: var(--brand-text); font-weight: 700; font-size: 16px; }
    .vb-phone input { flex: 1; min-width: 0; border: 0; outline: none; height: 54px; padding: 0 14px; font: inherit; font-size: 19px; letter-spacing: .04em; background: transparent; color: inherit; }
    .vb-otp { width: 100%; height: 60px; border: 1.5px solid var(--hairline); border-radius: var(--r-md); background: var(--surface); color: inherit; font: inherit; font-size: 28px; font-weight: 700; letter-spacing: .5em; text-align: center; direction: ltr; padding-inline-start: .5em; }
    .vb-links { display: flex; justify-content: space-between; gap: 10px; margin-top: 12px; }
    .vb-link { background: none; border: 0; padding: 6px 0; font: inherit; font-size: 14px; font-weight: 600; color: var(--brand-text); cursor: pointer; display: inline-flex; gap: 6px; align-items: center; }
    .vb-label { font-weight: 700; font-size: 14px; margin: 14px 0 8px; }
    .vb-dates { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 4px; scrollbar-width: none; }
    .vb-date { position: relative; flex: none; min-width: 76px; display: grid; justify-items: center; gap: 2px; padding: 10px 12px; border: 1.5px solid var(--hairline); border-radius: var(--r-md); background: var(--surface); cursor: pointer; text-align: center; }
    .vb-date input { position: absolute; opacity: 0; pointer-events: none; }
    .vb-date-day { font-size: 12.5px; color: var(--muted); font-weight: 600; }
    .vb-date-num { font-size: 15px; font-weight: 800; }
    .vb-date-left { font-size: 11.5px; color: var(--error); font-weight: 700; }
    .vb-date:has(input:checked) { border-color: var(--brand); background: var(--brand-soft); }
    .vb-date:has(input:checked) .vb-date-day { color: var(--brand-text); }
    .vb-date.is-off { opacity: .5; cursor: not-allowed; }
    .vb-types { margin-top: 14px; }
    .vb-type { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 0; border-bottom: 1px solid var(--hairline); }
    .vb-type:last-child { border-bottom: 0; }
    .vb-type.is-off { opacity: .55; }
    .vb-type-meta { min-width: 0; }
    .vb-type-name { font-weight: 700; font-size: 15.5px; display: flex; align-items: center; gap: 8px; }
    .vb-dot { width: 10px; height: 10px; border-radius: 50%; flex: none; }
    .vb-type-sub { font-size: 13px; margin-top: 2px; }
    .vb-type-sub .money { font-weight: 700; color: var(--text); }
    .vb-stepper { display: flex; align-items: center; gap: 6px; flex: none; direction: ltr; }
    .vb-step { width: 40px; height: 40px; border-radius: 50%; border: 1.5px solid var(--hairline); background: var(--surface); color: var(--brand-text); font-size: 20px; display: grid; place-items: center; cursor: pointer; }
    .vb-step:disabled { cursor: not-allowed; }
    .vb-stepper input { width: 44px; height: 40px; text-align: center; border: 0; background: transparent; font: inherit; font-size: 18px; font-weight: 800; color: inherit; -moz-appearance: textfield; }
    .vb-stepper input::-webkit-outer-spin-button, .vb-stepper input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    .vb-total { display: flex; justify-content: space-between; align-items: center; padding: 14px 0 12px; margin-top: 4px; border-top: 1px dashed var(--hairline); font-weight: 800; font-size: 15.5px; }
    .vb-total .money { font-size: 22px; font-weight: 800; }
    .vb-pay { min-height: 58px; font-size: 17px; }
    .vb-secure { text-align: center; font-size: 13px; margin: 10px 0 0; }
    .vb-ticket { padding: 16px; }
    .vb-ticket-head { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 10px; }
    .vb-badge { display: inline-flex; align-items: center; gap: 6px; height: 28px; padding: 0 12px; border-radius: 999px; font-weight: 700; font-size: 13px; color: #fff; background: var(--type-color, var(--brand)); }
    .vb-ticket-event { font-weight: 800; font-size: 16px; }
    .vb-ticket-when { font-size: 13.5px; margin: 2px 0 12px; display: flex; gap: 6px; align-items: center; }
    .vb-ticket .qr-panel { width: min(100%, 240px); }
    .vb-ticket-foot { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 8px; font-size: 13px; margin: 10px 0 12px; }
    .vb-save { min-height: 46px; font-size: 14.5px; }
    .vb-who { display: flex; justify-content: space-between; align-items: center; gap: 10px; font-size: 14px; margin-top: 4px; }
    .vb-who .muted { display: inline-flex; gap: 6px; align-items: center; }
</style>
@endpush
