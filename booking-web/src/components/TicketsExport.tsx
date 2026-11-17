import { useState } from 'react';
import type { Ticket } from '../api';
import { Icon } from '../icons';
import { canShareFiles, downloadBlob, saveAllTickets, shareFiles, ticketsPdf } from '../saveTicket';
import { ticketImage, type TicketContext } from './TicketCard';

type Action = 'pdf' | 'share' | 'images';

/**
 * Every ticket at once, above the list: one PDF (a page per ticket) to keep
 * on the device, the same PDF to share (WhatsApp, mail) where the browser can
 * share files, and all tickets as images (one share sheet on phones).
 */
export function TicketsExport(props: { tickets: Ticket[] } & TicketContext) {
  const { tickets, ...c } = props;
  const { t } = c;
  const [busy, setBusy] = useState<Action | null>(null);
  const [failed, setFailed] = useState(false);
  const canShare = canShareFiles('application/pdf');
  const base = `${c.filePrefix}_tickets`.replace(/[^\w.-]+/g, '-');
  const title = `${c.eventTitle} · ${t.yourTickets}`;

  const run = (action: Action) => async () => {
    setBusy(action);
    setFailed(false);
    try {
      const images = tickets.map((ticket) => ticketImage(ticket, c));
      if (action === 'images') {
        await saveAllTickets(images, title);
      } else {
        const pdf = await ticketsPdf(images, title);
        const name = `${base}.pdf`;
        if (action === 'share') {
          if (!(await shareFiles([new File([pdf], name, { type: 'application/pdf' })], title))) downloadBlob(pdf, name);
        } else {
          downloadBlob(pdf, name);
        }
      }
    } catch {
      setFailed(true);
    } finally {
      setBusy(null);
    }
  };

  const label = (action: Action, text: string) => (busy === action ? t.exportPreparing : text);

  return (
    <div className="vb-export no-print" data-tickets-export>
      <button type="button" className="btn btn-primary" onClick={run('pdf')} disabled={busy !== null}>
        <Icon name="file-earmark-pdf" /> {label('pdf', tickets.length > 1 ? t.downloadAllPdf : t.downloadPdf)}
      </button>
      {canShare && (
        <button type="button" className="btn btn-tonal" onClick={run('share')} disabled={busy !== null}>
          <Icon name="share" /> {label('share', t.sharePdf)}
        </button>
      )}
      {tickets.length > 1 && (
        <button type="button" className="btn btn-tonal" onClick={run('images')} disabled={busy !== null}>
          <Icon name="images" /> {label('images', t.saveAllImages)}
        </button>
      )}
      {failed && <p className="vb-export-error">{t.exportFailed}</p>}
    </div>
  );
}
