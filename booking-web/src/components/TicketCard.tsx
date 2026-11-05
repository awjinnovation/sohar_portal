import { useMemo, useState, type CSSProperties } from 'react';
import type { Lang, Ticket } from '../api';
import type { Strings } from '../i18n';
import { Icon } from '../icons';
import { qrMatrix, qrPath } from '../qr';
import { saveTicket } from '../saveTicket';

const STATUS_CLASS: Record<string, string> = {
  active: 'pill-success',
  used: 'pill-neutral',
  expired: 'pill-neutral',
  cancelled: 'pill-error',
  refunded: 'pill-error',
};

/** One ticket: type, day, entry QR (drawn here from the code), number, save as image. */
export function TicketCard(props: { ticket: Ticket; eventTitle: string; brand: string; filePrefix: string; lang: Lang; t: Strings }) {
  const { ticket, eventTitle, brand, filePrefix, lang, t } = props;
  const valid = ticket.status === 'active';
  const statusLabel = t.status[ticket.status] ?? ticket.status;
  const [saving, setSaving] = useState(false);
  const qr = useMemo(() => {
    const m = qrMatrix(ticket.qr_code);
    return { size: m.length, d: qrPath(m) };
  }, [ticket.qr_code]);

  const extras = [
    ticket.admits > 1 && ticket.admits_label ? `${t.admits} ${ticket.admits_label}` : null,
    ticket.requires_id ? t.idRequired : null,
  ].filter((x): x is string => !!x);

  const onSave = async () => {
    setSaving(true);
    try {
      await saveTicket({
        fileName: `${filePrefix}_ticket_${ticket.number || ticket.id}.png`.replace(/[^\w.-]+/g, '-'),
        dir: lang === 'ar' ? 'rtl' : 'ltr',
        brand,
        event: eventTitle,
        typeName: ticket.type_name,
        typeColor: ticket.type_color,
        status: statusLabel,
        statusValid: valid,
        when: ticket.when_label,
        number: ticket.number,
        qr: ticket.qr_code,
        extras,
      });
    } catch {
      window.print();
    } finally {
      setSaving(false);
    }
  };

  return (
    <article className="s-card vb-ticket" id={`ticket-${ticket.id}`} data-ticket-status={ticket.status}>
      <div className="vb-ticket-head">
        <span className="vb-badge" style={{ '--type-color': ticket.type_color } as CSSProperties}>
          <Icon name="ticket-perforated" />
          {ticket.type_name}
        </span>
        <span className={`pill ${STATUS_CLASS[ticket.status] ?? 'pill-neutral'}`}>{statusLabel}</span>
      </div>
      <div className="vb-ticket-event">{eventTitle}</div>
      <div className="muted vb-ticket-when">
        <Icon name="calendar3" /> {ticket.when_label}
      </div>
      <div className={`qr-panel${valid ? '' : ' is-void'}`} role="img" aria-label={t.entryQr}>
        <svg viewBox={`0 0 ${qr.size} ${qr.size}`} shapeRendering="crispEdges" xmlns="http://www.w3.org/2000/svg">
          <rect width={qr.size} height={qr.size} fill="#fff" />
          <path d={qr.d} fill="#000" />
        </svg>
      </div>
      <div className="vb-ticket-foot">
        <span className="mono ltr">{ticket.number}</span>
        {ticket.admits > 1 && ticket.admits_label && (
          <span className="pill pill-brand">
            <Icon name="people" />
            {t.admits} {ticket.admits_label}
          </span>
        )}
        {ticket.requires_id && (
          <span className="pill pill-warning">
            <Icon name="person-vcard" />
            {t.idRequired}
          </span>
        )}
      </div>
      <button type="button" className="btn btn-tonal vb-save no-print" onClick={onSave} disabled={saving}>
        <Icon name="download" /> {t.saveTicket}
      </button>
    </article>
  );
}
