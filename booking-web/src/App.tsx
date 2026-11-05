import { useCallback, useEffect, useRef, useState } from 'react';
import { ApiError, api, type EventPayload, type Lang, type Ticket } from './api';
import { BookingForm, type Order } from './components/BookingForm';
import { Layout, Note, type Tone } from './components/Layout';
import { SignIn } from './components/SignIn';
import { TicketCard } from './components/TicketCard';
import { HOME_URL } from './config';
import { initialLang, strings, type Strings } from './i18n';
import { Icon, type IconName } from './icons';
import { loadAuth, loadPendingOtp, saveAuth, savePendingOtp, type Auth, type PendingOtp } from './storage';
import { applyLang, applyTheme } from './theme';

/** /e/{id} (also /e/{id}/) → id. */
function eventIdFromPath(): number | null {
  const m = /^\/e\/(\d{1,10})\/?$/.exec(location.pathname);
  return m ? Number(m[1]) : null;
}

function newFormKey(): string {
  return crypto.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

type Message = { key: string; data?: Record<string, unknown> };

const NOTICE: Record<string, [Tone, IconName]> = {
  otp_sent: ['info', 'chat-dots'],
  signed_in: ['info', 'person-check'],
  payment_success: ['info', 'check-circle'],
  payment_cancelled: ['warn', 'info-circle'],
  payment_failed: ['error', 'x-circle'],
};

const ERROR: Record<string, [Tone, IconName]> = {
  invalid_phone: ['error', 'exclamation-circle'],
  otp_invalid: ['error', 'exclamation-circle'],
  otp_expired: ['error', 'clock-history'],
  otp_attempts: ['error', 'shield-exclamation'],
  throttled: ['warn', 'hourglass-split'],
  items_required: ['error', 'exclamation-circle'],
  date_required: ['error', 'calendar-x'],
  date_invalid: ['error', 'calendar-x'],
  sold_out: ['error', 'x-octagon'],
  per_order_limit: ['error', 'exclamation-circle'],
  sales_closed: ['error', 'lock'],
  type_not_available: ['error', 'x-circle'],
  network: ['error', 'wifi-off'],
  session_ended: ['warn', 'clock-history'],
  unavailable: ['error', 'x-circle'],
};

/** API error code → message key (+ the numbers some messages show). */
function errorMessage(e: unknown): Message {
  if (!(e instanceof ApiError)) return { key: 'unavailable' };
  const map: Record<string, string> = {
    INVALID_PHONE: 'invalid_phone',
    OTP_INVALID: 'otp_invalid',
    OTP_EXPIRED: 'otp_expired',
    OTP_ATTEMPTS_EXCEEDED: 'otp_attempts',
    TOO_MANY_REQUESTS: 'throttled',
    UNAUTHENTICATED: 'session_ended',
    NETWORK: 'network',
  };
  const key = map[e.code] ?? e.code.toLowerCase();
  return { key: key in ERROR ? key : 'unavailable', data: e.data };
}

function messageText(m: Message, t: Strings, pendingMasked: string): string {
  const d = m.data ?? {};
  switch (m.key) {
    case 'otp_sent':
      return t.otp_sent(pendingMasked);
    case 'otp_invalid':
      return d.attempts_remaining != null ? `${t.otp_invalid} ${t.attemptsLeft}${d.attempts_remaining}` : t.otp_invalid;
    case 'sold_out':
      return d.available != null ? `${t.sold_out} ${t.left}${d.available}` : t.sold_out;
    case 'per_order_limit':
      return `${t.per_order_limit}${d.per_order_max != null ? ` (${t.max}${d.per_order_max})` : ''}.`;
    default:
      return (t as unknown as Record<string, string>)[m.key] ?? t.unavailable;
  }
}

/** Reads ?payment= (success | cancelled | failed, set by the backend after the gateway) once, then drops it from the address. */
function takePaymentResult(): Message | null {
  const url = new URL(location.href);
  const result = url.searchParams.get('payment');
  if (!result) return null;
  url.searchParams.delete('payment');
  history.replaceState(null, '', url.pathname + url.search + url.hash);
  return ['success', 'cancelled', 'failed'].includes(result) ? { key: `payment_${result}` } : null;
}

export function App() {
  const eventId = eventIdFromPath();
  const [requestedLang, setRequestedLang] = useState<Lang | null>(initialLang);
  const [data, setData] = useState<EventPayload | null>(null);
  const [load, setLoad] = useState<'loading' | 'ready' | 'notfound' | 'error'>('loading');
  const [reloadKey, setReloadKey] = useState(0);
  const [auth, setAuth] = useState<Auth | null>(loadAuth);
  const [pending, setPending] = useState<PendingOtp | null>(() => (loadAuth() ? null : loadPendingOtp()));
  const [tickets, setTickets] = useState<Ticket[] | null>(null);
  const [notice, setNotice] = useState<Message | null>(takePaymentResult);
  const [error, setError] = useState<Message | null>(null);
  const [busy, setBusy] = useState(false);
  const [formKey, setFormKey] = useState(newFormKey);
  const notesRef = useRef<HTMLDivElement>(null);

  const lang: Lang = data?.lang ?? requestedLang ?? 'ar';
  const t = strings[lang];

  useEffect(() => applyLang(lang), [lang]);

  // "/" (no event): the configured home page, else a short help card.
  useEffect(() => {
    if (eventId === null && HOME_URL) location.replace(HOME_URL);
  }, [eventId]);

  // The event: public, cached by the API (and a CDN in front of it).
  useEffect(() => {
    if (eventId === null) return;
    const ctrl = new AbortController();
    setLoad((s) => (s === 'ready' ? s : 'loading'));
    api
      .event(eventId, requestedLang, ctrl.signal)
      .then((payload) => {
        setData(payload);
        setLoad('ready');
        applyTheme(payload.tenant.theme);
        document.title = `${payload.event.title || t.event} · ${payload.tenant.name}`;
      })
      .catch((e) => {
        if ((e as Error).name === 'AbortError') return;
        setLoad(e instanceof ApiError && e.status === 404 ? 'notfound' : 'error');
      });
    return () => ctrl.abort();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [eventId, requestedLang, reloadKey]);

  const signOutLocally = useCallback(() => {
    saveAuth(null);
    setAuth(null);
    setTickets(null);
  }, []);

  const loadTickets = useCallback(
    async (signal?: AbortSignal) => {
      if (eventId === null || !auth) return;
      try {
        const res = await api.tickets(eventId, lang, auth.token, signal);
        setTickets(res.tickets);
      } catch (e) {
        if ((e as Error).name === 'AbortError') return;
        if (e instanceof ApiError && e.status === 401) {
          signOutLocally();
          setError({ key: 'session_ended' });
        }
      }
    },
    [eventId, lang, auth, signOutLocally],
  );

  // The visitor's tickets for this event.
  useEffect(() => {
    if (!auth || load !== 'ready') return;
    const ctrl = new AbortController();
    loadTickets(ctrl.signal);
    return () => ctrl.abort();
  }, [auth, load, loadTickets]);

  // Back from a successful payment before the tickets show: look again a few times.
  const polls = useRef(0);
  useEffect(() => {
    if (notice?.key !== 'payment_success' || !auth || tickets === null || tickets.length > 0 || polls.current >= 4) return;
    const id = setTimeout(() => {
      polls.current += 1;
      loadTickets();
    }, 2000);
    return () => clearTimeout(id);
  }, [notice, auth, tickets, loadTickets]);

  // Bring a new message into view (they sit above the form on a phone).
  useEffect(() => {
    if ((notice || error) && load === 'ready') notesRef.current?.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }, [notice, error, load]);

  const changeLang = (next: Lang) => {
    setRequestedLang(next);
    try {
      localStorage.setItem('booking-lang', next);
    } catch {
      /* storage blocked */
    }
    const url = new URL(location.href);
    url.searchParams.set('lang', next);
    history.replaceState(null, '', url.pathname + url.search);
  };

  const run = async (fn: () => Promise<void>) => {
    setBusy(true);
    setError(null);
    try {
      await fn();
    } catch (e) {
      const m = errorMessage(e);
      if (m.key === 'session_ended') signOutLocally();
      setError(m);
    } finally {
      setBusy(false);
    }
  };

  const sendCode = (phone: string) =>
    run(async () => {
      const res = await api.sendOtp(eventId!, phone, lang);
      const next = { phone: res.phone, phone_masked: res.phone_masked, at: Date.now() };
      savePendingOtp(next);
      setPending(next);
      setNotice({ key: 'otp_sent' });
    });

  const verifyCode = (code: string) =>
    run(async () => {
      if (!pending) return;
      try {
        const res = await api.verifyOtp(eventId!, pending.phone, code, lang);
        const next = { token: res.token, expires_at: res.expires_at, phone_masked: res.phone_masked };
        saveAuth(next);
        savePendingOtp(null);
        setPending(null);
        setAuth(next);
        setNotice({ key: 'signed_in' });
      } catch (e) {
        // A used-up or expired code: back to the number.
        if (e instanceof ApiError && ['OTP_EXPIRED', 'OTP_ATTEMPTS_EXCEEDED'].includes(e.code)) {
          savePendingOtp(null);
          setPending(null);
          setNotice(null);
        }
        throw e;
      }
    });

  const changeNumber = () => {
    savePendingOtp(null);
    setPending(null);
    setNotice(null);
    setError(null);
  };

  const checkout = (order: Order) =>
    run(async () => {
      if (!auth) return;
      const res = await api.checkout(eventId!, auth.token, { lang, form_key: formKey, ...order });
      if (res.status === 'pending' && res.payment_url) {
        // The gateway sends the visitor back to /e/{id}?payment=…
        setBusy(true);
        location.assign(res.payment_url);
        await new Promise(() => {}); // keep the button busy while the browser leaves
      }
      setFormKey(newFormKey());
      setNotice({ key: 'payment_success' });
      polls.current = 0;
      await loadTickets();
    });

  const signOut = () => {
    const token = auth?.token;
    signOutLocally();
    setNotice(null);
    setError(null);
    if (token && eventId !== null) api.logout(eventId, token).catch(() => {});
  };

  // ---------------------------------------------------------------- views
  if (eventId === null) {
    return (
      <Layout tenant={null} lang={lang} t={t}>
        <section className="s-card vb-empty">
          <span className="vb-event-ico">
            <Icon name="qr-code" />
          </span>
          <h1 className="vb-event-title">{t.homeTitle}</h1>
          <p className="muted">{t.home}</p>
        </section>
      </Layout>
    );
  }

  if (load === 'notfound' || (load === 'error' && !data)) {
    const notFound = load === 'notfound';
    return (
      <Layout tenant={null} lang={lang} t={t}>
        <section className="s-card vb-empty" data-state={notFound ? 'not-found' : 'error'}>
          <span className="vb-event-ico">
            <Icon name={notFound ? 'calendar-x' : 'wifi-off'} />
          </span>
          <h1 className="vb-event-title">{notFound ? t.notFoundTitle : t.unavailable}</h1>
          <p className="muted">{notFound ? t.notFound : t.loadFailed}</p>
          {!notFound && (
            <button type="button" className="btn btn-primary" onClick={() => setReloadKey((k) => k + 1)}>
              <Icon name="arrow-repeat" /> {t.retry}
            </button>
          )}
        </section>
      </Layout>
    );
  }

  if (!data) {
    return (
      <Layout tenant={null} lang={lang} t={t}>
        <div className="s-card vb-skeleton" aria-busy="true">
          <span />
          <span />
          <span />
        </div>
        <div className="s-card vb-skeleton tall" aria-hidden="true" />
      </Layout>
    );
  }

  const { tenant, event, ticket_types: types } = data;
  const onSale = types.some((type) => type.sales_open);
  const notes = [notice && { m: notice, style: NOTICE[notice.key] }, error && { m: error, style: ERROR[error.key] ?? ERROR.unavailable }].filter(
    (n): n is { m: Message; style: [Tone, IconName] } => !!n && !!n.style,
  );

  return (
    <Layout tenant={tenant} lang={lang} t={t} onLang={changeLang}>
      <section className="s-card vb-event">
        <span className="vb-event-ico">
          <Icon name="ticket-perforated" />
        </span>
        <div className="vb-event-meta">
          <h1 className="vb-event-title">{event.title || t.event}</h1>
          <div className="muted vb-event-sub">
            {event.date_label && (
              <span>
                <Icon name="calendar3" /> {event.date_label}
              </span>
            )}
            {event.time_label && (
              <span className="num">
                <Icon name="clock" /> {event.time_label}
              </span>
            )}
            {event.venue && (
              <span>
                <Icon name="geo-alt" /> {event.venue}
              </span>
            )}
          </div>
        </div>
      </section>

      <div ref={notesRef} data-visitor-messages>
        {notes.map(({ m, style: [tone, icon] }) => (
          <Note key={m.key} tone={tone} icon={icon}>
            {messageText(m, t, pending?.phone_masked ?? '')}
          </Note>
        ))}
      </div>

      {tickets && tickets.length > 0 && (
        <>
          <h2 className="vb-section-title">
            <Icon name="qr-code" /> {t.yourTickets} <span className="muted num">({tickets.length})</span>
          </h2>
          <div data-visitor-tickets={tickets.length}>
            {tickets.map((ticket) => (
              <TicketCard key={ticket.id} ticket={ticket} eventTitle={event.title} brand={tenant.name} filePrefix={tenant.file_prefix} lang={lang} t={t} />
            ))}
          </div>
        </>
      )}

      {!auth ? (
        <SignIn tenant={tenant} t={t} pending={pending} busy={busy} onSend={sendCode} onVerify={verifyCode} onChangeNumber={changeNumber} />
      ) : event.ended ? (
        <Note tone="warn" icon="calendar-x">
          {t.ended}
        </Note>
      ) : !onSale ? (
        <Note tone="warn" icon="lock">
          {t.closed}
        </Note>
      ) : (
        <BookingForm key={formKey} data={data} t={t} hasTickets={!!tickets?.length} busy={busy} onSubmit={checkout} />
      )}

      {auth && (
        <div className="vb-who">
          <span className="muted">
            <Icon name="person-circle" /> {t.signedInAs} <span className="ltr num">{auth.phone_masked}</span>
          </span>
          <button type="button" className="vb-link" onClick={signOut}>
            {t.signOut}
          </button>
        </div>
      )}
    </Layout>
  );
}
