import { useEffect, useRef, useState, type FormEvent } from 'react';
import type { Tenant } from '../api';
import type { Strings } from '../i18n';
import { Icon } from '../icons';
import type { PendingOtp } from '../storage';

/** Phone, then the 6-digit code (one card, two steps: the page's data-visitor-step). */
export function SignIn(props: {
  tenant: Tenant;
  t: Strings;
  pending: PendingOtp | null;
  busy: boolean;
  onSend: (phone: string) => void;
  onVerify: (code: string) => void;
  onChangeNumber: () => void;
}) {
  const { tenant, t, pending, busy, onSend, onVerify, onChangeNumber } = props;
  const [phone, setPhone] = useState('');
  const [code, setCode] = useState('');
  const codeRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    setCode('');
    if (pending) codeRef.current?.focus();
  }, [pending]);

  const send = (e: FormEvent) => {
    e.preventDefault();
    if (!busy && phone.trim()) onSend(phone);
  };
  const verify = (e: FormEvent) => {
    e.preventDefault();
    if (!busy && code.length >= 4) onVerify(code);
  };

  if (!pending) {
    return (
      <section className="s-card" data-visitor-step="phone">
        <h2>
          <Icon name="phone" /> {t.yourPhone}
        </h2>
        <p className="muted vb-lead">{t.phoneLead}</p>
        <form className="vb-form" onSubmit={send} noValidate>
          <label className="vb-phone">
            <span className="vb-cc num">+{tenant.country_code}</span>
            <input
              type="tel"
              name="phone_number"
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
              inputMode="numeric"
              autoComplete="tel-national"
              placeholder={tenant.phone_hint}
              maxLength={15}
              required
              aria-label={t.phoneNumber}
            />
          </label>
          <button type="submit" className="btn btn-primary" disabled={busy || !phone.trim()}>
            {busy ? <span className="vb-spin" aria-hidden="true" /> : <Icon name="send" />} {t.sendCode}
          </button>
        </form>
      </section>
    );
  }

  return (
    <section className="s-card" data-visitor-step="otp">
      <h2>
        <Icon name="shield-lock" /> {t.codeTitle}
      </h2>
      <p className="muted vb-lead">
        {t.codeLead} <span className="ltr num">{pending.phone_masked}</span>
      </p>
      <form className="vb-form" onSubmit={verify} noValidate>
        <input
          ref={codeRef}
          type="text"
          name="otp"
          className="vb-otp num"
          value={code}
          onChange={(e) => {
            const digits = e.target.value.replace(/\D/g, '').slice(0, 6);
            setCode(digits);
            if (digits.length === 6 && !busy) onVerify(digits);
          }}
          inputMode="numeric"
          autoComplete="one-time-code"
          pattern="[0-9]*"
          maxLength={6}
          required
          placeholder="••••••"
          aria-label={t.codeTitle}
        />
        <button type="submit" className="btn btn-primary" disabled={busy || code.length < 4}>
          {busy ? <span className="vb-spin" aria-hidden="true" /> : <Icon name="box-arrow-in-right" />} {t.verify}
        </button>
      </form>
      <div className="vb-links">
        <button type="button" className="vb-link" disabled={busy} onClick={() => onSend(pending.phone)}>
          <Icon name="arrow-repeat" /> {t.resend}
        </button>
        <button type="button" className="vb-link" disabled={busy} onClick={onChangeNumber}>
          <Icon name="pencil" /> {t.changeNumber}
        </button>
      </div>
    </section>
  );
}
