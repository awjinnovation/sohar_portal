import { encode } from 'uqr';

/** QR modules of a ticket code (error correction M, 1-module border: the same as the backend's ticket pages). */
export function qrMatrix(text: string): boolean[][] {
  return encode(text, { ecc: 'M', border: 1 }).data;
}

/** One SVG path for all dark modules (row runs merged), for <path d>. */
export function qrPath(matrix: boolean[][]): string {
  let d = '';
  matrix.forEach((row, y) => {
    let x = 0;
    while (x < row.length) {
      if (!row[x]) {
        x++;
        continue;
      }
      const start = x;
      while (x < row.length && row[x]) x++;
      d += `M${start} ${y}h${x - start}v1h${start - x}z`;
    }
  });
  return d;
}
