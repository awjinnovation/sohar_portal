// Ticket type colours (docs/ticket_types_contract.md v1.2): organizers pick the
// colour of the printed ticket or wristband, so it may be light (yellow, white).
// Same rule as the backend (EventTicketType::textColorFor) and the apps.

export function luminance(hex: string | null | undefined): number {
  let h = String(hex ?? '').replace('#', '');
  if (h.length === 3) h = h.replace(/./g, '$&$&');
  if (!/^[0-9a-f]{6}$/i.test(h)) return 0;
  const [r, g, b] = [0, 2, 4].map((i) => {
    const v = parseInt(h.substring(i, i + 2), 16) / 255;
    return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
  });
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

/**
 * Text on a badge of [hex], max-contrast rule: dark when (L + 0.05) / 0.05
 * beats white's 1.05 / (L + 0.05), i.e. relative luminance L > 0.179.
 */
export function textOn(hex: string | null | undefined): string {
  return luminance(hex) > 0.179 ? '#111827' : '#FFFFFF';
}

/** Very light colours (white) also need a ring to be seen on a white page. */
export function needsRing(hex: string | null | undefined): boolean {
  return luminance(hex) > 0.8;
}
