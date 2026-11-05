import { useMemo, useState, type FormEvent } from 'react';
import type { BookingDate, EventPayload } from '../api';
import type { Strings } from '../i18n';
import { Icon } from '../icons';
import { formatMinor } from '../money';
import { Money } from './Layout';

export interface Order {
  booking_date: string | null;
  items: { ticket_type_id: number; quantity: number }[];
}

/** Day of the visit (dated types), quantities per type, live total, pay. The server prices the order again. */
export function BookingForm(props: {
  data: EventPayload;
  t: Strings;
  hasTickets: boolean;
  busy: boolean;
  onSubmit: (order: Order) => void;
}) {
  const { data, t, hasTickets, busy, onSubmit } = props;
  const { tenant, ticket_types: types, dates } = data;
  const [date, setDate] = useState<string | null>(() => dates.find((d) => d.available !== 0)?.date ?? null);
  const [qty, setQty] = useState<Record<number, number>>({});

  const { count, minor } = useMemo(
    () =>
      types.reduce(
        (acc, type) => {
          const q = qty[type.id] ?? 0;
          return { count: acc.count + q, minor: acc.minor + q * type.price_minor };
        },
        { count: 0, minor: 0 },
      ),
    [qty, types],
  );

  const step = (id: number, delta: number, max: number) =>
    setQty((prev) => ({ ...prev, [id]: Math.min(max, Math.max(0, (prev[id] ?? 0) + delta)) }));

  const submit = (e: FormEvent) => {
    e.preventDefault();
    if (busy || count === 0) return;
    onSubmit({
      booking_date: dates.length ? date : null,
      items: types.filter((type) => (qty[type.id] ?? 0) > 0).map((type) => ({ ticket_type_id: type.id, quantity: qty[type.id] })),
    });
  };

  const dayLabel = (d: BookingDate) => (d.relative === 'today' ? t.today : d.relative === 'tomorrow' ? t.tomorrow : d.day_label);

  return (
    <form className="s-card vb-book" id="vbBook" onSubmit={submit} noValidate>
      <h2>
        <Icon name="cart" /> {hasTickets ? t.bookMore : t.chooseTickets}
      </h2>

      {dates.length > 0 && (
        <>
          <div className="vb-label" id="vb-day-label">
            {t.visitDay}
          </div>
          <div className="vb-dates" role="radiogroup" aria-labelledby="vb-day-label">
            {dates.map((d) => {
              const soldOut = d.available === 0;
              return (
                <label key={d.date} className={`vb-date${soldOut ? ' is-off' : ''}`}>
                  <input type="radio" name="booking_date" value={d.date} checked={date === d.date} disabled={soldOut} onChange={() => setDate(d.date)} />
                  <span className="vb-date-day">{dayLabel(d)}</span>
                  <span className="vb-date-num num">{d.num_label}</span>
                  {soldOut && <span className="vb-date-left">{t.soldOutDay}</span>}
                </label>
              );
            })}
          </div>
        </>
      )}

      <div className="vb-types">
        {types.map((type) => {
          const q = qty[type.id] ?? 0;
          const open = type.sales_open;
          return (
            <div key={type.id} className={`vb-type${open ? '' : ' is-off'}`}>
              <div className="vb-type-meta">
                <div className="vb-type-name">
                  <span className="vb-dot" style={{ background: type.color }} />
                  {type.name}
                </div>
                <div className="muted vb-type-sub">
                  {type.price_minor === 0 ? t.free : <Money minor={type.price_minor} currency={tenant.currency} decimals={tenant.currency_decimals} format={formatMinor} />}
                  {type.validity_label && <> · {type.validity_label}</>}
                  {type.admits > 1 && type.admits_label && (
                    <>
                      {' '}
                      · {t.admits} {type.admits_label}
                    </>
                  )}
                  {!open && <> · {t.salesClosed}</>}
                </div>
              </div>
              <div className="vb-stepper">
                <button type="button" className="vb-step" aria-label={t.less} disabled={!open || q === 0} onClick={() => step(type.id, -1, type.max_quantity)}>
                  <Icon name="dash" />
                </button>
                <output className="num" aria-live="polite" aria-label={`${t.quantity}: ${type.name}`}>
                  {q}
                </output>
                <button
                  type="button"
                  className="vb-step"
                  aria-label={t.more}
                  disabled={!open || q >= type.max_quantity}
                  onClick={() => step(type.id, 1, type.max_quantity)}
                >
                  <Icon name="plus" />
                </button>
              </div>
            </div>
          );
        })}
      </div>

      <div className="vb-total">
        <span>{t.total}</span>
        <span id="vbTotal">
          <Money minor={minor} currency={tenant.currency} decimals={tenant.currency_decimals} format={formatMinor} />
        </span>
      </div>
      <button type="submit" className="btn btn-primary vb-pay" id="vbPay" disabled={busy || count === 0}>
        {busy ? <span className="vb-spin" aria-hidden="true" /> : <Icon name="credit-card-2-front" />}{' '}
        <span>{count > 0 && minor === 0 ? t.getTickets : t.payByCard}</span>
      </button>
      <p className="muted vb-secure">
        <Icon name="shield-lock" /> {t.secure}
      </p>
    </form>
  );
}
