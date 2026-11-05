import { qrMatrix } from './qr';

export interface TicketImage {
  fileName: string;
  dir: 'rtl' | 'ltr';
  brand: string;
  event: string;
  typeName: string;
  typeColor: string;
  status: string;
  statusValid: boolean;
  when: string;
  number: string;
  qr: string;
  extras: string[];
}

const W = 360; // CSS pixels; drawn at SCALE for a sharp image
const SCALE = 3;
const PAD = 22;

function cssVar(name: string, fallback: string): string {
  return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
}

/** Word wrap to at most `max` lines (the last one ends with … if cut). */
function wrap(ctx: CanvasRenderingContext2D, text: string, width: number, max: number): string[] {
  const words = text.split(/\s+/).filter(Boolean);
  const lines: string[] = [];
  let line = '';
  for (const word of words) {
    const next = line ? `${line} ${word}` : word;
    if (ctx.measureText(next).width <= width || !line) {
      line = next;
    } else {
      lines.push(line);
      line = word;
      if (lines.length === max) break;
    }
  }
  if (lines.length < max && line) lines.push(line);
  if (lines.length === max && words.join(' ') !== lines.join(' ')) lines[max - 1] = `${lines[max - 1].replace(/\s+\S*$/, '')}…`;
  return lines;
}

function roundRect(ctx: CanvasRenderingContext2D, x: number, y: number, w: number, h: number, r: number): void {
  ctx.beginPath();
  ctx.moveTo(x + r, y);
  ctx.arcTo(x + w, y, x + w, y + h, r);
  ctx.arcTo(x + w, y + h, x, y + h, r);
  ctx.arcTo(x, y + h, x, y, r);
  ctx.arcTo(x, y, x + w, y, r);
  ctx.closePath();
}

/** Draws the ticket card (same content as on screen) and returns a PNG. */
export async function renderTicket(t: TicketImage): Promise<Blob> {
  await document.fonts?.ready;
  const fontText = `${cssVar(t.dir === 'rtl' ? '--font-ar' : '--font-en', 'system-ui')}, system-ui, sans-serif`;
  const fontNum = `${cssVar('--font-num', 'system-ui')}, ui-monospace, monospace`;
  const rtl = t.dir === 'rtl';
  const startX = rtl ? W - PAD : PAD;
  const inner = W - PAD * 2;

  const canvas = document.createElement('canvas');
  const measure = canvas.getContext('2d')!;
  measure.font = `800 19px ${fontText}`;
  const titleLines = wrap(measure, t.event, inner, 3);
  const qrSize = 236;
  const extrasH = t.extras.length ? 24 : 0;
  const H = PAD + 30 + 14 + titleLines.length * 27 + 24 + 16 + qrSize + 16 + 22 + extrasH + 26 + 18 + PAD;

  canvas.width = W * SCALE;
  canvas.height = H * SCALE;
  const ctx = canvas.getContext('2d')!;
  ctx.scale(SCALE, SCALE);
  ctx.direction = t.dir;
  ctx.textBaseline = 'alphabetic';

  // card
  ctx.fillStyle = '#F3F4F6';
  ctx.fillRect(0, 0, W, H);
  roundRect(ctx, 6, 6, W - 12, H - 12, 18);
  ctx.fillStyle = '#FFFFFF';
  ctx.fill();
  ctx.strokeStyle = '#E5E7EB';
  ctx.lineWidth = 1;
  ctx.stroke();

  // type badge (start) and status pill (end)
  let y = PAD + 4;
  ctx.font = `700 13px ${fontText}`;
  const badgeW = Math.min(inner * 0.62, ctx.measureText(t.typeName).width + 26);
  roundRect(ctx, rtl ? W - PAD - badgeW : PAD, y, badgeW, 28, 14);
  ctx.fillStyle = t.typeColor || '#314F99';
  ctx.fill();
  ctx.fillStyle = '#FFFFFF';
  ctx.textAlign = 'center';
  ctx.fillText(t.typeName, rtl ? W - PAD - badgeW / 2 : PAD + badgeW / 2, y + 19, badgeW - 16);

  ctx.font = `600 12.5px ${fontText}`;
  const pillW = ctx.measureText(t.status).width + 22;
  roundRect(ctx, rtl ? PAD : W - PAD - pillW, y + 1, pillW, 26, 13);
  ctx.fillStyle = t.statusValid ? '#DCFCE7' : '#EEF0F3';
  ctx.fill();
  ctx.fillStyle = t.statusValid ? '#166534' : '#4B5563';
  ctx.fillText(t.status, rtl ? PAD + pillW / 2 : W - PAD - pillW / 2, y + 18);

  // event title + when
  y += 30 + 14;
  ctx.textAlign = 'start';
  ctx.fillStyle = '#111827';
  ctx.font = `800 19px ${fontText}`;
  for (const line of titleLines) {
    y += 22;
    ctx.fillText(line, startX, y);
    y += 5;
  }
  y += 20;
  ctx.font = `500 13.5px ${fontText}`;
  ctx.fillStyle = '#6B7280';
  ctx.fillText(t.when, startX, y, inner);

  // QR (white panel with a hairline, quiet zone included in the matrix)
  y += 16;
  const qx = (W - qrSize) / 2;
  roundRect(ctx, qx, y, qrSize, qrSize, 16);
  ctx.fillStyle = '#FFFFFF';
  ctx.fill();
  ctx.strokeStyle = '#E5E7EB';
  ctx.stroke();
  const matrix = qrMatrix(t.qr);
  const inset = 12;
  const cell = (qrSize - inset * 2) / matrix.length;
  ctx.fillStyle = '#000000';
  matrix.forEach((row, r) =>
    row.forEach((on, c) => {
      if (on) ctx.fillRect(qx + inset + c * cell, y + inset + r * cell, Math.ceil(cell * 100) / 100 + 0.02, Math.ceil(cell * 100) / 100 + 0.02);
    }),
  );
  y += qrSize + 16;

  // number, extras, brand
  ctx.textAlign = 'center';
  ctx.direction = 'ltr';
  ctx.font = `600 14px ${fontNum}`;
  ctx.fillStyle = '#111827';
  y += 16;
  ctx.fillText(t.number, W / 2, y, inner);
  ctx.direction = t.dir;
  if (t.extras.length) {
    y += 24;
    ctx.font = `600 13px ${fontText}`;
    ctx.fillStyle = '#374151';
    ctx.fillText(t.extras.join('  ·  '), W / 2, y, inner);
  }
  y += 26;
  ctx.strokeStyle = '#E5E7EB';
  ctx.setLineDash([5, 4]);
  ctx.beginPath();
  ctx.moveTo(PAD, y - 12);
  ctx.lineTo(W - PAD, y - 12);
  ctx.stroke();
  ctx.setLineDash([]);
  ctx.font = `700 12.5px ${fontText}`;
  ctx.fillStyle = '#6B7280';
  ctx.fillText(t.brand, W / 2, y + 8, inner);

  return new Promise((resolve, reject) => canvas.toBlob((b) => (b ? resolve(b) : reject(new Error('toBlob'))), 'image/png'));
}

/** Phones: the share sheet (Save Image / Photos). Elsewhere: a download. */
export async function saveTicket(t: TicketImage): Promise<void> {
  const blob = await renderTicket(t);
  const file = new File([blob], t.fileName, { type: 'image/png' });
  const touch = window.matchMedia?.('(pointer: coarse)').matches;
  if (touch && navigator.canShare?.({ files: [file] })) {
    try {
      await navigator.share({ files: [file], title: t.event });
      return;
    } catch (e) {
      if ((e as Error).name === 'AbortError') return;
    }
  }
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = t.fileName;
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1500);
}
