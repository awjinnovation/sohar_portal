/**
 * A small PDF 1.4 writer: one JPEG image per page, the page the size of the
 * image. Enough for "all my tickets in one file" that opens anywhere and can
 * be shared (WhatsApp, mail) or printed. The tickets are drawn by the browser
 * (saveTicket.ts), so Arabic text is already shaped and no font is embedded.
 */
export interface PdfPage {
  /** Baseline JPEG bytes (what canvas.toBlob('image/jpeg') gives). */
  jpeg: Uint8Array;
  pixelWidth: number;
  pixelHeight: number;
  /** Page size in points (1/72 inch). */
  width: number;
  height: number;
}

const num = (n: number) => (Math.round(n * 100) / 100).toString();

/** A text string as UTF-16BE hex with a byte order mark (Arabic titles included). */
function pdfText(s: string): string {
  let hex = 'FEFF';
  for (let i = 0; i < s.length; i++) hex += s.charCodeAt(i).toString(16).padStart(4, '0').toUpperCase();
  return `<${hex}>`;
}

function pdfDate(d: Date): string {
  const p = (n: number) => String(n).padStart(2, '0');
  return `D:${d.getUTCFullYear()}${p(d.getUTCMonth() + 1)}${p(d.getUTCDate())}${p(d.getUTCHours())}${p(d.getUTCMinutes())}${p(d.getUTCSeconds())}Z`;
}

export function jpegPagesToPdf(pages: PdfPage[], title = ''): Blob {
  const enc = new TextEncoder();
  const chunks: Uint8Array[] = [];
  const offsets: number[] = [];
  let length = 0;
  const push = (data: string | Uint8Array) => {
    const bytes = typeof data === 'string' ? enc.encode(data) : data;
    chunks.push(bytes);
    length += bytes.length;
  };
  const obj = (id: number, body: () => void) => {
    offsets[id] = length;
    push(`${id} 0 obj\n`);
    body();
    push('\nendobj\n');
  };

  // Objects: 1 catalog, 2 page tree, 3 info, then page / image / content per page.
  const pageId = (i: number) => 4 + i * 3;
  const size = 4 + pages.length * 3;

  push('%PDF-1.4\n%âãÏÓ\n');
  obj(1, () => push('<< /Type /Catalog /Pages 2 0 R >>'));
  obj(2, () => push(`<< /Type /Pages /Count ${pages.length} /Kids [${pages.map((_, i) => `${pageId(i)} 0 R`).join(' ')}] >>`));
  obj(3, () => push(`<< /Title ${pdfText(title)} /Producer (Mawsim) /CreationDate (${pdfDate(new Date())}) >>`));
  pages.forEach((p, i) => {
    const id = pageId(i);
    obj(id, () =>
      push(
        `<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${num(p.width)} ${num(p.height)}] ` +
          `/Resources << /XObject << /Im${i} ${id + 1} 0 R >> /ProcSet [/PDF /ImageC] >> /Contents ${id + 2} 0 R >>`,
      ),
    );
    obj(id + 1, () => {
      push(
        `<< /Type /XObject /Subtype /Image /Width ${p.pixelWidth} /Height ${p.pixelHeight} ` +
          `/ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ${p.jpeg.length} >>\nstream\n`,
      );
      push(p.jpeg);
      push('\nendstream');
    });
    const content = `q ${num(p.width)} 0 0 ${num(p.height)} 0 0 cm /Im${i} Do Q`;
    obj(id + 2, () => push(`<< /Length ${content.length} >>\nstream\n${content}\nendstream`));
  });

  const xref = length;
  push(`xref\n0 ${size}\n0000000000 65535 f \n`);
  for (let id = 1; id < size; id++) push(`${String(offsets[id]).padStart(10, '0')} 00000 n \n`);
  push(`trailer\n<< /Size ${size} /Root 1 0 R /Info 3 0 R >>\nstartxref\n${xref}\n%%EOF\n`);
  return new Blob(chunks as BlobPart[], { type: 'application/pdf' });
}
