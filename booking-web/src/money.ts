/** Minor units → "12,450.000" (the backend's Fmt::number: thousands separator, fixed decimals, no sign). */
export function formatMinor(minor: number, decimals: number): string {
  return (Math.abs(minor) / 10 ** decimals).toLocaleString('en-US', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  });
}
