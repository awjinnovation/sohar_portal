/**
 * Runtime configuration. The Docker image writes it into index.html at
 * container start (<meta name="booking-api">, docker/15-booking-config.envsh),
 * so one image runs against any backend; `npm run dev` uses VITE_API_BASE_URL.
 */
function meta(name: string): string {
  const value = document.querySelector<HTMLMetaElement>(`meta[name="${name}"]`)?.content ?? '';
  return value.startsWith('__') ? '' : value.trim();
}

const trim = (url: string) => url.replace(/\/+$/, '');

/** Backend root without /api/v1, e.g. https://mawsim.awj.om */
export const API_BASE_URL = trim(meta('booking-api') || import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8002');

/** Where "/" (no event in the address) sends visitors; empty = a short help page. */
export const HOME_URL = trim(meta('booking-home') || import.meta.env.VITE_HOME_URL || '');
